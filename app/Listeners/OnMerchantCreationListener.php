<?php

namespace App\Listeners;

use Carbon\Carbon;
use App\Events\WithdrawEvent;
use App\Traits\DataTransferTrait;
use Illuminate\Support\Facades\DB;
use App\Events\MerchantCreationEvent;

class OnMerchantCreationListener
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
     * @param BookingEvent|RegisterEvent|WithdrawEvent $event
     * @return void
     */
    public function handle(MerchantCreationEvent $event)
    {
        if ($event->merchant) {
            $this->createOutlet($event->merchant);
            //send confirmation email
            //send confirmation sms
        }
    }

    public function createOutlet($merchant)
    {

        return DB::table('lp_outlets')->insertGetId([
            'outlet_name' => $merchant->merchant_name,
            'merchant_id' => $merchant->id,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);
    }
}
