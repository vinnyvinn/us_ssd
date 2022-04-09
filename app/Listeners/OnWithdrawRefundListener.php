<?php

namespace App\Listeners;

use App\Events\WithdrawEvent;
use App\Traits\DataTransferTrait;
use App\Events\WithdrawRefundEvent;
use Illuminate\Support\Facades\Log;

class OnWithdrawRefundListener
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
     * @param BookingEvent|RegisterEvent|WithdrawRefundEvent $event
     * @return void
     */
    public function handle(WithdrawRefundEvent $event)
    {
        if ($event->withdrawCash) {
            $this->initWithdraw($event->withdrawCash);
        }
    }


    function initWithdraw($withdrawData)
    {
        $this->walletWithdrawService($withdrawData->user_id, $withdrawData->withdraw_amount, $withdrawData->customer_phone);
    }


    function walletWithdrawService($userId, $amount, $phoneNumber)
    {
        $withdrawData = ['user_id' => $userId, 'with_amount' => $amount, 'phone_number' => $phoneNumber];
        $withdrawResponse = (new self)->guzzlePost(env('WALLET_ENDPOINT') . 'api/flexpay/customer/refund/cash', $withdrawData);
        return json_decode($withdrawResponse);
    }
}
