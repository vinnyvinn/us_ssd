<?php

namespace App\Listeners;


use App\CouponUtil;
use App\Events\CouponEvent;
use App\Helpers\UssdUtil;
use App\Traits\DataTransferTrait;
use Faker\Provider\Uuid;

class OnCouponListener
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
     * @param CouponEvent $event
     * @return void
     */
    public function handle(CouponEvent $event)
    {
        if (!is_null($event->paymentSession) && !is_null($event->coupon)) {
            CouponUtil::query()->create([
                'coupon_id' => $event->coupon->id,
                'user_id' => $event->paymentSession->user_id,
                'merchant_id' => $event->paymentSession->merchant_id,
                'booking_reference' => $event->paymentSession->payment_reference,
                'uuid' => Uuid::uuid()
            ]);
            $customer = UssdUtil::getCustomer($event->paymentSession->user_id);
            $this->couponApply($customer, $event->coupon->coupon_code, $event->coupon->coupon_amount, $event->paymentSession->payment_reference);
        }
    }

    public function couponApply($customer, $coupon_code, $couponAmount, $booking_ref)
    {
        $money_id = UssdUtil::savePayment($customer, $coupon_code, $couponAmount, $booking_ref);
        $data = [
            'userId' => $customer->user_id,
            'moneyInId' => $money_id,
            'amountCredited' => $couponAmount,
            'amountSource' => 'coupon',
            'bookingRef' => $booking_ref];
        $this->guzzlePost(env('WALLET_ENDPOINT') . 'api/flexpay/wallet/credit', $data);
    }
}
