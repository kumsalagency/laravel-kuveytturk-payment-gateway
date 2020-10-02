<?php


namespace KumsalAgency\Payment\KuveytTurk;


use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use KumsalAgency\Payment\KuveytTurk\Response\ThreeDPaymentResponse;
use KumsalAgency\Payment\PaymentException;
use KumsalAgency\Payment\PaymentGateway;
use KumsalAgency\Payment\PaymentResponse;
use Spatie\ArrayToXml\ArrayToXml;

class KuveytTurk extends PaymentGateway
{
    /**
     * KuveytTurk constructor.
     * @param Application $application
     * @param array $config
     */
    public function __construct(Application $application, array $config)
    {
        parent::__construct($application, $config);

        $this->client = Http::withoutVerifying()
            ->baseUrl($this->config['three_d_base_url']);

    }

    public function payment()
    {
        if ($this->isThreeD)
        {
            try {
                $response = $this->client->withBody(ArrayToXml::convert([
                    'HashData' => base64_encode(
                        sha1(
                            $this->config['merchant_id'] ?? ''.
                            $this->orderID.
                            $this->amount.
                            $this->successUrl.
                            $this->failUrl.
                            $this->config['username'] ?? ''.
                            base64_encode(sha1($this->config['password'] ?? '', "ISO-8859-9"))
                            , "ISO-8859-9"
                        )
                    ),
                    'APIVersion' => $this->config['API_version'] ?? '1.0.0',
                    'MerchantId' => $this->config['merchant_id'] ?? '',
                    'CustomerId' => $this->config['customer_id'] ?? '',
                    'UserName' => $this->config['username'] ?? '',
                    'CurrencyCode' => $this->config['currency_code'] ?? '0949',
                    'TransactionType' => 'Sale',
                    'TransactionSide' => 'Sale',
                    'TransactionSecurity' => '3',
                    'OkUrl' => $this->successUrl,
                    'FailUrl' => $this->failUrl,
                    'CardNumber' => $this->cardNumber,
                    'CardExpireDateYear' => Str::substr((string) $this->cardExpireDateYear,-2),
                    'CardExpireDateMonth' => Str::padLeft((string) $this->cardExpireDateMonth,2,'0'),
                    'CardCVV2' => $this->cardCVV2,
                    'CardHolderName' => $this->cardHolderName,
                    'CardType' => $this->cardType ?? 'Visa',
                    'BatchID' => 0,
                    'InstallmentCount' => $this->installmentCount ?? ($this->installmentCount > 1 ? $this->installmentCount : 0),
                    'Amount' => $this->amount * 100,
                    'DisplayAmount' => $this->amount * 100,
                    'MerchantOrderId' => $this->orderID,
                ], [
                    'rootElementName' => 'KuveytTurkVPosMessage',
                    '_attributes' => [
                        'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
                        'xmlns:xsd' => 'http://www.w3.org/2001/XMLSchema',
                    ],
                ], true, 'UTF-8'),'application/xml')
                    ->post('sanalposservice/Home/ThreeDModelPayGate');

                if (!$response->successful() || $response->body() == strip_tags($response->body()))
                {
                    throw new ConnectionException;
                }

                return $response->body();
            }
            catch(ConnectionException $exception)
            {
                throw new PaymentException(null,PaymentException::ErrorConnection,$exception);
            }
            catch (\Exception $exception)
            {
                throw new PaymentException(null,PaymentException::ErrorGeneral,$exception);
            }
        }
        else
        {
            throw new PaymentException(null,PaymentException::ErrorNotSupportedMailOrder);
        }
    }

    /**
     * @param Request $request
     * @return PaymentResponse
     * @throws PaymentException
     */
    public function paymentThreeDFallback(Request $request): PaymentResponse
    {
        try {
            if (!$request->has('AuthenticationResponse'))
            {
                throw new PaymentException(null,PaymentException::ErrorPosUnexpectedReturn);
            }

            $response = simplexml_load_string(urldecode($request->AuthenticationResponse));

            if (!isset($response->ResponseCode))
            {
                throw new PaymentException(null,PaymentException::ErrorPosUnexpectedReturn);
            }

            if ($response->ResponseCode->__toString() != '00')
            {
                return new ThreeDPaymentResponse(urldecode($request->AuthenticationResponse));
            }

            $responseApprove = $this->client->withBody(ArrayToXml::convert([
                'HashData' => base64_encode(
                    sha1(
                        $this->config['merchant_id'] ?? ''.
                        $this->orderID.
                        $this->amount.
                        $this->config['username'] ?? ''.
                        base64_encode(sha1($this->config['password'] ?? '', "ISO-8859-9"))
                        , "ISO-8859-9"
                    )
                ),
                'MerchantId' => $this->config['merchant_id'] ?? '',
                'CustomerId' => $this->config['customer_id'] ?? '',
                'UserName' => $this->config['username'] ?? '',
                'TransactionType' => 'Sale',
                'InstallmentCount' => $this->installmentCount ?? ($this->installmentCount > 1 ? $this->installmentCount : 0),
                'Amount' => $this->amount * 100,
                'MerchantOrderId' => $this->orderID,
                'TransactionSecurity' => '3',
                'KuveytTurkVPosAdditionalData' => [
                    'AdditionalData' => [
                        'Key' => 'MD',
                        'Data' => $response->MD->__toString()
                    ]
                ],
            ], [
                'rootElementName' => 'KuveytTurkVPosMessage',
                '_attributes' => [
                    'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
                    'xmlns:xsd' => 'http://www.w3.org/2001/XMLSchema',
                ],
            ], true, 'UTF-8'),'application/xml')
                ->post('sanalposservice/Home/ThreeDModelProvisionGate');

            if (!$responseApprove->successful())
            {
                throw new PaymentException(null,PaymentException::ErrorConnection);
            }

            return new ThreeDPaymentResponse($responseApprove->body());
        }
        catch (ConnectionException $exception)
        {
            throw new PaymentException(null,PaymentException::ErrorConnection);
        }
        catch (\Exception $exception)
        {
            throw new PaymentException(null,PaymentException::ErrorGeneral);
        }
    }
}