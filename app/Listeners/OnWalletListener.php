<?php

namespace App\Listeners;

use App\Events\BookingEvent;
use App\Events\OpenWalletEvent;
use App\Events\RegisterEvent;
use App\Traits\DataTransferTrait;


class OnWalletListener
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
     * @param BookingEvent|OpenWalletEvent|RegisterEvent $event
     * @return void
     */
    public function handle(OpenWalletEvent $event)
    {
        if ($event->user_id) {
            $this->openWallet($event);
        }
    }


    /**
     * @param $event
     * @return \Psr\Http\Message\StreamInterface
     */
    function openWallet($event)
    {
        $walletData = ['userId' => $event->user_id];
        $response = (new self)->guzzlePost(env('WALLET_ENDPOINT') . 'api/flexpay/wallet/create', $walletData);
        return $response;
    }


}
