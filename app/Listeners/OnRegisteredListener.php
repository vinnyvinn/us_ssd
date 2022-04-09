<?php

namespace App\Listeners;

use App\Events\RegisterEvent;
use App\Helpers\UssdUtil;


class OnRegisteredListener
{
    /**
     * Create the event listener.
     *
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  RegisterEvent $event
     * @return void
     */
    public function handle(RegisterEvent $event)
    {
        if (!is_null($event->registerWatch)) {
            UssdUtil::createOrUpdate($event->registerWatch->customer_phone, $event->registerWatch->customer_email, $event->registerWatch->customer_name1, $event->registerWatch->customer_name2);
        }
    }
}
