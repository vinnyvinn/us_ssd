<?php

namespace App\Events;

class ValidateAndConfirmEvent extends Event
{
    public $receipt;
    /**
     * @var
     */
    public $phoneNumber;
    /**
     * @var
     */
    public $userId;

    /**
     * Create a new event instance.
     * @param $receipt
     * @param $phoneNumber
     * @param $userId
     * @internal param $product
     * @internal param $createBooking
     */
    public function __construct($receipt, $phoneNumber, $userId)
    {
        //
        $this->receipt = $receipt;
        $this->phoneNumber = $phoneNumber;
        $this->userId = $userId;
    }
}
