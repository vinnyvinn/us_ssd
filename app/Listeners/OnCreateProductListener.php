<?php

namespace App\Listeners;

use App\PaymentSteps;
use App\ProductSteps;
use App\Helpers\UssdUtil;
use App\Events\BookingEvent;
use App\Events\PaymentEvent;
use App\Events\RegisterEvent;
use App\Traits\DataTransferTrait;
use App\Events\CreateProductEvent;
use Illuminate\Support\Facades\DB;

class OnCreateProductListener
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
     * @param BookingEvent|CreateProductEvent|RegisterEvent $event
     * @return void
     */
    public function handle(CreateProductEvent $event)
    {

        if (!is_null($event->productSteps)) {
            $data = [
                'product_name' => $event->productSteps->product_name,
                'outlet_id' => $event->productSteps->outlet_id,
                'phone_no' => UssdUtil::getCustomerCare(),
                'merchant_price' => $event->productSteps->product_price,
                'flexpay_price' => $event->productSteps->product_price,
                'product_booking_days' => $event->productSteps->product_payment_days,
                'promoter_id' => $event->productSteps->promoter_id,
                'product_category_id' => 6,
                'type' => ProductSteps::setProductType($event->productSteps->promoter_id) ?? 'product',
                'mode' => false
            ];
            $product = (new self)->guzzlePost(env('PRODUCT_ENDPOINT'), $data);;
            $new_product = json_decode($product);
            $product_id = $new_product->data->product->id;
            $outlet = UssdUtil::getOutlet($event->productSteps->outlet_id);
            $bookingData = [
                'product_id' => $product_id,
                'user_id' => $event->productSteps->user_id,
                'merchant_id' => $outlet->merchant_id,
                'booking_price' => $event->productSteps->product_price,
                'initial_deposit' => $event->productSteps->product_deposit,
                'booking_days' => $event->productSteps->product_payment_days,
                'outlet_id' => $event->productSteps->outlet_id,
                'promoter_id' => $event->productSteps->promoter_id,
                'booking_on_credit' => $event->productSteps->product_on_credit
            ];

            $bookingResponse = (new self)->guzzlePost(env('BOOKING_ENDPOINT'), $bookingData);
            $booking = json_decode($bookingResponse);
            $paymentSession = $this->create_booking_session($booking, $event->productSteps->product_deposit, $event->productSteps->customer_number, $event->productSteps->session_id);
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
        $merchant_id = DB::table('lp_outlets')
                ->where('id', $outlet->id)
                ->value('merchant_id');
        /**
         * get commission to be earned
         */
        $list = explode(',', env('MERCHANT_PROMOTER_FLAT_FEE'));
        if (!in_array($merchant_id, $list)) {
            $commissionRate = env('FLEX_RATE_COMMISSION');
            $min_commission = env('FLEX_MIN_COMMISSION', 50);
            $earnCommission = (($commissionRate / 100) * $new_product->data->product->merchant_price) >= $min_commission ? (($commissionRate / 100) * $new_product->data->product->merchant_price) : env('FLEX_MIN_COMMISSION');
        } else {
            $earnCommission = env('FLEX_FLAT_COMMISSION');
        }

        $data = ['recipients' => $event->productSteps->promoter_phone,
            'template_id' => '9',
            'first_name' => "Promoter",
            'product_name' => $new_product->data->product->product_name,
            'outlet' => $outlet->outlet_name,
            'product_code' => $new_product->data->product->product_code,
            'commission' => $earnCommission,
            'phone_no' => '0719725060'
        ];
        //Log::error("Sms Error :" . json_encode($data));
        $smsResponse = (new self)->guzzlePost(env('SMS_ENDPOINT'), $data);
        return $smsResponse;
        // Log::error($smsResponse);
    }

}
