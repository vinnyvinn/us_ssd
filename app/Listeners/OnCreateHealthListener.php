<?php

namespace App\Listeners;

use App\Events\CreateHealthEvent;
use App\Events\PaymentEvent;
use App\PaymentSteps;
use App\Traits\DataTransferTrait;

class OnCreateHealthListener
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
     * @param CreateHealthEvent $event
     * @return void
     */
    public function handle(CreateHealthEvent $event)
    {
        if (!is_null($event->health)) {
            $bookingData = [
                'product_id' => $event->health->product_id,
                'user_id' => $event->health->user_id,
                'merchant_id' => $event->health->hospital_id,
                'booking_price' => $event->health->product_price,
                'initial_deposit' => $event->health->product_deposit,
                'booking_days' => $event->health->product_payment_instalment,
                'outlet_id' => $event->health->outlet_id,
                'promoter_id' => env('DEFAULT_PROMOTER'),
                'booking_on_credit' => 0
            ];
            $bookingResponse = (new self)->guzzlePost(env('BOOKING_ENDPOINT'), $bookingData);
            $booking = json_decode($bookingResponse);
            $paymentSession = $this->create_booking_session($booking, $event->health->product_deposit, $event->health->customer_number, $event->health->session_id);
            event(new PaymentEvent($paymentSession));
        }
    }

    function create_booking_session($booking, $booking_deposit, $phoneNumber, $sessionId)
    {
        $paymentSteps = new PaymentSteps();
        $paymentSteps->session_id = $sessionId;
        $paymentSteps->payment_amount = $booking_deposit;
        $paymentSteps->user_id = $booking->data->product_booking->user_id;
        $paymentSteps->customer_phone = $phoneNumber;
        $paymentSteps->payment_reference = $booking->data->product_booking->booking_reference;
        $paymentSteps->payment_status = 'initializePay';
        $paymentSteps->save();
        return $paymentSteps;
    }
}
