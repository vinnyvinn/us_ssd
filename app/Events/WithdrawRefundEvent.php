<?php

namespace App\Events;

class WithdrawRefundEvent extends Event
{
    public $withdrawCash;

    /**
     * Create a new event instance.
     * @param $withdrawCash
     * @internal param $createBooking
     */
    public function __construct($withdrawCash)
    {
        //
        $this->withdrawCash = $withdrawCash;
    }
}
