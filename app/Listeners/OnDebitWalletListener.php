<?php

namespace App\Listeners;

use App\Events\DebitWalletEvent;
use App\Traits\DataTransferTrait;


class OnDebitWalletListener
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
     * @param DebitWalletEvent $event
     * @return void
     */
    public function handle(DebitWalletEvent $event)
    {
        if ($event->paymentSession) {
            $this->debitWallet($event);
        }
    }


    /**
     * @param $event
     * @return \Psr\Http\Message\StreamInterface
     */
    function debitWallet($event)
    {
        $walletData = ['userId' => $event->paymentSession->user_id, 'debitAmount' => $event->paymentSession->payment_amount, 'booking_reference' => $event->paymentSession->payment_reference];
        $response = (new self)->guzzlePost(env('WALLET_ENDPOINT') . 'api/flexpay/wallet/debit', $walletData);
        return $response;
    }
}
