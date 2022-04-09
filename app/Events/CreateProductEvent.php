<?php

namespace App\Events;

class CreateProductEvent extends Event
{
    public $productSteps;

    /**
     * Create a new event instance.
     * @param $productStep
     */
    public function


    __construct($productStep)
    {
        $this->productSteps = $productStep;
    }
}
