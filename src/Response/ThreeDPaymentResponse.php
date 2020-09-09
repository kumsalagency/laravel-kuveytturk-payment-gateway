<?php


namespace KumsalAgency\Payment\KuveytTurk\Response;


use KumsalAgency\Payment\PaymentResponse;

class ThreeDPaymentResponse extends PaymentResponse
{
    /**
     * ThreeDPaymentResponse constructor.
     * @param $response
     */
    public function __construct($response)
    {
        parent::__construct($response);

        $this->xml();
    }

    /**
     * Determine if the response was successful.
     *
     * @return bool
     */
    public function successful()
    {
        return isset($this->decoded['ResponseCode']) ? ($this->decoded['ResponseCode'] == '00') : false;
    }

    /**
     * Get message
     *
     * @return string
     */
     public function getMessage()
     {
         return trans()->has('payment::payment.kuveytturk.messages.'.$this->decoded['ResponseCode'] ?? '0') ?
             trans('payment::payment.kuveytturk.messages.'.$this->decoded['ResponseCode'] ?? '0') :
             $this->decoded['ResponseMessage'] ?? '';
     }

    /**
     * Get code
     *
     * @return string
     */
     public function getCode()
     {
         return $this->decoded['ResponseCode'] ?? '';
     }

    /**
     * Get ID
     *
     * @return string
     */
     public function getID()
     {
         return $this->decoded['OrderId'] ?? '0';
     }
}