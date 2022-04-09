<?php

namespace App\Events;

class DebitRefundWalletEvent extends Event
{
    public $paymentSession;

    /**
     * Create a new event instance.
     * @param $paymentSession
     * @internal param $user
     * @internal param $createBooking
     */
    public function __construct($paymentSession)
    {
        //
        $this->paymentSession = $paymentSession;
    }
}
