<?php

namespace App\Listeners;

use App\Events\WithdrawEvent;
use App\Traits\DataTransferTrait;

class OnWithdrawListener
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
     * @param BookingEvent|RegisterEvent|WithdrawEvent $event
     * @return void
     */
    public function handle(WithdrawEvent $event)
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
        $withdrawResponse = (new self)->guzzlePost(env('WALLET_ENDPOINT') . 'api/flexpay/commission/withdraw', $withdrawData);
        return $booking = json_decode($withdrawResponse);
    }
}
