<?php

namespace App\Listeners;

use App\Events\CreateGoalEvent;
use App\Events\CreateHealthEvent;
use App\Events\PaymentEvent;
use App\PaymentSteps;
use App\Traits\DataTransferTrait;

class OnCreateGoalListener
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
     * @param CreateGoalEvent $event
     * @return void
     */
    public function handle(CreateGoalEvent $event)
    {
        if (!is_null($event->goal)) {
            $bookingData = [
                'product_id' => $event->goal->product_id,
                'user_id' => $event->goal->user_id,
                'merchant_id' => $event->goal->merchant_id,
                'booking_price' => $event->goal->product_price,
                'initial_deposit' => $event->goal->product_deposit,
                'booking_days' => $event->goal->product_payment_instalment,
                'outlet_id' => $event->goal->outlet_id,
                'promoter_id' => env('DEFAULT_PROMOTER'),
                'booking_on_credit' => 0
            ];
            $bookingResponse = (new self)->guzzlePost(env('BOOKING_ENDPOINT'), $bookingData);
            $booking = json_decode($bookingResponse);
            $paymentSession = $this->create_booking_session($booking, $event->goal->product_deposit, $event->goal->customer_number, $event->goal->session_id);
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
