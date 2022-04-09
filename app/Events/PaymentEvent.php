<?php

namespace App\Events;

class PaymentEvent extends Event
{
    public $paymentSession;

    /**
     * Create a new event instance.
     * @param $paymentSession
     */
    public function __construct($paymentSession)
    {
        $this->paymentSession = $paymentSession;
    }
}
