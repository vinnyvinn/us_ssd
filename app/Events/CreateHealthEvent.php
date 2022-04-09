<?php

namespace App\Events;

class CreateHealthEvent extends Event
{
    public $health;

    /**
     * Create a new event instance.
     * @param $health
     */
    public function __construct($health)
    {
        $this->health = $health;
    }
}
