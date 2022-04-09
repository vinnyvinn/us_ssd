<?php

namespace App\Listeners;


use App\Events\CheckoutEvent;
use App\Traits\DataTransferTrait;

class OnCheckoutListener
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
     * @param CheckoutEvent $event
     * @return void
     * @internal param $CheckoutEvent
     */
    public function handle(CheckoutEvent $event)
    {
        if ($event->checkout) {
            $this->initCheckout($event->checkout);
        }
    }


    function initCheckout($checkout)
    {
        $checkoutData = ['user_id' => $checkout->customer_id,
            'redemption_amount' => $checkout->checkout_amount,
            'reference' => $checkout->booking_reference,
            'short_code' => $checkout->short_code,
            'merchant_id' => $checkout->merchant_id
        ];
        $checkoutResponse = (new self)->guzzlePost(env('PAYMENT_METHODS') . 'api/b2b/request', $checkoutData);
        return $booking = json_decode($checkoutResponse);
    }


}
