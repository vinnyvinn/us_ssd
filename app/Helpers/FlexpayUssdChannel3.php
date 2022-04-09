<?php
/**
 * Created by PhpStorm.
 * User: monongahela
 * Date: 19/03/2019
 * Time: 15:54
 */

namespace App\Helpers;

use App\HealthStep;
use App\PaymentSteps;
use App\SessionManager;
use Faker\Provider\Uuid;
use App\Events\PaymentEvent;
use App\Events\DebitWalletEvent;
use App\Events\CreateHealthEvent;
use App\Traits\DataTransferTrait;
use Illuminate\Support\Facades\Log;


class FlexpayUssdChannel3
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
        $text = $request->text;
        $phoneNumber = UssdUtil::formatPhoneNo($request->phoneNumber);
        $customer = UssdUtil::createOrUpdate($phoneNumber);
        if (empty($text)) {
            $response = "CON Welcome to MamaPrime (powered by Flexpay)\n";
            $response .= "1.Choose your preferred hospital\n";
            $response .= "2.Make payment\n";
            $response .= "3.Booking Balance\n";
            UssdUtil::replyUssd($response);

            HealthStep::query()->create([
                'session_id' => $sessionId,
                'user_id' => $customer->user_id,
                'customer_number' => $phoneNumber,
                'customer_tag' => Uuid::uuid()
            ]);
        } else {
            $requestPhrase = explode('*', $text);
            self::customerJourney($customer->user_id, $phoneNumber, $sessionId, $requestPhrase);
        }

    }


    static function customerJourney($userId, $phoneNumber, $sessionId, $requestPhrase)
    {
        if (count($requestPhrase) > 0) {
            if ($requestPhrase[0] == 1) {
                self::bookHealth($sessionId, $requestPhrase);
            } elseif ($requestPhrase[0] == 2) {
                self::makePayment($userId, $phoneNumber, $sessionId, $requestPhrase);
            } elseif ($requestPhrase[0] == 3) {
                self::processBalance($userId, $phoneNumber, $sessionId, $requestPhrase);
            }

        } else {
            $response = "END There was an error accessing USSD\n";
            UssdUtil::replyUssd($response);
        }
    }


    private static function bookHealth($sessionId, $requestPhrase)
    {
        if (count($requestPhrase) == 1) {
            // Business logic for first level response
            $response = "CON Hospital List.\n";
            $hospital = UssdUtil::hospitalList();
            $response .= self::create_health_session($hospital, $sessionId);
            UssdUtil::replyUssd($response);
        } else if (count($requestPhrase) == 2) {
            if (last($requestPhrase) == 98) {
                return;
            }

            // Business logic for first level response
            $merchant_selection = (intval($requestPhrase[1]) - 1);
            $merchantList = self::get_health_session($sessionId);
            $merchant_id = unserialize($merchantList->hospital_list)[$merchant_selection];
            $outletList = UssdUtil::outletList($merchant_id);
            $response = "CON Choose Hospital Branch.\n";
            $response .= self::create_outlet_session($outletList, $sessionId);
            UssdUtil::replyUssd($response);
            HealthStep::query()->where('session_id', $sessionId)->update([
                'hospital_id' => $merchant_id
            ]);
        } else if (count($requestPhrase) == 3) {
            if ($requestPhrase[1] == 98) {
                $merchant_selection = (intval($requestPhrase[2]) - 1);
                $merchantList = self::get_health_session($sessionId);
                $merchant_id = unserialize($merchantList->hospital_list)[$merchant_selection];
                $outletList = UssdUtil::outletList($merchant_id);
                $response = "CON Choose Hospital Branch.\n";
                $response .= self::create_outlet_session($outletList, $sessionId);
                UssdUtil::replyUssd($response);
                HealthStep::query()->where('session_id', $sessionId)->update([
                    'hospital_id' => $merchant_id
                ]);
            }
            // Business logic for first level response
            $outlet_selection = (intval($requestPhrase[2]) - 1);
            $outlet = self::get_health_session($sessionId);
            $outlet_id = unserialize($outlet->outlet_list)[$outlet_selection];
            $productList = UssdUtil::productList($outlet_id, $outlet->hospital_id);
            $response = "CON Select Package.\n";
            $response .= self::create_product_session($productList, $sessionId);
            UssdUtil::replyUssd($response);

            HealthStep::query()->where('session_id', $sessionId)->update([
                'outlet_id' => $outlet_id
            ]);

        } else if (count($requestPhrase) == 4) {
            if ($requestPhrase[1] == 98) {
                $outlet_selection = (intval($requestPhrase[3]) - 1);
                $outlet = self::get_health_session($sessionId);
                $outlet_id = unserialize($outlet->outlet_list)[$outlet_selection];
                $productList = UssdUtil::productList($outlet_id, $outlet->hospital_id);
                $response = "CON Select Package.\n";
                $response .= self::create_product_session($productList, $sessionId);
                UssdUtil::replyUssd($response);

                HealthStep::query()->where('session_id', $sessionId)->update([
                    'outlet_id' => $outlet_id
                ]);
            }
            // Business logic for first level response
            $response = "CON Enter the deposit Amount\n";
            UssdUtil::replyUssd($response);

            $product_selection = (intval($requestPhrase[3]) - 1);
            $outletList = self::get_health_session($sessionId);
            $product_id = unserialize($outletList->product_list)[$product_selection];
            $product = UssdUtil::getProductById($product_id);
            HealthStep::query()->where('session_id', $sessionId)->update([
                'product_id' => $product_id,
                'product_price' => $product->flexpay_price,
                'product_payment_instalment' => $product->product_booking_days
            ]);

        } else if (count($requestPhrase) == 5) {
            if ($requestPhrase[1] == 98) {
                $response = "CON Enter the deposit Amount\n";
                UssdUtil::replyUssd($response);

                $product_selection = (intval($requestPhrase[4]) - 1);
                $outletList = self::get_health_session($sessionId);
                $product_id = unserialize($outletList->product_list)[$product_selection];
                $product = UssdUtil::getProductById($product_id);
                HealthStep::query()->where('session_id', $sessionId)->update([
                    'product_id' => $product_id,
                    'product_price' => $product->flexpay_price,
                    'product_payment_instalment' => $product->product_booking_days
                ]);
            } else {
                // Business logic for first level response
                HealthStep::query()->where('session_id', $sessionId)->update([
                'product_deposit' => $requestPhrase[4]
            ]);
                $response = "END Please wait for M-PESA pin prompt\n";
                UssdUtil::replyUssd($response);

                $heath = HealthStep::query()->where('session_id', $sessionId)->first();
                event(new CreateHealthEvent($heath));
            }
        } else if (count($requestPhrase) == 6) {
              // Business logic for first level response
              HealthStep::query()->where('session_id', $sessionId)->update([
                'product_deposit' => $requestPhrase[5]
            ]);
            $response = "END Please wait for M-PESA pin prompt\n";
            UssdUtil::replyUssd($response);

            $heath = HealthStep::query()->where('session_id', $sessionId)->first();
            event(new CreateHealthEvent($heath));
        }


    }

    static function makePayment($userId, $phoneNumber, $sessionId, $requestPhrase)
    {
        $booking = UssdUtil::getBookingHealth($userId);
        if (count($requestPhrase) == 1) {
            // Business logic for first level response
            if ($booking->isEmpty()) {
                $response = "END You don't have MamaPrime Booking\n";
            } else {
                $response = "CON Select the booking to pay for:\n";
                $response .= self::create_payment_session($userId, $booking, $phoneNumber, $sessionId);
            }
            UssdUtil::replyUssd($response);
        } elseif (count($requestPhrase) == 2) {
            $balance = UssdUtil::getWalletBalance($userId);
            if ($balance > 0) {
                $response = "CON Wallet Balance Ksh. " . number_format($balance) . "\n";
                $response .= "Enter amount to pay\n";
            } else {
                $response = "CON Enter amount to pay\n";
            }

            UssdUtil::replyUssd($response);
            $paymentStep = PaymentSteps::where('session_id', $sessionId)->first();
            $paymentStep->payment_selection = (intval($requestPhrase[1]) - 1);
            $payment_session_List = self::get_payment_session($sessionId);
            $product_code = unserialize($payment_session_List->payment_list)[$paymentStep->payment_selection];
            $paymentStep->payment_reference = $product_code;
            $paymentStep->save();
        } elseif (count($requestPhrase) == 3) {
            $balance = UssdUtil::getWalletBalance($userId);
            if (!is_numeric($requestPhrase[2])) {
                $response = "END Invalid payment amount.\n";
                UssdUtil::replyUssd($response);
                return;
            }
            if ($requestPhrase[2] <= 0) {
                $response = "END Invalid payment amount.\n";
                UssdUtil::replyUssd($response);
                return;
            }
            if ($balance > 0) {
                $response = "CON 1.With M-PESA.\n";
                $response .= "2.From Wallet(KES " . number_format($balance) . ")\n";
                UssdUtil::replyUssd($response);
                $paymentStep = PaymentSteps::where('session_id', $sessionId)->first();
                $paymentStep->payment_amount = $requestPhrase[2];
                $paymentStep->save();
            } else {
                $response = "END Wait for M-PESA menu to complete!\n";
                UssdUtil::replyUssd($response);
                $paymentStep = PaymentSteps::where('session_id', $sessionId)->first();
                $paymentStep->payment_amount = $requestPhrase[2];
                $paymentStep->save();
                $payment_session_List = self::get_payment_session($sessionId);
                event(new PaymentEvent($payment_session_List));
            }

        } elseif (count($requestPhrase) == 4) {
            if ($requestPhrase[3] == 1) {
                try {
                    $response = "END Wait for M-PESA menu to complete!\n";
                    UssdUtil::replyUssd($response);
                } finally {
                    $payment_session_List = self::get_payment_session($sessionId);
                    event(new PaymentEvent($payment_session_List));
                }

            } elseif ($requestPhrase[3] == 2) {
                $balance = UssdUtil::getWalletBalance($userId);
                if ($requestPhrase[2] > $balance) {
                    $response = "END Payment cannot be more than the balance!\n";
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
            }
        }


    }

    public static function processBalance($userId, $phoneNumber, $sessionId, $requestPhrase)
    {
        $booking = UssdUtil::getBookingHealth($userId);
        if ($booking->count() == 0) {
            $response = "END You don't have an active booking!\n";
            UssdUtil::replyUssd($response);
            exit(1);
        }
        if (count($requestPhrase) == 1) {
            UssdUtil::close_session($userId);
            $arrayIndexed = UssdUtil::build_array($booking);
            $paymentSteps = new PaymentSteps();
            $paymentSteps->session_id = $sessionId;
            $paymentSteps->user_id = $userId;
            $paymentSteps->customer_phone = $phoneNumber;
            $paymentSteps->payment_reference = $booking->count() == 1 ? $booking[0]->booking_reference : 0;
            $paymentSteps->payment_status = 'initializePay';
            $paymentSteps->payment_list = serialize($arrayIndexed);
            $paymentSteps->save();
            $response = "CON Reply with option to Pay:\n";
            $response .= UssdUtil::balance_index($booking);
            UssdUtil::replyUssd($response);
        } elseif (count($requestPhrase) == 2) {
            $response = "CON Enter amount to pay\n";
            UssdUtil::replyUssd($response);
            $paymentStep = PaymentSteps::where('session_id', $sessionId)->first();
            $paymentStep->payment_selection = (intval($requestPhrase[1]) - 1);
            $payment_session_List = self::get_payment_session($sessionId);
            $product_code = unserialize($payment_session_List->payment_list)[$paymentStep->payment_selection];
            $paymentStep->payment_reference = $product_code;
            $paymentStep->save();
        } elseif (count($requestPhrase) == 3) {
            $response = "END Wait for M-PESA menu to complete!\n";
            UssdUtil::replyUssd($response);
            $paymentStep = PaymentSteps::where('session_id', $sessionId)->first();
            $paymentStep->payment_amount = $requestPhrase[2];
            $paymentStep->save();
            $payment_session_List = self::get_payment_session($sessionId);
            event(new PaymentEvent($payment_session_List));

        }


    }

    static function get_health_session($sessionId)
    {
        return HealthStep::where('session_id', $sessionId)->first();
    }

    static function get_payment_session($sessionId)
    {
        return PaymentSteps::where('session_id', $sessionId)->first();
    }

    static function create_health_session($hospitalList, $sessionId)
    {
        $merchantList = UssdUtil::build_merchant($hospitalList);
        HealthStep::query()->where('session_id', $sessionId)->update([
            'hospital_list' => serialize($merchantList)
        ]);
        return UssdUtil::reply_merchant($hospitalList);
    }

    static function create_outlet_session($outletList, $sessionId)
    {
        $outList = UssdUtil::build_outlet($outletList);
        HealthStep::query()->where('session_id', $sessionId)->update([
            'outlet_list' => serialize($outList)
        ]);
        return UssdUtil::reply_outlet($outletList);
    }

    static function create_product_session($packageList, $sessionId)
    {
        $outList = UssdUtil::build_Hospital($packageList);
        HealthStep::query()->where('session_id', $sessionId)->update([
            'product_list' => serialize($outList)
        ]);
        return UssdUtil::reply_product($packageList);
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

    public function setRequestPhrase($requestPhrase)
    {
        if (count($requestPhrase) > 1 && $requestPhrase[1] == 98) {
            unset($requestPhrase[1]);
            return array_values($requestPhrase);
        }
        return $requestPhrase;
    }


}
