<?php

namespace App\Listeners;

use App\ProductSteps;
use App\Helpers\UssdUtil;
use App\Traits\DataTransferTrait;
use App\Events\ChangeProductEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OnChangeProductListener
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
     * @param ChangeProductEvent $event
     * @return void
     */
    public function handle(ChangeProductEvent $event)
    {
        if (!is_null($event->productSteps)) {
            $data = [
                'product_name' => $event->productSteps->product_name,
                'outlet_id' => $event->productSteps->outlet_id,
                'phone_no' => UssdUtil::getCustomerCare(),
                'merchant_price' => $event->productSteps->product_price,
                'flexpay_price' => $event->productSteps->product_price,
                'product_booking_days' => 30,
                'promoter_id' => $event->productSteps->promoter_id,
                'product_category_id' => 6,
                'type' => ProductSteps::setProductType($event->productSteps->promoter_id) ?? 'product',
                'mode' => false
            ];
            $product = (new self)->guzzlePost(env('PRODUCT_ENDPOINT'), $data);
            $new_product = json_decode($product);
            $product_id = $new_product->data->product->id;

            $bookingData = [
                'product_id' => $product_id,
                'product_price' => $event->productSteps->product_price,
                'booking_reference' => $event->productSteps->booking_reference
            ];

            $old_product_id = DB::table('product_booking')
                ->where('booking_reference', $event->productSteps->booking_reference)
                ->value('product_id');

            if (isset($old_product_id)) {
                $old_product = DB::table('lp_products')
                    ->where('id', $old_product_id)->first();
            }

            $bookingResponse = (new self)->guzzlePost(env('BOOKING_CHANGE_ENDPOINT'), $bookingData);
            $booking = json_decode($bookingResponse);

            if (isset($booking)) {
                $outlet = UssdUtil::getOutlet($event->productSteps->outlet_id);
                $promoter = DB::table('lp_promoters')->where('id', $event->productSteps->promoter_id)->first();

                if (isset($promoter, $outlet, $old_product)) {
                    $promoter_data = [
                        'recipients' => $promoter->phone_number,
                        'template_id' => '30',
                        'promoter_name' => $promoter->first_name,
                        'product_name' => $old_product->product_name,
                        'outlet' => $outlet->outlet_name,
                        'product_cost' => $old_product->merchant_price,
                        'new_product_name' => $event->productSteps->product_name,
                        'new_product_cost' => $event->productSteps->product_price,
                        'phone_no' => '0719725060'
                    ];

                    (new self)->guzzlePost(env('SMS_ENDPOINT'), $promoter_data);
                }
            }
            return;

        }
    }


}
