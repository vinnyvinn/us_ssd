<?php

namespace App\Providers;

use App\Events\PromoEvent;
use App\Events\CouponEvent;
use App\Events\BookingEvent;
use App\Events\PaymentEvent;
use App\Events\CheckoutEvent;
use App\Events\ReferralEvent;
use App\Events\RegisterEvent;
use App\Events\WithdrawEvent;
use App\Events\CreateGoalEvent;
use App\Events\OpenWalletEvent;
use App\Events\DebitWalletEvent;
use App\Events\CreateHealthEvent;
use App\Events\ChangeProductEvent;
use App\Events\CreateProductEvent;
use App\Listeners\OnPromoListener;
use App\Events\WithdrawRefundEvent;
use App\Listeners\OnCouponListener;
use App\Listeners\OnWalletListener;
use App\Events\CreateInsuranceEvent;
use App\Listeners\OnBookingListener;
use App\Listeners\OnPaymentListener;
use App\Events\MerchantCreationEvent;
use App\Listeners\OnCheckoutListener;
use App\Listeners\OnReferralListener;
use App\Listeners\OnValidateListener;
use App\Listeners\OnWithdrawListener;
use App\Events\CreateLoanProductEvent;
use App\Events\DebitRefundWalletEvent;
use App\Events\ValidateAndConfirmEvent;
use App\Listeners\OnCreateGoalListener;
use App\Listeners\OnRegisteredListener;
use App\Listeners\OnDebitWalletListener;
use App\Listeners\OnCreateHealthListener;
use App\Listeners\OnChangeProductListener;
use App\Listeners\OnCreateProductListener;
use App\Listeners\OnWithdrawRefundListener;
use App\Listeners\OnCreateInsuranceListener;
use App\Listeners\OnMerchantCreationListener;
use App\Listeners\OnCreateLoanProductListener;
use App\Listeners\OnDebitRefundWalletListener;
use Laravel\Lumen\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        RegisterEvent::class => [
            OnRegisteredListener::class
        ],
        PaymentEvent::class => [
            OnPaymentListener::class
        ],
        BookingEvent::class => [
            OnBookingListener::class
        ],
        CreateProductEvent::class => [
            OnCreateProductListener::class
        ],
        WithdrawEvent::class => [
            OnWithdrawListener::class
        ],
        WithdrawRefundEvent::class => [
            OnWithdrawRefundListener::class
        ],
        ChangeProductEvent::class => [
            OnChangeProductListener::class
        ],
        OpenWalletEvent::class => [
            OnWalletListener::class
        ],
        ValidateAndConfirmEvent::class => [
            OnValidateListener::class
        ],
        DebitWalletEvent::class => [
            OnDebitWalletListener::class
        ],
        DebitRefundWalletEvent::class => [
            OnDebitRefundWalletListener::class
        ],
        CreateInsuranceEvent::class => [
            OnCreateInsuranceListener::class
        ],
        CreateHealthEvent::class => [
            OnCreateHealthListener::class
        ],
        CreateLoanProductEvent::class => [
            OnCreateLoanProductListener::class
        ],
        CheckoutEvent::class => [
            OnCheckoutListener::class
        ],
        CreateGoalEvent::class => [
            OnCreateGoalListener::class
        ],
        ReferralEvent::class => [
            OnReferralListener::class
        ],
        CouponEvent::class => [
            OnCouponListener::class
        ],
        PromoEvent::class => [
            OnPromoListener::class
        ],
        MerchantCreationEvent::class => [
            OnMerchantCreationListener::class
        ],
    ];
}
