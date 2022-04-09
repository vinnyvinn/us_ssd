<?php

namespace App\Events;

class CreateInsuranceEvent extends Event
{
    public $bookingInsurance;

    /**
     * Create a new event instance.
     * @param $bookingInsurance
     */
    public function __construct($bookingInsurance)
    {
        $this->bookingInsurance = $bookingInsurance;
    }
}
