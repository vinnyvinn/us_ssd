<?php

namespace App\Listeners;

use App\Events\CreateLoanProductEvent;
use App\Helpers\UssdUtil;
use App\Traits\DataTransferTrait;

class OnCreateLoanProductListener
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
     * @param CreateLoanProductEvent $event
     * @return void
     */
    public function handle(CreateLoanProductEvent $event)
    {
        if (!is_null($event->productSteps)) {
            $data = [
                'product_name' => $event->productSteps->product_name,
                'outlet_id' => $event->productSteps->outlet_id,
                'phone_no' => env('FLEX_CUSTOMER_CARE_LINE'),
                'merchant_price' => $event->productSteps->product_price,
                'flexpay_price' => $event->productSteps->product_price,
                'product_booking_days' => $event->productSteps->product_payment_days,
                'promoter_id' => $event->productSteps->promoter_id,
                'product_category_id' => 6,
                'type' => 'product',
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

            (new self)->guzzlePost(env('BOOKING_ENDPOINT'), $bookingData);

            $this->notifyCustomer($event, $new_product);
            $this->notifyPromoter($event, $new_product, $outlet);

        }
    }


    public function notifyCustomer($event, $new_product)
    {
        $data = ['recipients' => $event->productSteps->customer_number,
            'template_id' => '43',
            'product_name' => $new_product->data->product->product_name,
            'product_price' => $event->productSteps->product_price,
            'non_refundable_deposit' => env('FLEX_LOAN_NONE_REFUNDABLE',1000),
            'deposit_percentage' => $event->productSteps->product_deposit,
            'initial_deposit' => $event->productSteps->product_deposit,
            'till_no' => env('FLEXPAY_TILL'),
            'terms_link' => env('FLEX_LOAN_TERM'),
            'product_code' => $new_product->data->product->product_code,
            'phone_no' => env('FLEX_CUSTOMER_CARE_LINE'),
        ];
        $smsResponse = (new self)->guzzlePost(env('SMS_ENDPOINT'), $data);
        return $smsResponse;
    }

    public function notifyPromoter($event, $new_product, $outlet)
    {

        $data = ['recipients' => $event->productSteps->promoter_phone,
            'template_id' => '9',
            'first_name' => "Promoter",
            'product_name' => $new_product->data->product->product_name,
            'outlet' => $outlet->outlet_name,
            'product_code' => $new_product->data->product->product_code,
            'phone_no' => env('FLEX_CUSTOMER_CARE_LINE')
        ];
        $smsResponse = (new self)->guzzlePost(env('SMS_ENDPOINT'), $data);
        return $smsResponse;
    }
}
