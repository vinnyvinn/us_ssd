<?php

namespace App\Events;

use Illuminate\Support\Facades\Log;

class ChangeProductEvent extends Event
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
