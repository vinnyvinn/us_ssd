<?php

namespace App\Events;

class CreateLoanProductEvent extends Event
{
    public $productSteps;

    /**
     * Create a new event instance.
     * @param $productStep
     */
    public function __construct($productStep)
    {
        $this->productSteps = $productStep;
    }
}
