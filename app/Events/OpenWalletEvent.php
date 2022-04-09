<?php

namespace App\Events;

class OpenWalletEvent extends Event
{
    public $user_id;

    /**
     * Create a new event instance.
     * @param $user_id
     * @internal param $user
     * @internal param $createBooking
     */
    public function __construct($user_id)
    {
        //
        $this->user_id = $user_id;
    }
}
