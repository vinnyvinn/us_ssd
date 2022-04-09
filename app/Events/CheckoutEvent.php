<?php

namespace App\Events;

/**
 * @property  checkoutProcess
 */
class CheckoutEvent extends Event
{
    public $checkout;



    public function __construct($checkout)
    {
        //
        $this->checkout = $checkout;
    }
}
