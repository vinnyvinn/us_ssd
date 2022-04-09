<?php

namespace App\Events;

class RegisterEvent extends Event
{
    public $registerWatch;

    /**
     * Create a new event instance.
     *
     * @param $registerWatch
     */
    public function __construct($registerWatch)
    {
        //
        $this->registerWatch = $registerWatch;
    }
}
