<?php


namespace KumsalAgency\Payment\KuveytTurk;


use Illuminate\Support\ServiceProvider;
use KumsalAgency\Payment\Payment;

class KuveytTurkServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->afterResolving(Payment::class, function (Payment $payment) {
            $payment->extend("kuveytturk", function ($application,$config) use ($payment) {
                return new KuveytTurk($application,$config);
            });
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {

    }
}