<?php

namespace App\Listeners;

use App\Traits\DataTransferTrait;
use Illuminate\Support\Facades\Log;
use App\Events\DebitRefundWalletEvent;


class OnDebitRefundWalletListener
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
    public function handle(DebitRefundWalletEvent $event)
    {
        if ($event->paymentSession) {
            $this->debitRefundWallet($event);
        }
    }


    /**
     * @param $event
     * @return \Psr\Http\Message\StreamInterface
     */
    function debitRefundWallet($event)
    {
        $walletData = ['userId' => $event->paymentSession->user_id, 'debitAmount' => $event->paymentSession->payment_amount, 'booking_reference' => $event->paymentSession->payment_reference];
        $response = (new self)->guzzlePost(env('WALLET_ENDPOINT') . 'api/flexpay/wallet/refund/debit', $walletData);
        return $response;
    }
}
