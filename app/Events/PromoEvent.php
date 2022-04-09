<?php

namespace App\Events;


class PromoEvent extends Event
{

    public $coupon;
    public $customer;

    public function __construct($customer, $coupon)
    {
        $this->coupon = $coupon;
        $this->customer = $customer;
    }
}
