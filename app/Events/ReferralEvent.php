<?php

namespace App\Events;


class ReferralEvent extends Event
{


    public $referral;
    public $customer;


    public function __construct($referral,$customer)
    {
        $this->referral = $referral;
        $this->customer=$customer;
    }
}
