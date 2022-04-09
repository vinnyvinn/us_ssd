<?php

namespace App\Listeners;


use App\CouponUtil;
use App\Events\PromoEvent;
use App\Helpers\UssdUtil;
use App\Traits\DataTransferTrait;
use Faker\Provider\Uuid;

class OnPromoListener
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
     * @param PromoEvent $event
     * @return void
     */
    public function handle(PromoEvent $event)
    {

        if (!is_null($event->customer) && !is_null($event->coupon)) {
            CouponUtil::query()->create([
                'coupon_id' => $event->coupon->id,
                'user_id' => $event->customer->user_id,
                'merchant_id' => $event->customer->user_id,
                'booking_reference' => $event->customer->user_id,
                'uuid' => Uuid::uuid()
            ]);

            $this->couponApply($event->customer, $event->coupon->coupon_code, $event->coupon->coupon_amount);
        }
    }

    public function couponApply($customer, $coupon_code, $couponAmount)
    {
        $money_id = UssdUtil::savePayment($customer, $coupon_code, $couponAmount, $customer->user_id);
        $data = [
            'userId' => $customer->user_id,
            'moneyInId' => $money_id,
            'amountCredited' => $couponAmount,
            'amountSource' => 'coupon'];
        $this->guzzlePost(env('WALLET_ENDPOINT') . 'api/flexpay/wallet/credit', $data);
    }
}
