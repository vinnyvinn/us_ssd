<?php

namespace App\Listeners;


use App\Events\ReferralEvent;
use App\Helpers\UssdUtil;
use App\Traits\DataTransferTrait;

class OnReferralListener
{
    use DataTransferTrait;

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
     * @param ReferralEvent $event
     * @return void
     */
    public function handle(ReferralEvent $event)
    {

        if (!is_null($event->referral)) {
            $this->addReferral($event);
        }
    }


    public function addReferral($event)
    {
        $customer = UssdUtil::getCustomer($event->referral->user_id);
        if ($customer) {

            $data = ['recipients' => $customer->phone_number_1,
                'template_id' => '54',
                // 'first_name' => $customer->phone_number_1,
                'referee' => $event->referral->referee_phone_number,
                // 'code' => $event->referral->referral_code,
                'phone_no' => '0719725060'
            ];
            (new self)->guzzlePost(env('SMS_ENDPOINT'), $data);


            // referrer code
            $data = ['recipients' => $event->referral->referee_phone_number,
                'template_id' => '55',
                'first_name' => $event->customer->first_name,
                'referred' => $customer->phone_number_1,
                'reward' => env('REFERRAL_AMOUNT', 100),
                'phone_no' => '0719725060'
            ];
            (new self)->guzzlePost(env('SMS_ENDPOINT'), $data);
        }
    }

}
