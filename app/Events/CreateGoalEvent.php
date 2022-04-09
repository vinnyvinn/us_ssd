<?php

namespace App\Events;

class CreateGoalEvent extends Event
{
    public $goal;

    /**
     * Create a new event instance.
     * @param $goal
     * @internal param $health
     */
    public function __construct($goal)
    {
        $this->goal = $goal;
    }
}
