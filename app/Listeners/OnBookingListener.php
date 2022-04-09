<?php

namespace App\Listeners;

use App\Events\BookingEvent;
use App\Events\PaymentEvent;
use App\Events\RegisterEvent;
use App\Helpers\UssdUtil;
use App\PaymentSteps;
use App\Traits\DataTransferTrait;
use Illuminate\Support\Facades\Log;


class OnBookingListener
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
     * @param BookingEvent|RegisterEvent $event
     * @return void
     */
    public function handle(BookingEvent $event)
    {
        if ($event->createBooking) {
            $this->booking($event);
        }
    }


    /**
     * @param $event
     */
    function booking($event)
    {
        //Log::error("Ussd Booking");
        $product = UssdUtil::getProduct($event->createBooking->booking_code);
        $outlet = UssdUtil::getOutlet($product->outlet_id);
        if ($product) {
            $bookingData = [
                'product_id' => $product->id,
                'user_id' => $event->createBooking->user_id,
                'merchant_id' => $outlet->merchant_id,
                'booking_price' => $product->flexpay_price,
                'initial_deposit' => $event->createBooking->booking_deposit,
                'booking_days' => $product->product_booking_days,
                'outlet_id' => $product->outlet_id,
                'promoter_id' => $product->promoter_id,
            ];

            $bookingResponse = (new self)->guzzlePost(env('BOOKING_ENDPOINT'), $bookingData);
            $booking = json_decode($bookingResponse);
            $paymentSession = $this->create_booking_session($booking, $event->createBooking->booking_deposit, $event->createBooking->customer_phone, $event->createBooking->session_id);
            event(new PaymentEvent($paymentSession));
        } else {
            Log::error("No product found: Ussd book Product");
        }

    }

    function create_booking_session($booking, $booking_deposit, $phoneNumber, $sessionId)
    {
        $paymentSteps = new PaymentSteps();
        $paymentSteps->session_id = $sessionId;
        $paymentSteps->user_id = $booking->data->product_booking->user_id;
        $paymentSteps->customer_phone = $phoneNumber;
        $paymentSteps->payment_amount = $booking_deposit;
        $paymentSteps->payment_reference = $booking->data->product_booking->booking_reference;
        $paymentSteps->payment_status = 'initializePay';
        $paymentSteps->save();
        return $paymentSteps;
    }


}
