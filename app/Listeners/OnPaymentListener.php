<?php

namespace App\Listeners;

use App\Events\PaymentEvent;
use App\Events\RegisterEvent;
use App\Traits\DataTransferTrait;
use Illuminate\Support\Facades\Log;

class OnPaymentListener
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
     * @param PaymentEvent|RegisterEvent $event
     * @return void
     */
    public function handle(PaymentEvent $event)
    {
        if (strcasecmp($event->paymentSession->payment_status, 'initializePay') == 0) {
            $bookingMData = ['user_id' => $event->paymentSession->user_id, 'amount' => $event->paymentSession->payment_amount, 'phone' => $event->paymentSession->customer_phone, 'reference' => $event->paymentSession->payment_reference, 'description' => 'Payment Via USSD'];
           // Log::info('booking m data '.serialize($bookingMData));
            $bookingPayment = (new self)->guzzlePost(env('PAYMENT_METHODS') . 'stk_request', $bookingMData);
            $bookingMpesa = json_decode($bookingPayment);
            // Log::critical(print_r($bookingMpesa, true));
            if (isset($bookingMpesa->CheckoutRequestID)) {
                $message['data']['message'] = 'M-PESA initiated successfully.Please input you M-PESA PIN';
                $message['data']['code'] = 200;
            } else {
                $message['data']['message'] = 'There was an error initiating M-PESA checkout.Please try again';
                $message['data']['code'] = 422;
            }
           // Log::notice("Payment USSD " . serialize($message));
            return;
        } 
    }
}
