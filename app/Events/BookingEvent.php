<?php

namespace App\Events;

class BookingEvent extends Event
{
    public $createBooking;

    /**
     * Create a new event instance.
     * @param $createBooking
     */
    public function __construct($createBooking)
    {
        //
        $this->createBooking = $createBooking;
    }
}
