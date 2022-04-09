<?php

namespace App\Listeners;

use App\Events\BookingEvent;
use App\Events\CreateInsuranceEvent;
use App\Events\CreateProductEvent;
use App\Events\PaymentEvent;
use App\Events\RegisterEvent;
use App\Helpers\UssdUtil;
use App\PaymentSteps;
use App\Traits\DataTransferTrait;

class OnCreateInsuranceListener
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
     * @param CreateInsuranceEvent $event
     * @return void
     */
    public function handle(CreateInsuranceEvent $event)
    {
        if (!is_null($event->bookingInsurance)) {
            $data = [
                'product_name' => $event->bookingInsurance->product_name,
                'outlet_id' => env('BROKER_INSURANCE_OUTLET_ID'),
                'merchant_id' => env('BROKER_INSURANCE_ID'),
                'phone_no' => UssdUtil::getCustomerCare(),
                'merchant_price' => $event->bookingInsurance->product_proposed_premium,
                'flexpay_price' => $event->bookingInsurance->product_proposed_premium,
                'product_booking_days' => $event->bookingInsurance->product_proposed_premium_days,
                'promoter_id' => $event->bookingInsurance->promoter_id,
                'product_category_id' => 6,
                'type' => 'service',
                'mode' => false
            ];
            $product = (new self)->guzzlePost(env('PRODUCT_ENDPOINT'), $data);;
            $new_product = json_decode($product);
            $product_id = $new_product->data->product->id;
            $outlet = UssdUtil::getOutlet($data['outlet_id']);

            $bookingData = [
                'product_id' => $product_id,
                'user_id' => $event->bookingInsurance->user_id,
                'merchant_id' => $outlet->merchant_id,
                'booking_price' => $event->bookingInsurance->product_proposed_premium,
                'initial_deposit' => $event->bookingInsurance->product_premium_deposit,
                'booking_days' => $event->bookingInsurance->product_proposed_premium_days,
                'outlet_id' => $data['outlet_id'],
                'promoter_id' => $event->bookingInsurance->promoter_id,
            ];

            $bookingResponse = (new self)->guzzlePost(env('BOOKING_ENDPOINT'), $bookingData);
            $booking = json_decode($bookingResponse);
            $paymentSession = $this->create_booking_session($booking, $event->bookingInsurance->product_premium_deposit, $event->bookingInsurance->customer_number, $event->bookingInsurance->session_id);
            event(new PaymentEvent($paymentSession));
            $this->notifyPromoter($event, $new_product, $outlet);

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

    public function notifyPromoter($event, $new_product, $outlet)
    {

        $data = ['recipients' => $event->bookingInsurance->promoter_phone,
            'template_id' => '9',
            'first_name' => "Promoter",
            'product_name' => $new_product->data->product->product_name,
            'outlet' => $outlet->outlet_name,
            'product_code' => $new_product->data->product->product_code,
            'phone_no' => '0719725060'
        ];
        //Log::error("Sms Error :" . json_encode($data));
        $smsResponse = (new self)->guzzlePost(env('SMS_ENDPOINT'), $data);
       return $smsResponse;
       // Log::error($smsResponse);
    }
}
