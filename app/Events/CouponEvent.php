<?php

namespace App\Events;


class CouponEvent extends Event
{


    public $paymentSession;
    public $coupon;


    public function __construct($paymentSession, $coupon)
    {
        $this->paymentSession = $paymentSession;
        $this->coupon = $coupon;
    }
}
