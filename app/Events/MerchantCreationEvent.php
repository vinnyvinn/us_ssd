<?php

namespace App\Events;

class MerchantCreationEvent extends Event
{
    public $merchant;

    /**
     * Create a new event instance.
     * @param $withdrawCash
     * @internal param $createBooking
     */
    public function __construct($merchant)
    {
        //creation merchant
        $this->merchant = $merchant;
    }
}
