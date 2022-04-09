<?php

/**
 * Created by PhpStorm.
 * User: monongahela
 * Date: 19/03/2019
 * Time: 15:54
 */

namespace App\Helpers;

use App\Goal;
use Carbon\Carbon;
use App\PaymentSteps;
use App\ProductSteps;
use App\WithdrawSteps;
use App\GoalSettlement;
use App\SessionManager;
use Faker\Provider\Uuid;
use App\Helpers\UssdUtil;
use App\Events\PromoEvent;
use App\Events\CouponEvent;
use App\Events\PaymentEvent;
use App\Events\ReferralEvent;
use App\Events\WithdrawEvent;
use App\Events\CreateGoalEvent;
use App\Events\DebitWalletEvent;
use App\Traits\DataTransferTrait;
use App\Events\ChangeProductEvent;
use App\Events\CreateProductEvent;
use Illuminate\Support\Facades\DB;
use App\Events\WithdrawRefundEvent;
use Illuminate\Support\Facades\Log;
use libphonenumber\PhoneNumberUtil;
use App\Events\MerchantCreationEvent;
use App\Events\DebitRefundWalletEvent;
use App\Events\ValidateAndConfirmEvent;
use libphonenumber\PhoneNumberToCarrierMapper;


class FlexpayUssd
{

    use DataTransferTrait;

    /**
     * @param $request
     * @return mixed
     */
    public static function receiveUssd($request)
    {
        if (!isset($request->sessionId) || !isset($request->phoneNumber)) {
            dd("Take care! Are you part of the shared code to access API just like that :-) ");
        } else {
            self::saveSession($request);
            self::processUssdData($request);
        }
    }

    public static function saveSession($request)
    {
        $sessionId = $request->sessionId;
        $phoneNumber = $request->phoneNumber;
        $sessionManager = SessionManager::where('session_id', $sessionId)->where('phone_number', $phoneNumber)->first();
        if (is_null($sessionManager)) {
            $sessionManager = new SessionManager();
            $sessionManager->phone_number = $phoneNumber;
            $sessionManager->session_id = $sessionId;
            $sessionManager->short_tag = base64_encode(time() . "-" . $phoneNumber);
            $sessionManager->save();
        }
    }

    private static function processUssdData($request)
    {
        $sessionId = $request->sessionId;
        $text = UssdUtil::ussdHandler($request->text);
        $phoneNumber = UssdUtil::formatPhoneNo($request->phoneNumber);
        $promoter = UssdUtil::getPromoterByPhone($phoneNumber);
        $customer = UssdUtil::createOrUpdate($phoneNumber);
        if (isset($promoter)) {
            if (empty($text)) {
                $response = "CON Welcome to Flexpay\n";
                $response .= "1.Promoter \n";;
                $response .= "2.My account\n";
                UssdUtil::replyUssd($response);
            } else {
                $requestPhrase = explode('*', $text);
                self::processPromoterJourney($customer, $promoter, $promoter->id, $promoter->outlet_id, $phoneNumber, $sessionId, $requestPhrase);
            }
        } elseif (isset($customer)) {
            if (empty($text)) {
                $refundBalance = UssdUtil::getWalletAvailableBalance($customer->user_id);
                $response = "CON Earn while shopping.\n\n";
                $response .= "1.Create a goal\n";
                $response .= "2.Merchant goal\n";
                $response .= "3.Join Chama\n";
                $response .= "4.Make payment\n";
                $response .= "5.Merchant code\n";
                $response .= "6.Redeem points[" . number_format(UssdUtil::getPoints($customer->user_id)) . "]\n";
                $response .= "7.Refer friends\n";
                if ($refundBalance >= 50) {
                    $response .= "8.Get Refund[" . $refundBalance . "]\n";
                }
                if (env('FLEX_PROMO', false)) {
                    $response .= "9.Flex Promo \n";
                }
                UssdUtil::replyUssd($response);
            } else {
                $requestPhrase = explode('*', $text);
                self::processCustomerJourney($customer->user_id, $phoneNumber, $sessionId, $requestPhrase);
            }
        }
    }


    static function processPromoterJourney($customer, $promoter, $promoterId, $outletId, $phoneNumber, $sessionId, $requestPhrase)
    {

        if (count($requestPhrase) > 0) {
            if ($requestPhrase[0] == 1) {
                self::processPromoterAction($promoter, $promoterId, $outletId, $phoneNumber, $sessionId, $requestPhrase);
            } else if ($requestPhrase[0] == 2) {
                self::processCustomerAction($customer, $phoneNumber, $sessionId, $requestPhrase);
            } else {
                $response = "CON Invalid selection!\n";
                $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
                UssdUtil::replyUssd($response);
                return;
            }
        } else {
            $response = "END There was an error accessing USSD\n";
            UssdUtil::replyUssd($response);
        }
    }

    static function processPromoterAction($promoter, $promoterId, $outletId, $phoneNumber, $sessionId, $requestPhrase)
    {
        if (count($requestPhrase) == 1) {
            $response = "CON 1.Register Customer\n";
            $response .= "2.Change product\n";
            $response .= "3.Validate Receipt\n";
            $response .= "4.Commission\n";
            $response .= "5.Flex Agent\n";
            UssdUtil::replyUssd($response);
        } else {
            array_shift($requestPhrase);
            if ($requestPhrase[0] == 1) {
                self::promoterCreateProduct($promoter->user_id, $promoterId, $outletId, $phoneNumber, $sessionId, $requestPhrase);
            } elseif ($requestPhrase[0] == 2) {
                self::promoterChangeProduct($promoter->user_id, $promoterId, $outletId, $phoneNumber, $sessionId, $requestPhrase);
            } elseif ($requestPhrase[0] == 3) {
                self::validationAndConfirmation($promoter, $phoneNumber, $sessionId, $requestPhrase);
            } elseif ($requestPhrase[0] == 4) {
                self::promoterWithdraw($promoter->user_id, $phoneNumber, $sessionId, $requestPhrase);
            } elseif ($requestPhrase[0] == 5) {
                self::merchantRegstration($promoter->user_id, $phoneNumber, $sessionId, $requestPhrase);
            } else {
                $response = "CON Invalid selection!\n";
                $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
                UssdUtil::replyUssd($response);
                return;
            }
        }
    }

    private static function processCustomerAction($customer, $phoneNumber, $sessionId, $requestPhrase)
    {
        if (count($requestPhrase) == 1) {
            $refundBalance = UssdUtil::getWalletAvailableBalance($customer->user_id);
            $response = "CON Earn while shopping.\n\n";
            $response .= "1.Create a goal\n";
            $response .= "2.Merchant goal\n";
            $response .= "3.Join Chama\n";
            $response .= "4.Make payment\n";
            $response .= "5.Merchant code\n";
            $response .= "6.Redeem points[" . number_format(UssdUtil::getPoints($customer->user_id)) . "]\n";
            $response .= "7.Refer friends\n";
            if ($refundBalance >= 50) {
                $response .= "8.Get Refund[" . $refundBalance . "]\n";
            }
            if (env('FLEX_PROMO', false)) {
                $response .= "9.Flex Promo \n";
            }
            UssdUtil::replyUssd($response);
        } else {
            array_shift($requestPhrase);
            self::processCustomerJourney($customer->user_id, $phoneNumber, $sessionId, $requestPhrase);
        }
    }

    static function processCustomerJourney($userId, $phoneNumber, $sessionId, $requestPhrase)
    {
        if (count($requestPhrase) > 0) {
            if ($requestPhrase[0] == 1) {
                self::personalGoal($userId, $phoneNumber, $sessionId, $requestPhrase);
            } elseif ($requestPhrase[0] == 2) {
                self::merchantGoal($userId, $phoneNumber, $sessionId, $requestPhrase);
            } elseif ($requestPhrase[0] == 3) {
                self::processChama($userId, $phoneNumber, $sessionId, $requestPhrase);
            } elseif ($requestPhrase[0] == 4) {
                self::makePayment($userId, $phoneNumber, $sessionId, $requestPhrase);
            } elseif ($requestPhrase[0] == 5) {
                self::merchantTillProcess($userId, $phoneNumber, $sessionId, $requestPhrase);
            } elseif ($requestPhrase[0] == 6) {
                self::processRedeemPoint($userId, $phoneNumber, $requestPhrase);
            } elseif ($requestPhrase[0] == 7) {
                self::referFriend($userId, $phoneNumber, $sessionId, $requestPhrase);
            } elseif ($requestPhrase[0] == 8) {
                self::requestRefund($userId, $phoneNumber, $sessionId, $requestPhrase);
            } elseif ($requestPhrase[0] == 9) {
                self::promoCode($userId, $requestPhrase);
            } else {
                $response = "CON Invalid selection!\n";
                $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
                UssdUtil::replyUssd($response);
            }
        } else {
            $response = "CON Invalid selection!\n";
            $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
            UssdUtil::replyUssd($response);
        }
    }

    /**
     * @param $customerId
     * @param $promoterId
     * @param $outletId
     * @param $phoneNumber
     * @param $sessionId
     * @param $requestPhrase
     */
    static function promoterCreateProduct($customerId, $promoterId, $outletId, $phoneNumber, $sessionId, $requestPhrase)
    {
        //die(var_dump($requestPhrase));
        if (count($requestPhrase) == 1) {
            // Business logic for first level response
            $response = "CON Enter product name and code";
            UssdUtil::replyUssd($response);
        } else if (count($requestPhrase) == 2) {
            if (empty($requestPhrase[1])) {
                $response = "CON Product name  cannot be Empty!\n";
                $response .= UssdUtil::$GO_BACK . " Go Back\n";
                $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
                UssdUtil::replyUssd($response);
                return;
            }
            // Business logic for first level response
            $response = "CON Enter product price";
            UssdUtil::replyUssd($response);
            $productStep = new ProductSteps();
            $productStep->product_name = $requestPhrase[1];
            $productStep->session_id = $sessionId;
            $productStep->promoter_id = $promoterId;
            $productStep->promoter_phone = $phoneNumber;
            $productStep->outlet_id = $outletId;
            $productStep->user_id = $customerId;
            $productStep->customer_tag = Uuid::uuid();
            $productStep->save();
        } else if (count($requestPhrase) == 3) {
            $productPrice = UssdUtil::numberDigit($requestPhrase[2]);
            if (!self::validateAmount($productPrice)) {
                return;
            }
            // Business logic for first level response
            $response = "CON Enter duration of payment(in days) \n e.g 60";
            UssdUtil::replyUssd($response);

            $productStep = ProductSteps::where('session_id', $sessionId)->first();
            $productStep->product_price = $productPrice;
            $productStep->save();
        } else if (count($requestPhrase) == 4) {
            // Business logic for first level response
            $paymentDays = UssdUtil::numberDigit($requestPhrase[3]);
            if (!self::validatePaymentDate($paymentDays, $requestPhrase[3])) {
                return;
            }

            $response = "CON Enter customer number\n e.g 0711000000";
            UssdUtil::replyUssd($response);

            $productStep = ProductSteps::where('session_id', $sessionId)->first();
            $productStep->product_payment_days = $paymentDays;
            $productStep->save();
        } else if (count($requestPhrase) == 5) {
            $phoneNumber = UssdUtil::formatPhoneNo($requestPhrase[4]);
            if (!is_numeric($phoneNumber) || (strlen($phoneNumber) > env('PHONE_NUMBER_LENGTH', 12))) {
                $response = "CON Customer phone number '" . $requestPhrase[4] . "' is invalid!\n";
                $response .= UssdUtil::$GO_BACK . " Go Back\n";
                $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
                UssdUtil::replyUssd($response);
                return;
            }
            $phoneUtil = PhoneNumberUtil::getInstance();
            $objectPhoneNumber = $phoneUtil->parse($phoneNumber, "KE");
            $carrierMapper = PhoneNumberToCarrierMapper::getInstance();
            $carrierProvider = $carrierMapper->getNameForNumber($objectPhoneNumber, "en");
            if (strcasecmp($carrierProvider, 'Safaricom') != 0 && !UssdUtil::isValidNewNumber($phoneNumber)) {
                $response = "CON Customer phone pumber '" . $requestPhrase[4] . "' does not support m-pesa!\n";
                $response .= UssdUtil::$GO_BACK . " Go Back\n";
                UssdUtil::replyUssd($response);
                return;
            }
            // Business logic for first level response
            $response = "CON Enter booking deposit";
            UssdUtil::replyUssd($response);
            $productStep = ProductSteps::where('session_id', $sessionId)->first();
            $productStep->customer_number = $phoneNumber;
            $productStep->user_id = UssdUtil::createOrUpdate($productStep->customer_number)->user_id;
            $productStep->save();
        } else if (count($requestPhrase) == 6) {
            $productDeposit = UssdUtil::numberDigit($requestPhrase[5]);
            if (!self::validateAmount($productDeposit)) {
                return;
            }
            $response = "END Please ask the customer to input m-pesa pin";
            UssdUtil::replyUssd($response);
            $productStep = ProductSteps::where('session_id', $sessionId)->first();
            $productStep->product_on_credit = 0;
            $productStep->product_deposit = $productDeposit;
            $productStep->save();
            event(new CreateProductEvent($productStep));
        }
    }

    /**
     * @param $userId
     * @param $promoterId
     * @param $phoneNumber
     * @param $sessionId
     * @param $requestPhrase
     * @internal param $outletId
     *
     */
    static function promoterChangeProduct($userId, $promoterId, $outletId, $phoneNumber, $sessionId, $requestPhrase)
    {
        if (count($requestPhrase) == 1) {
            $response = "CON Enter customer's phone";
            UssdUtil::replyUssd($response);
        } else if (count($requestPhrase) == 2) {
            $phoneNumber = UssdUtil::formatPhoneNo($requestPhrase[1]);
            if (!is_numeric($phoneNumber) || (strlen($phoneNumber) > env('PHONE_NUMBER_LENGTH', 12))) {
                $response = "CON Customer phone number '" . $requestPhrase[1] . "' is invalid!\n";
                $response .= UssdUtil::$GO_BACK . " Go Back\n";
                $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
                UssdUtil::replyUssd($response);
                return;
            }
            $phoneUtil = PhoneNumberUtil::getInstance();
            $objectPhoneNumber = $phoneUtil->parse($phoneNumber, "KE");
            $carrierMapper = PhoneNumberToCarrierMapper::getInstance();
            $carrierProvider = $carrierMapper->getNameForNumber($objectPhoneNumber, "en");
            if (strcasecmp($carrierProvider, 'Safaricom') != 0 && !UssdUtil::isValidNewNumber($phoneNumber)) {
                $response = "CON Customer phone number '" . $requestPhrase[1] . "' does not support m-pesa!\n";
                $response .= UssdUtil::$GO_BACK . " Go Back\n";
                $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
                UssdUtil::replyUssd($response);
                return;
            }
            $customerId = optional(UssdUtil::createOrUpdate($phoneNumber))->user_id;
            $booking = UssdUtil::getBookings($customerId);
            if ($booking->isEmpty()) {
                $response = "CON This customer does not have an active booking\n";
                $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
            } else {
                $response = "CON Select the booking to Change\n";
                $response .= self::create_payment_session($userId, $booking, $phoneNumber, $sessionId);
            }
            UssdUtil::replyUssd($response);
            $productStep = ProductSteps::where('session_id', $sessionId)->first();
            if (is_null($productStep)) {
                $productStep = new ProductSteps();
            }
            $productStep->customer_number = UssdUtil::formatPhoneNo($requestPhrase[1]);
            $productStep->session_id = $sessionId;
            $productStep->promoter_id = $promoterId;
            $productStep->user_id = $userId;
            $productStep->outlet_id = $outletId;
            $productStep->promoter_phone = $phoneNumber;
            $productStep->customer_tag = Uuid::uuid();
            $productStep->save();
        } else if (count($requestPhrase) == 3) {
            if (!is_numeric($requestPhrase[2])) {
                $response = "CON The Selection must be numerical!\n";
                $response .= UssdUtil::$GO_BACK . " Go Back\n";
                $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
                UssdUtil::replyUssd($response);
                return;
            }
            $productStep = ProductSteps::where('session_id', $sessionId)->first();
            $payment_session_List = self::get_payment_session($sessionId);
            $selection = intval(UssdUtil::numberDigit($requestPhrase[2])) - 1;
            $stored_variable = unserialize($payment_session_List->payment_list);
            if (($selection < 0 || ($selection >= sizeof($stored_variable)))) {
                $response = "CON Invalid Selection !\n";
                $response .= UssdUtil::$GO_BACK . " Go Back\n";
                $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
                UssdUtil::replyUssd($response);
                return;
            }
            $response = "CON Enter New product name/code\n";
            UssdUtil::replyUssd($response);
            $productStep->booking_selection = intval($requestPhrase[2]) - 1;
            $booking_ref = $stored_variable[$productStep->booking_selection];
            $productStep->booking_reference = $booking_ref;
            $productStep->save();
        } else if (count($requestPhrase) == 4) {
            // Business logic for first level response
            if (empty($requestPhrase[3])) {
                $response = "CON Product name cannot be Empty!\n";
                $response .= UssdUtil::$GO_BACK . " Go Back\n";
                $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
                UssdUtil::replyUssd($response);
                return;
            }
            $response = "CON Enter product Price";
            UssdUtil::replyUssd($response);
            $productStep = ProductSteps::where('session_id', $sessionId)->first();
            $productStep->product_name = $requestPhrase[3];
            $productStep->save();
        } else if (count($requestPhrase) == 5) {
            $productPrice = UssdUtil::numberDigit($requestPhrase[4]);
            if (!self::validateAmount($productPrice)) {
                return;
            }

            $productStep = ProductSteps::where('session_id', $sessionId)->first();
            $booking = UssdUtil::getBookingByRef($productStep->booking_reference);
            $amount_paid = UssdUtil::getTotalPaid($booking->id);
            if ($productPrice < $amount_paid) {
                $response = "CON New product price cannot be less than amount already paid";
                $response .= UssdUtil::$GO_BACK . " Go Back\n";
                $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
                UssdUtil::replyUssd($response);
            } else {
                $response = "END Please wait for confirmation";
                UssdUtil::replyUssd($response);
                $productStep->product_price = $productPrice;
                $productStep->save();
                event(new ChangeProductEvent($productStep));
            }
        }
    }


    static function promoterWithdraw($userId, $phoneNumber, $sessionId, $requestPhrase)
    {
        $wallet_balance = UssdUtil::getSuperWalletBalance($userId);
        if (!env('FORCE_WITHDRAW_B2C', false) && Carbon::today()->dayOfWeek != Carbon::FRIDAY) {
            $response = "CON You will be withdrawing your sales commission on FRIDAY. Total is " . $wallet_balance . "\n";
            $response .= UssdUtil::$GO_BACK . " Go Back\n";
            $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
            UssdUtil::replyUssd($response);
            return;
        }
        if (count($requestPhrase) == 1) {
            // Business logic for first level response
            $response = "CON Commission : Ksh " . UssdUtil::getSuperWalletBalance($userId) . "\n";
            $response .= "Enter amount to withdraw\n";
            UssdUtil::replyUssd($response);
        } else if (count($requestPhrase) == 2) {
            $commission = UssdUtil::numberDigit($requestPhrase[1]);
            if (!self::validateAmount($commission)) {
                return;
            }
            if ($commission < 50) {
                $response = "CON You cannot withdraw less than KES.50";
                $response .= UssdUtil::$GO_BACK . " Go Back\n";
                $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
                UssdUtil::replyUssd($response);
                return;
            }
            if ($commission > UssdUtil::getSuperWalletBalance($userId)) {
                $response = "CON You cannot withdraw more than your balance";
                $response .= UssdUtil::$GO_BACK . " Go Back\n";
                $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
                UssdUtil::replyUssd($response);
                return;
            }
            // Business logic for first level response
            $response = "END Wait for withdraw response";
            UssdUtil::replyUssd($response);

            $withdrawStep = new WithdrawSteps();
            $withdrawStep->withdraw_amount = $requestPhrase[1];
            $withdrawStep->session_id = $sessionId;
            $withdrawStep->customer_phone = $phoneNumber;
            $withdrawStep->user_id = $userId;
            $withdrawStep->save();
            event(new WithdrawEvent($withdrawStep));
        }
    }

    static function requestRefund($userId, $phoneNumber, $sessionId, $requestPhrase)
    {
        $balance = UssdUtil::getWalletAvailableBalance($userId);
        $fee = (doubleval(env('WALLET_TRANSACTION_FEE', 1)) / 100);
        $charges = ceil($balance * $fee);
        $deduct = ($charges >= 100 ? 100 : $charges);
        $amount = ($balance - $deduct);
        if (count($requestPhrase) == 1) {
            // Business logic for first level response
            $response = "CON Refund : Ksh " . number_format($amount) . "\n";
            $response .= "Transaction fee : Ksh " . number_format($deduct) . "\n";
            $response .= "Enter amount to withdraw\n";
            UssdUtil::replyUssd($response);
        } else if (count($requestPhrase) == 2) {
            $refundAmount = UssdUtil::numberDigit($requestPhrase[1]);
            if (!self::validateAmount($refundAmount)) {
                return;
            }
            if ($refundAmount < 50) {
                $response = "CON You cannot withdraw less than KES.50\n";
                $response .= UssdUtil::$GO_BACK . " Go Back\n";
                $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
                UssdUtil::replyUssd($response);
                return;
            }
            if ($refundAmount > $amount) {
                $response = "CON You cannot request more than your total refund\n";
                $response .= UssdUtil::$GO_BACK . " Go Back\n";
                $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
                UssdUtil::replyUssd($response);
                return;
            }
            // Business logic for first level response
            $response = "END Wait for refund response";
            UssdUtil::replyUssd($response);
            $withdrawStep = new WithdrawSteps();
            $withdrawStep->withdraw_amount = $requestPhrase[1];
            $withdrawStep->session_id = $sessionId;
            $withdrawStep->customer_phone = $phoneNumber;
            $withdrawStep->user_id = $userId;
            $withdrawStep->save();
            event(new WithdrawRefundEvent($withdrawStep));
        }
    }

    static function promoCode($userId, $requestPhrase)
    {

        if (count($requestPhrase) == 1) {
            // Business logic for first level response
            $response = "CON Enter the Promo code\n";
            UssdUtil::replyUssd($response);
        } else if (count($requestPhrase) == 2) {
            // Business logic for first level response
            $coupon = UssdUtil::getCoupon($requestPhrase[1]);
            if (!$coupon) {
                $response = "CON This coupon code is invalid!\n";
                $response .= UssdUtil::$GO_BACK . " Go Back\n";
                $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
                UssdUtil::replyUssd($response);
                return;
            } elseif (UssdUtil::hasConsumedCoupon($userId, $coupon->id)) {
                $response = "CON This coupon cannot be used more than once!\n";
                $response .= UssdUtil::$GO_BACK . " Go Back\n";
                $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
                UssdUtil::replyUssd($response);
                return;
            } elseif (UssdUtil::hasConsumedRecently($userId)) {
                $response = "CON You have applied another promo Coupon for this campaign!\n";
                $response .= UssdUtil::$GO_BACK . " Go Back\n";
                $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
                UssdUtil::replyUssd($response);
                return;
            }
            $response = "END You have been given KES." . $coupon->coupon_amount . " to start Lipia Polepole";
            UssdUtil::replyUssd($response);
            //apply coupon once
            $customer = UssdUtil::getCustomer($userId);
            event(new PromoEvent($customer, $coupon));
        }
    }


    static function referFriend($userId, $phoneNumber, $sessionId, $requestPhrase)
    {
        if (count($requestPhrase) == 1) {
            // Business logic for first level response
            $response = "CON Enter Friend's Phone No#\n e.g 0700000000";
            UssdUtil::replyUssd($response);
        } else if (count($requestPhrase) == 2) {
            $referralNumber = UssdUtil::formatPhoneNo($requestPhrase[1]);
            $phoneUtil = PhoneNumberUtil::getInstance();
            $objectPhoneNumber = $phoneUtil->parse($referralNumber, "KE");
            if (!is_numeric($phoneNumber) || (!$phoneUtil->isValidNumber($objectPhoneNumber) && !UssdUtil::isValidNewNumber($referralNumber))) {
                $response = "CON Referred Phone Number '" . $requestPhrase[1] . "' is NOT invalid!\n";
                $response .= UssdUtil::$GO_BACK . " Go Back\n";
                $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
                UssdUtil::replyUssd($response);
                return;
            }

            if (UssdUtil::customerExist($referralNumber)) {
                $response = "CON The phone number provided already exist as customer\n";
                $response .= UssdUtil::$GO_BACK . " Go Back\n";
                $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
                UssdUtil::replyUssd($response);
            }

            if (!in_array($phoneNumber, explode(',', UssdUtil::flexPayPromoters())) && UssdUtil::isPromoter($userId)) {
                $response = "CON Promoters referrals are limited.\n";
                $response .= UssdUtil::$GO_BACK . " Go Back\n";
                $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
                UssdUtil::replyUssd($response);
            } else {
                $response = "END Thank you for referring " . $referralNumber . " to Flexpay\n";
                UssdUtil::replyUssd($response);
                $customer = UssdUtil::getCustomer($userId);
                $referral = UssdUtil::createReferral($referralNumber, $phoneNumber, $userId);
                event(new ReferralEvent($referral, $customer));
            }
        }
    }

    static function processRedeemPoint($user_id, $phoneNumber, $requestPhrase)
    {
        if (count($requestPhrase) == 1) {
            $point = UssdUtil::getPoints($user_id);
            if ($point < 10) {
                $response = "CON Minimum of 10 points needed to proceed with redeeming.\n";
                $response .= UssdUtil::$GO_BACK . " Go Back\n";
                $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
            } else if ($point >= 10) {
                $response = "CON Redeem Flex Points?\n";
                $response .= "1.Yes\n";
                $response .= "0.Exit\n";
            } else {
                $response = "END You have " . $point . " Points.\n";
            }
            UssdUtil::replyUssd($response);
        } else if ($requestPhrase[1] == 1) {
            $redeemedAmount = UssdUtil::redeemFlexPoint($user_id);
            if ($redeemedAmount > 0) {
                $points = (($redeemedAmount * 100) / env('FLEX_LOYALTY_POINT_PERCENTAGE'));
                $response = "END Thanks Redeeming Flex Points";
                UssdUtil::replyUssd($response);
                self::redemptionMessage($phoneNumber, $points, $redeemedAmount);
            } else {
                $response = "CON Failed to Redeem Flex Points!";
                $response .= UssdUtil::$GO_BACK . " Go Back\n";
                $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
                UssdUtil::replyUssd($response);
            }
        } else if ($requestPhrase[1] == 0) {
            $response = "END Thanks for using Flexpay Lipia Polepole";
            UssdUtil::replyUssd($response);
        } else if ($requestPhrase[1] > 2 || $requestPhrase[1] < 0) {
            $response = "CON Invalid Selection";
            $response .= UssdUtil::$GO_BACK . " Go Back\n";
            $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
            UssdUtil::replyUssd($response);
        }
    }


    /**
     * @param $userId
     * @param $phoneNumber
     * @param $sessionId
     * @param $requestPhrase
     */
    static function makePayment($userId, $phoneNumber, $sessionId, $requestPhrase)
    {
        $booking = UssdUtil::getBookings($userId);
        if (count($requestPhrase) == 1) {
            // Business logic for first level response
            if ($booking->isEmpty()) {
                $response = "END You don't have any booking\n";
                $response .= UssdUtil::$GO_BACK . " Go Back\n";
                $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
            } else {
                $response = "CON Select the booking to pay for:\n";
                $response .= self::create_payment_session($userId, $booking, $phoneNumber, $sessionId);
            }
            UssdUtil::replyUssd($response);
        } elseif (count($requestPhrase) == 2) {

            $payment_session_List = self::get_payment_session($sessionId);
            $selection = intval(UssdUtil::numberDigit($requestPhrase[1])) - 1;
            $stored_variable = unserialize($payment_session_List->payment_list);
            if (($selection < 0 || ($selection >= sizeof($stored_variable)))) {
                $response = "CON invalid selection !\n";
                $response .= UssdUtil::$GO_BACK . " Go Back\n";
                $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
                UssdUtil::replyUssd($response);
                return;
            }
            $paymentStep = PaymentSteps::where('session_id', $sessionId)->first();
            $paymentStep->payment_selection = $selection;
            $product_code = $stored_variable[$selection];
            $paymentStep->payment_reference = $product_code;
            $paymentStep->save();
            $balance = UssdUtil::getWalletBalance($userId);
            $refundBalance = UssdUtil::getWalletAvailableBalance($userId);
            //request Payment Method
            $response = "CON Payment method\n";
            $response .= "1.With M-PESA.\n";
            $response .= "2.From Wallet(Bal. KES " . number_format($balance) . ")\n";
            $response .= "3.From Refund(Bal. KES " . number_format($refundBalance) . ")\n";
            $response .= "4.Flex Coupon";
            UssdUtil::replyUssd($response);
        } elseif (count($requestPhrase) == 3) {
            $selection = UssdUtil::numberDigit($requestPhrase[2]);
            if ($selection < 1 || $selection > 4) {
                $response = "CON Selection '" . $requestPhrase[2] . "' invalid!\n";
                $response .= UssdUtil::$GO_BACK . " Go Back\n";
                $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
                UssdUtil::replyUssd($response);
                return;
            }
            if ($selection == 4) {
                $response = "CON Enter COUPON code\n";
            } else {
                $response = "CON Enter amount to pay\n";
            }
            UssdUtil::replyUssd($response);
        } elseif (count($requestPhrase) >= 4) {
            $paymentAmount = UssdUtil::numberDigit($requestPhrase[3]);
            if (intval($requestPhrase[2]) != 3 && !self::validateAmount($paymentAmount)) {
                return;
            }
            $paymentStep = PaymentSteps::where('session_id', $sessionId)->first();
            $paymentStep->payment_amount = $paymentAmount;
            $paymentStep->save();
            if ($requestPhrase[2] == 1) {
                //M-PESA
                if (count($requestPhrase) == 4) {
                    $response = "CON Select M-Pesa number!\n";
                    $response .= "1." . $phoneNumber . "\n";
                    $response .= "2.Use Another Number\n";
                    UssdUtil::replyUssd($response);
                } elseif (count($requestPhrase) == 5) {
                    if ($requestPhrase[4] == 1) {
                        try {
                            $response = "END Wait for M-PESA menu to complete!\n";
                            UssdUtil::replyUssd($response);
                        } finally {
                            $payment_session_List = self::get_payment_session($sessionId);
                            event(new PaymentEvent($payment_session_List));
                        }
                    } elseif ($requestPhrase[4] == 2) {
                        $response = "CON Enter M-Pesa number e.g 0700000000\n";
                        $response .= UssdUtil::$GO_BACK . " Go Back\n";
                        UssdUtil::replyUssd($response);
                    }
                } elseif (count($requestPhrase) == 6) {
                    $newPhoneNumber = UssdUtil::formatPhoneNo($requestPhrase[5]);
                    if (!is_numeric($newPhoneNumber) || (strlen($newPhoneNumber) > env('PHONE_NUMBER_LENGTH', 12))) {
                        $response = "CON Customer Phone Number '" . $requestPhrase[5] . "' is invalid!\n";
                        $response .= UssdUtil::$GO_BACK . " Go Back\n";
                        $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
                        UssdUtil::replyUssd($response);
                        return;
                    }
                    $phoneUtil = PhoneNumberUtil::getInstance();
                    $objectPhoneNumber = $phoneUtil->parse($newPhoneNumber, "KE");
                    $carrierMapper = PhoneNumberToCarrierMapper::getInstance();
                    $carrierProvider = $carrierMapper->getNameForNumber($objectPhoneNumber, "en");
                    if (strcasecmp($carrierProvider, 'Safaricom') != 0 && !UssdUtil::isValidNewNumber($newPhoneNumber)) {
                        $response = "CON Customer Phone Number '" . $requestPhrase[5] . "' Does not Support M-PESA!\n";
                        $response .= UssdUtil::$GO_BACK . " Go Back\n";
                        $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
                        UssdUtil::replyUssd($response);
                        return;
                    }
                    try {
                        $paymentStep = PaymentSteps::where('session_id', $sessionId)->first();
                        $paymentStep->customer_phone = $newPhoneNumber;
                        $paymentStep->save();
                        $response = "END Wait for M-PESA menu to complete!\n";
                        UssdUtil::replyUssd($response);
                    } finally {
                        $payment_session_List = self::get_payment_session($sessionId);
                        event(new PaymentEvent($payment_session_List));
                    }
                }
            } elseif ($requestPhrase[2] == 2) {
                $balance = UssdUtil::getWalletBalance($userId);
                if ($paymentAmount > $balance) {
                    $response = "CON Payment cannot be more than the balance!\n";
                    $response .= UssdUtil::$GO_BACK . " Go Back\n";
                    $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
                    UssdUtil::replyUssd($response);
                    return;
                }
                try {
                    $response = "END Please Wait for Flexpay confirmation \n";
                    UssdUtil::replyUssd($response);
                } finally {
                    $payment_session_List = self::get_payment_session($sessionId);
                    event(new DebitWalletEvent($payment_session_List));
                }
            } elseif ($requestPhrase[2] == 3) {
                $balance = UssdUtil::getWalletAvailableBalance($userId);
                if ($paymentAmount > $balance) {
                    $response = "CON Payment cannot be more than your refund balance!\n";
                    $response .= UssdUtil::$GO_BACK . " Go Back\n";
                    $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
                    UssdUtil::replyUssd($response);
                    return;
                }
                try {
                    $response = "END Please Wait for refund account confirmation \n";
                    UssdUtil::replyUssd($response);
                } finally {
                    $payment_session_List = self::get_payment_session($sessionId);
                    event(new DebitRefundWalletEvent($payment_session_List));
                }
            } elseif ($requestPhrase[2] == 4) {
                $coupon = UssdUtil::getCoupon($requestPhrase[3]);
                if (!$coupon) {
                    $response = "CON This coupon code is invalid!\n";
                    $response .= UssdUtil::$GO_BACK . " Go Back\n";
                    $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
                    UssdUtil::replyUssd($response);
                    return;
                } elseif (UssdUtil::hasConsumedCoupon($userId, $coupon->id)) {
                    $response = "CON This coupon cannot be used more than once!\n";
                    $response .= UssdUtil::$GO_BACK . " Go Back\n";
                    $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
                    UssdUtil::replyUssd($response);
                    return;
                }

                $paymentSession = self::get_payment_session($sessionId);
                $isValid = UssdUtil::getProcessedCoupon($coupon, $paymentSession->merchant_id, $userId);
                if (is_bool($isValid) === false) {
                    $response = "CON " . $isValid . "!\n";
                    $response .= UssdUtil::$GO_BACK . " Go Back\n";
                    $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
                    UssdUtil::replyUssd($response);
                    return;
                } else {
                    $response = "END Please Wait for Flexpay confirmation \n";
                    UssdUtil::replyUssd($response);
                    event(new CouponEvent($paymentSession, $coupon));
                }
            }
        }
    }


    static function get_payment_session($sessionId)
    {
        return PaymentSteps::where('session_id', $sessionId)->first();
    }

    static function create_payment_session($user_id, $booking, $phoneNumber, $sessionId)
    {
        UssdUtil::close_session($user_id);
        $arrayIndexed = UssdUtil::build_array($booking);
        $paymentSteps = new PaymentSteps();
        $paymentSteps->session_id = $sessionId;
        $paymentSteps->user_id = $user_id;
        $paymentSteps->customer_phone = $phoneNumber;
        $paymentSteps->payment_reference = 0;
        $paymentSteps->payment_status = 'initializePay';
        $paymentSteps->payment_list = serialize($arrayIndexed);
        $paymentSteps->save();
        return UssdUtil::reply_index($booking);
    }


    /**
     * @param $promoter
     * @param $phoneNumber
     * @param $sessionId
     * @param $requestPhrase
     * @internal param $userId
     * @internal param $receiptNumber
     * @internal param $sender
     */
    public static function validationAndConfirmation($promoter, $phoneNumber, $sessionId, $requestPhrase)
    {
        if (count($requestPhrase) == 1) {
            // Business logic for first level response
            $response = "CON Please enter the receipt number\n";
            UssdUtil::replyUssd($response);
        } else if (count($requestPhrase) == 2) {
            $receiptNumber = $requestPhrase[1];
            if (UssdUtil::isReceipt($receiptNumber)) {
                if (is_null(UssdUtil::promoterAllowed($receiptNumber, $promoter))) {
                    $response = "CON  You are not allowed to validate this customer!\n";
                    $response .= UssdUtil::$GO_BACK . " Go Back\n";
                    $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
                    UssdUtil::replyUssd($response);
                } else {
                    $response = "END Wait for SMS confirmation!\n";
                    UssdUtil::replyUssd($response);
                    event(new ValidateAndConfirmEvent($receiptNumber, $phoneNumber, $promoter->user_id));
                }
            } else {
                $response = "CON Invalid Booking Receipt!\n";
                $response .= UssdUtil::$GO_BACK . " Go Back\n";
                $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
                UssdUtil::replyUssd($response);
            }
        }
    }


    public static function redemptionMessage($phoneNumber, $points, $point_cash)
    {
        $promoter_data = [
            'recipients' => $phoneNumber,
            'template_id' => '42',
            'redeemed_points' => $points,
            'redeemed_cash' => $point_cash,
            'phone_no' => '0719725060'
        ];
        (new self)->guzzlePost(env('SMS_ENDPOINT'), $promoter_data);
    }

    protected static function merchantCredit()
    {
        return explode(',', env('CREDIT_LIST_MERCHANT'));
    }

    private static function personalGoal($userId, $phoneNumber, $sessionId, $requestPhrase)
    {
        if (count($requestPhrase) == 1) {
            $response = "CON Goal name \ne.g Rent,vacation,school fees\n";
            UssdUtil::replyUssd($response);
            Goal::query()->create([
                'session_id' => $sessionId,
                'user_id' => $userId,
                'customer_number' => $phoneNumber,
                'merchant_id' => 4,
                'customer_tag' => Uuid::uuid()
            ]);
        } else if (count($requestPhrase) == 2) {
            if (empty($requestPhrase[1])) {
                $response = "CON Goal name cannot be Empty !\n";
                $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
                UssdUtil::replyUssd($response);
                return;
            }

            // Business logic for first level response
            $response = "CON Amount to save. \ne.g 50000";
            UssdUtil::replyUssd($response);
            Goal::query()->where('session_id', $sessionId)->update([
                'product_description' => $requestPhrase[1],
                'product_id' => $requestPhrase[1],
            ]);
        } elseif (count($requestPhrase) == 3) {
            $productPrice = UssdUtil::numberDigit($requestPhrase[2]);
            if (!self::validateAmount($productPrice)) {
                return;
            }
            // Business logic for first level response
            $response = "CON Saving duration (set in days). \n e.g 60";
            UssdUtil::replyUssd($response);
            Goal::query()->where('session_id', $sessionId)->update([
                'product_price' => $productPrice
            ]);
        } else if (count($requestPhrase) == 4) {
            $paymentDays = UssdUtil::numberDigit($requestPhrase[3]);
            if (!self::validatePaymentDate($paymentDays, $requestPhrase[3])) {
                return;
            }
            // Business logic for first level response
            $response = "CON Your starting amount. \n e.g 500";
            UssdUtil::replyUssd($response);
            Goal::query()->where('session_id', $sessionId)->update([
                'product_payment_instalment' => $paymentDays
            ]);
        } else if (count($requestPhrase) == 5) {
            $productDeposit = UssdUtil::numberDigit($requestPhrase[4]);
            if (!self::validateAmount($productDeposit)) {
                return;
            }
            // Business logic for first level response
            $response = "END Please enter m-pesa pin to complete.\n";
            UssdUtil::replyUssd($response);
            Goal::query()->where('session_id', $sessionId)->update([
                'product_deposit' => $productDeposit
            ]);
            $goal = Goal::query()->where('session_id', $sessionId)->first();
            self::initiateGoal($goal);
        }
    }



    private static function processChama($userId, $phoneNumber, $sessionId, $requestPhrase)
    {
        $monthQuaterContribute = Carbon::now()->diffInMonths(Carbon::now()->endOfQuarter()) + 1;
        $semiAnnualContribute = Carbon::now()->diffInMonths(Carbon::parse(Carbon::now()->year . '-06-30 00:00:00')) + 1;
        $yearlyContribute = Carbon::now()->diffInMonths(Carbon::now()->endOfYear()) + 1;
        $deadlineDate = Carbon::now()->endOfYear();
        $multiplier = 1;
        if (count($requestPhrase) == 1) {
            $response = "CON Earn Upto 10% interest on Chama Savings\n";
            $response .= "1.Quaterly Chama.\n";
            $response .= "2.Half yearly Chama.\n";
            $response .= "3.Annual Chama\n";
            $response .= UssdUtil::$GO_BACK . " Go Back\n";
            UssdUtil::replyUssd($response);
            Goal::query()->create([
                'session_id' => $sessionId,
                'user_id' => $userId,
                'customer_number' => $phoneNumber,
                'customer_tag' => Uuid::uuid()
            ]);
        } else if (count($requestPhrase) == 2) {
            $selection = UssdUtil::numberDigit($requestPhrase[1]);
            if ($selection < 1 || $selection > 3) {
                $response = "CON The selected option is invalid!\n";
                $response .= UssdUtil::$GO_BACK . " Go Back\n";
                $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
                UssdUtil::replyUssd($response);
                return;
            }
            if ($selection == 1) {
                //show chama list
                $productList = UssdUtil::chamaProduct(env('CHAMA_MONTHLY'));
                Goal::query()->where('session_id', $sessionId)->update([
                    'product_description' => env('CHAMA_MONTHLY')
                ]);
            }
            if ($selection == 2) {
                //show chama list
                $productList = UssdUtil::chamaProduct(env('CHAMA_MONTHLY'));
                Goal::query()->where('session_id', $sessionId)->update([
                    'product_description' => env('CHAMA_MONTHLY')
                ]);
            } elseif ($selection == 3) {
                $productList = UssdUtil::chamaProduct(env('CHAMA_52WEEK'));
                Goal::query()->where('session_id', $sessionId)->update([
                    'product_description' => env('CHAMA_52WEEK')
                ]);
            }
            if (count($productList) != 0 && !empty($productList)) {
                // Business logic for first level response
                $response = "CON Select Chama\n";
                $response .= self::create_chama_product_session($productList, $sessionId);
                UssdUtil::replyUssd($response);
            } else {
                $response = "CON The Chama not valid this time!\n";
                $response .= UssdUtil::$GO_BACK . " Go Back\n";
                $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
                UssdUtil::replyUssd($response);
            }
        } else if ($requestPhrase[1] != 5) {
            if (count($requestPhrase) == 3) {
                $selection_item = (intval(UssdUtil::numberDigit($requestPhrase[2])) - 1);
                $serializedProduct = self::get_goal_session($sessionId);
                $stored_variable = unserialize($serializedProduct->product_list);
                if (($selection_item < 0 || ($selection_item >= sizeof($stored_variable)))) {
                    $response = "CON Invalid Selection !\n";
                    $response .= UssdUtil::$GO_BACK . " Go Back\n";
                    $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
                    UssdUtil::replyUssd($response);
                    return;
                }


                $response = "CON Enter the deposit amount\n";
                UssdUtil::replyUssd($response);
                $selection = UssdUtil::numberDigit($requestPhrase[1]);
                if ($selection == 1) {
                    $multiplier = $monthQuaterContribute;
                    $deadlineDate = Carbon::now()->endOfQuarter();
                } elseif ($selection == 2) {
                    $multiplier =  $semiAnnualContribute;
                    $deadlineDate = Carbon::parse(Carbon::now()->year . '-06-30 00:00:01');
                } else {
                    $multiplier =  1;
                    $deadlineDate = Carbon::now()->endOfYear();
                }
                $product_id = $stored_variable[$selection_item];
                $product = UssdUtil::getProductById($product_id);
                Goal::query()->where('session_id', $sessionId)->update([
                    'product_id' => $product_id,
                    'product_price' => ($product->flexpay_price * $multiplier),
                    'outlet_id' => $product->outlet_id,
                    'merchant_id' => $product->merchant_id,
                    'product_payment_instalment' => Carbon::now()->diffInDays(Carbon::parse($deadlineDate))
                ]);
            } else if (count($requestPhrase) == 4) {

                $productDeposit = UssdUtil::numberDigit($requestPhrase[3]);
                if (!self::validateAmount($productDeposit)) {
                    return;
                }
                $response = "END Please enter m-pesa pin to complete.\n";
                UssdUtil::replyUssd($response);
                Goal::query()->where('session_id', $sessionId)->update([
                    'product_deposit' => $productDeposit
                ]);
                $product = Goal::query()->where('session_id', $sessionId)->first();
                event(new CreateGoalEvent($product));
            }
        } else {
            self::processCheckOut($userId, $phoneNumber, $sessionId, $requestPhrase);
        }
    }

    static function create_chama_product_session($productList, $sessionId)
    {
        Goal::query()->where('session_id', $sessionId)->update([
            'product_list' => serialize(UssdUtil::build_product($productList))
        ]);
        return UssdUtil::reply_chama_product($productList);
    }

    static function get_goal_session($sessionId)
    {
        return Goal::where('session_id', $sessionId)->first();
    }

    static function processCheckOut($userId, $phoneNumber, $sessionId, $requestPhrase)
    {
        if (count($requestPhrase) == 2) {
            $chama = UssdUtil::chamaComplete($userId);
            $response = "CON Checkout Chamas\n";
            $response .= "0.Settle All \n";
            $response .= self::set_chama_product_session($chama, $sessionId);
            UssdUtil::replyUssd($response);
        } elseif (count($requestPhrase) > 2) {
            if ($requestPhrase[2] == 0) {
                self::processRefundSettleAll($userId, $phoneNumber, $sessionId, $requestPhrase);
            } else {
                self::processRefundSettleOne($userId, $phoneNumber, $sessionId, $requestPhrase);
            }
        }
    }

    public static function processRefundSettleAll($userId, $phoneNumber, $sessionId, $requestPhrase)
    {
        if (count($requestPhrase) == 3) {
            $response = "CON Do you want to settle all Chamas?\n";
            $response .= "0.No\n";
            $response .= "1.Yes\n";
            UssdUtil::replyUssd($response);
        } elseif (count($requestPhrase) == 4) {
            if ($requestPhrase[3] == 1) {
                $response = "END All Complete Chamas Settled.\n";
                $chamasBooking = UssdUtil::chamaComplete($userId);
                foreach ($chamasBooking as $booking) {
                    self::settleGoals($userId, $phoneNumber, $sessionId, $booking->booking_id);
                }
            } else {
                $response = "END Cancelled or Invalid option.\n";
            }
            UssdUtil::replyUssd($response);
        }
    }


    public static function processRefundSettleOne($userId, $phoneNumber, $sessionId, $requestPhrase)
    {
        if (count($requestPhrase) == 3) {
            $product_selection = (intval($requestPhrase[2]) - 1);
            $serializedProduct = self::get_goal_session($sessionId);
            $booking_id = unserialize($serializedProduct->product_list)[$product_selection];
            $booking = UssdUtil::getBookingById($booking_id);
            self::settleGoals($userId, $phoneNumber, $sessionId, $booking_id);
            if ($booking->booking_status !== 'closed') {
                $response = "END This Chama payment is incomplete.\n";
                $response .= "For help call 0719725060.\n";
                UssdUtil::replyUssd($response);
            } else {
                $response = "END Settled.\n";
                UssdUtil::replyUssd($response);
            }
        }
    }


    static function set_chama_product_session($bookingList, $sessionId)
    {
        Goal::query()->where('session_id', $sessionId)->update([
            'product_list' => serialize(UssdUtil::build_booking($bookingList))
        ]);
        return UssdUtil::reply_chama_complete($bookingList);
    }

    private static function create_merchant_session($merchantList, $sessionId)
    {
        Goal::query()->where('session_id', $sessionId)->update([
            'merchant_list' => serialize(UssdUtil::build_merchant($merchantList))
        ]);
        return UssdUtil::reply_merchant($merchantList);
    }

    private static function merchantTillProcess($userId, $phoneNumber, $sessionId, $requestPhrase)
    {
        if (count($requestPhrase) == 1) {
            $response = "CON Enter business code\n";
            UssdUtil::replyUssd($response);
            Goal::query()->create([
                'session_id' => $sessionId,
                'user_id' => $userId,
                'customer_number' => $phoneNumber,
                'customer_tag' => Uuid::uuid()
            ]);
        } elseif (count($requestPhrase) == 2) {
            $merchant = UssdUtil::getMerchantByTill($requestPhrase[1]);
            if (!$merchant) {
                $response = "CON Business code does not exist/invalid\n";
                $response .= UssdUtil::$GO_BACK . " Go Back\n";
                $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
            } else {
                Goal::query()->where('session_id', $sessionId)->update([
                    'merchant_id' => $merchant->id,
                ]);
                $response = "CON product name at '" . explode(' ', $merchant->merchant_name)[0] . "'\ne.g Fridge/Holiday/Fees\n";
            }
            UssdUtil::replyUssd($response);
        } else if (count($requestPhrase) == 3) {
            // Business logic for first level response
            if (empty($requestPhrase[2])) {
                $response = "CON The product name must be provided\n";
                $response .= UssdUtil::$GO_BACK . " Go Back\n";
                $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
                UssdUtil::replyUssd($response);
                return;
            }
            $response = "CON Enter product amount \n e.g 20,000.";
            UssdUtil::replyUssd($response);
            Goal::query()->where('session_id', $sessionId)->update([
                'product_description' => $requestPhrase[2],
                'product_id' => $requestPhrase[2],
            ]);
        } elseif (count($requestPhrase) == 4) {
            $productPrice = UssdUtil::numberDigit($requestPhrase[3]);
            if (!self::validateAmount($productPrice)) {
                return;
            }
            // Business logic for first level response
            $response = "CON Enter duration of payment(in days) \n e.g 60.";
            UssdUtil::replyUssd($response);
            Goal::query()->where('session_id', $sessionId)->update([
                'product_price' => $requestPhrase[3]
            ]);
        } else if (count($requestPhrase) == 5) {
            $productDays = UssdUtil::numberDigit($requestPhrase[4]);
            if (!self::validatePaymentDate($productDays, $requestPhrase[4])) {
                return;
            }
            // Business logic for first level response
            $response = "CON Enter product deposit \n e.g 500";
            UssdUtil::replyUssd($response);
            Goal::query()->where('session_id', $sessionId)->update([
                'product_payment_instalment' => $productDays
            ]);
        } else if (count($requestPhrase) == 6) {
            // Business logic for first level response
            $productDeposit = UssdUtil::numberDigit($requestPhrase[5]);
            if (!self::validateAmount($productDeposit)) {
                return;
            }
            $response = "END Please enter m-pesa pin to complete\n";
            UssdUtil::replyUssd($response);
            Goal::query()->where('session_id', $sessionId)->update([
                'product_deposit' => $productDeposit
            ]);
            $goal = Goal::query()->where('session_id', $sessionId)->first();
            self::initiateGoal($goal);
        }
    }

    private static function merchantGoal($userId, $phoneNumber, $sessionId, $requestPhrase)
    {
        if (count($requestPhrase) == 1) {
            Goal::query()->create([
                'session_id' => $sessionId,
                'user_id' => $userId,
                'customer_number' => $phoneNumber,
                'customer_tag' => Uuid::uuid()
            ]);
            $merchant = UssdUtil::getKapuMerchant();
            $response = "CON Choose  Merchant\n";
            $response .= self::create_merchant_session($merchant, $sessionId);
            UssdUtil::replyUssd($response);
        } elseif (count($requestPhrase) == 2) {
            $selection_item = (intval(UssdUtil::numberDigit($requestPhrase[1])) - 1);
            $serializedProduct = self::get_goal_session($sessionId);
            $stored_variable = unserialize($serializedProduct->merchant_list);
            if (($selection_item < 0 || ($selection_item >= sizeof($stored_variable)))) {
                $response = "CON Invalid selection !\n";
                $response .= UssdUtil::$GO_BACK . " Go Back\n";
                UssdUtil::replyUssd($response);
                return;
            }
            $merchant_id = $stored_variable[$selection_item];
            Goal::query()->where('session_id', $sessionId)->update([
                'merchant_id' => $merchant_id,
            ]);
            $response = "CON What do you want to buy? e.g Fridge";
            UssdUtil::replyUssd($response);
        } elseif (count($requestPhrase) == 3) {
            $product_name = $requestPhrase[2];
            Goal::query()->where('session_id', $sessionId)->update([
                'product_description' => $product_name,
                'product_id' => $product_name
            ]);
            $response = "CON Enter your budget";
            UssdUtil::replyUssd($response);
        } elseif (count($requestPhrase) == 4) {
            $productPrice = UssdUtil::numberDigit($requestPhrase[3]);
            if (!self::validateAmount($productPrice)) {
                return;
            }
            // Business logic for first level response
            $response = "CON Enter duration of payment(in days) \n e.g 60";
            UssdUtil::replyUssd($response);
            Goal::query()->where('session_id', $sessionId)->update([
                'product_price' => $productPrice
            ]);
        } else if (count($requestPhrase) == 5) {
            $paymentDays = UssdUtil::numberDigit($requestPhrase[4]);
            if (!self::validatePaymentDate($paymentDays, $requestPhrase[4])) {
                return;
            }
            // Business logic for first level response
            $response = "CON Enter initial deposit \n e.g 500";
            UssdUtil::replyUssd($response);
            Goal::query()->where('session_id', $sessionId)->update([
                'product_payment_instalment' => $paymentDays
            ]);
        } else if (count($requestPhrase) == 6) {
            $productDeposit = UssdUtil::numberDigit($requestPhrase[5]);
            if (!self::validateAmount($productDeposit)) {
                return;
            }
            // Business logic for first level response
            $response = "END Respond to m-mpesa prompt\n";
            UssdUtil::replyUssd($response);
            Goal::query()->where('session_id', $sessionId)->update([
                'product_deposit' => $productDeposit
            ]);
            $goal = Goal::query()->where('session_id', $sessionId)->first();
            self::initiateGoal($goal);
        }
    }

    private static function initiateGoal($goal)
    {
        try {
            $outlet_id = self::getOutletId($goal->merchant_id);
            $product = ProductSteps::query()->create([
                'session_id' => $goal->session_id,
                'user_id' => $goal->user_id,
                'promoter_id' => 1,
                'outlet_id' => (!isset($goal->outlet_id) ? $outlet_id : $goal->outlet_id),
                'merchant_id' => $goal->merchant_id,
                'product_name' => $goal->product_description,
                'product_price' => $goal->product_price,
                'product_deposit' => $goal->product_deposit,
                'product_payment_days' => $goal->product_payment_instalment,
                'customer_number' => $goal->customer_number,
                'customer_tag' => $goal->customer_tag,
                'created_at' => Carbon::now()->toDateTimeString(),
                'updated_at' => Carbon::now()->toDateTimeString(),
            ]);
            event(new CreateProductEvent($product));
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
        }
    }

    private static function settleGoals($userId, $phoneNumber, $sessionId, $bookingId)
    {
        $totalPaid = UssdUtil::getTotalPaid($bookingId);
        if ($totalPaid > 0 && !UssdUtil::settlementExist($bookingId)) {
            $booking = UssdUtil::getBookingById($bookingId);
            $goalSettled = GoalSettlement::query()->create([
                'session_id' => $sessionId,
                'user_id' => $userId,
                'customer_number' => $phoneNumber,
                'product_id' => $booking->product_id,
                'booking_id' => $bookingId,
                'booking_reference' => $booking->booking_reference,
                'booking_price' => $booking->booking_price,
                'amount_paid' => $totalPaid,
                'maturity_state' => self::getMaturityStatus($booking),
                'settlement_status' => 'unpaid',
                'settlement_tag' => Uuid::uuid()
            ]);
            if ($goalSettled) {
                self::closeBooking($booking, $totalPaid);
            }
        } else {
            Log::error('failed===' . $bookingId);
        }
    }

    private static function merchantRegstration($userId, $phoneNumber, $sessionId, $requestPhrase)
    {
        if (count($requestPhrase) == 1) {
            $response = "CON Enter shop name\n";
            UssdUtil::replyUssd($response);
        } elseif (count($requestPhrase) == 2) {
            $response = "CON Shop location e.g Westlands \n";
            UssdUtil::replyUssd($response);
        } elseif (count($requestPhrase) == 3) {
            $response = "CON Email address\n";
            UssdUtil::replyUssd($response);
        } elseif (count($requestPhrase) == 4) {
            $response = "CON Phone number \n";
            UssdUtil::replyUssd($response);
        } else {
            $name = $requestPhrase[1];
            $location = $requestPhrase[2];
            $email = $requestPhrase[3];
            $phoneNumber = $requestPhrase[4];
            $merchant = UssdUtil::createMerchant($email, '123456', $name, $phoneNumber, $location, '18');
            $response = "END Shop created successfully. Merchant code is " . $merchant->merchant_code . " \n";
            UssdUtil::replyUssd($response);
            event(new MerchantCreationEvent($merchant));
        }
    }


    private static function closeBooking($booking, $validationAmount)
    {
        DB::table('product_booking_receipt')
            ->where('booking_id', $booking->id)
            ->update([
                'receipt_status' => 'closed',
                'closed_by' => env('FLEXPAY_PROMOTER_ID', 1),
                'validated_at' => Carbon::now()
            ]);

        DB::table('product_booking')
            ->where('id', $booking->id)
            ->where('booking_status', 'closed')
            ->update(['validation_price' => $validationAmount]);
    }

    private static function getMaturityStatus($booking)
    {
        if ($booking->booking_status !== 'closed') {
            $days = Carbon::parse($booking->deadline_date)->diffInDays(Carbon::now(), false);
            if ($days >= 1) {
                return 'active';
            } else {
                return 'overdue';
            }
        } else {
            return 'complete';
        }
    }

    private static function getOutletId($merchantId)
    {
        if (intval($merchantId) == 4) {
            return 483;
        } elseif (self::hasOneOutlet($merchantId)) {
            return DB::table('lp_outlets')->where('merchant_id', $merchantId)->value('id');
        } else {
            return UssdUtil::getOutletIds($merchantId);
        }
    }

    private static function hasOneOutlet($merchantId)
    {
        return (DB::table('lp_outlets')->where('merchant_id', $merchantId)->count() == 1);
    }

    private static function validateAmount($amount): bool
    {
        if (!is_numeric($amount)) {
            $response = "CON The Amount '" . $amount . "'  MUST be a Number!\n";
            $response .= UssdUtil::$GO_BACK . " Go Back\n";
            $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
            UssdUtil::replyUssd($response);
            return false;
        }
        $maximum_price = env('MAX_PRICE', 1000000);
        $minimum_price = env('MIN_PRICE', 1);

        if (($amount > $maximum_price)) {
            $response = "CON The Amount " . $amount . " has exceeded maximum Allowed\n";
            $response .= UssdUtil::$GO_BACK . " Go Back\n";
            $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
            UssdUtil::replyUssd($response);
            return false;
        }
        if (($amount < $minimum_price)) {
            $response = "CON The Amount " . $amount . " is below Ksh. " . $minimum_price . "\n";
            $response .= UssdUtil::$GO_BACK . " Go Back\n";
            $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
            UssdUtil::replyUssd($response);
            return false;
        }
        return true;
    }


    private static function validatePaymentDate($paymentDays, $value): bool
    {
        if (!is_numeric($paymentDays) || $paymentDays < 1) {
            $response = "CON Duration of payment '" . $value . "' is invalid!\n";
            $response .= UssdUtil::$GO_BACK . " Go Back\n";
            $response .= UssdUtil::$GO_TO_MAIN_MENU . " Main Menu\n";
            UssdUtil::replyUssd($response);
            return false;
        }
        return true;
    }
}
