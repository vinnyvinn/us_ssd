<?php
/**
 * Created by PhpStorm.
 * User: monongahela
 * Date: 19/03/2019
 * Time: 15:54
 */

namespace App\Helpers;

use App\BookingInsurance;
use App\Events\CreateInsuranceEvent;
use App\PaymentSteps;
use App\SessionManager;
use App\Traits\DataTransferTrait;
use Faker\Provider\Uuid;


class FlexpayUssdChannel2
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
        $promoter = UssdUtil::getPromoterByPhone($phoneNumber);
        $customer = UssdUtil::createOrUpdate($phoneNumber);
        if ($promoter) {
            if (empty($text)) {
                $response = "CON Welcome to Flexure\n";
                $response .= "1.Insure Flexpay product\n";
                $response .= "2.Add Insurable product\n";;
                UssdUtil::replyUssd($response);
            } else {
                $requestPhrase = explode('*', $text);
                self::agentJourney($promoter, $phoneNumber, $sessionId, $requestPhrase);
            }
        } elseif (isset($customer) && !is_null($customer->first_name)) {
            if (empty($text)) {
                $response = "CON Welcome to Flexure\n";
                $booking = UssdUtil::getBookingInsure($customer->user_id);
                if ($booking->isEmpty()) {
                    $response = "END You don't have any booking.Visit the customer care or sales person of the outlet to Assist you.\n";
                } else {
                    $response .= "Select the Product to Insure\n";
                    $response .= self::create_payment_session($customer->user_id, $booking, $phoneNumber, $sessionId);
                }
                UssdUtil::replyUssd($response);
            } else {
                $requestPhrase = explode('*', $text);
                self::customerJourney($customer->user_id, $phoneNumber, $sessionId, $requestPhrase);
            }
        }
    }

    /**
     * @param $promoter
     * @param $phoneNumber
     * @param $sessionId
     * @param $requestPhrase
     */
    static function agentJourney($promoter, $phoneNumber, $sessionId, $requestPhrase)
    {
        if (count($requestPhrase) > 0) {
            if ($requestPhrase[0] == 1) {
                self::insureAgentFlexProduct($promoter->user_id, $phoneNumber, $sessionId, $requestPhrase);
            } elseif ($requestPhrase[0] == 2) {
                self::insureAgentNoneFlexProduct($promoter->user_id, $phoneNumber, $sessionId, $requestPhrase);
            }
        } else {
            $response = "END There was an error accessing USSD\n";
            UssdUtil::replyUssd($response);
        }
    }

    static function customerJourney($userId, $phoneNumber, $sessionId, $requestPhrase)
    {
        if (count($requestPhrase) > 0) {
            self::flexInsure($userId, $phoneNumber, $sessionId, $requestPhrase);
        } else {
            $response = "END There was an error accessing USSD\n";
            UssdUtil::replyUssd($response);
        }
    }


    static function insureAgentNoneFlexProduct($promoterId, $phoneNumber, $sessionId, $requestPhrase)
    {

        if (count($requestPhrase) == 1) {
            // Business logic for first level response
            $response = "CON Enter product name/code";
            UssdUtil::replyUssd($response);
        } else if (count($requestPhrase) == 2) {
            // Business logic for first level response
            $response = "CON Enter product Cost";
            UssdUtil::replyUssd($response);
            $bookingInsurance = new BookingInsurance();
            $bookingInsurance->product_name = "Cover for " . $requestPhrase[1];
            $bookingInsurance->session_id = $sessionId;
            $bookingInsurance->user_id = 0;
            $bookingInsurance->promoter_id = $promoterId;
            $bookingInsurance->promoter_phone = $phoneNumber;
            $bookingInsurance->outlet_name = "";
            $bookingInsurance->merchant_name = "";
            $bookingInsurance->customer_tag = Uuid::uuid();
            $bookingInsurance->product_serial = "00000";
            $bookingInsurance->save();

        } else if (count($requestPhrase) == 3) {
            // Business logic for first level response
            $response = "CON Enter customer number\n e.g 0711000000";
            UssdUtil::replyUssd($response);
            $bookingInsurance = BookingInsurance::where('session_id', $sessionId)->first();
            $bookingInsurance->product_cost = $requestPhrase[2];
            $bookingInsurance->save();

        } else if (count($requestPhrase) == 4) {
            //call insurance Validator
            $bookingInsurance = BookingInsurance::where('session_id', $sessionId)->first();

            $bookingInsurance->customer_number = UssdUtil::formatPhoneNo($requestPhrase[3]);
            $customer = UssdUtil::createOrUpdate($bookingInsurance->customer_number);
            $bookingInsurance->user_id = $customer->user_id;
            $bookingInsurance->save();

            $data = UssdUtil::buildData($bookingInsurance, $customer);
            dd($data);
            $insuranceFeedback = (new self)->guzzlePostJson(env('BROKER_INSURANCE_URL'), $data);
            $premium = json_decode($insuranceFeedback);
            dd($premium);

            // Business logic for first level response
            $response = "CON Cover Premium is Ksh " . ceil($premium->data->premiums) . ".\nEnter Insurance Deposit";
            UssdUtil::replyUssd($response);
            $bookingInsurance->product_proposed_premium = ceil($premium->data->premiums);
            $bookingInsurance->product_proposed_premium_days = $premium->data->period;
            $bookingInsurance->save();


        } else if (count($requestPhrase) == 5) {
            $response = "END Please ask the customer to input M-PESA Pin";
            UssdUtil::replyUssd($response);

            $bookingInsurance = BookingInsurance::where('session_id', $sessionId)->first();
            $bookingInsurance->product_premium_deposit = $requestPhrase[4];
            $bookingInsurance->save();
            event(new CreateInsuranceEvent($bookingInsurance));

        }

    }

    private static function insureAgentFlexProduct($userId, $phoneNumber, $sessionId, $requestPhrase)
    {
        $customerId = 0;
        if (sizeof($requestPhrase) >= 2) {
            $customerId = optional(UssdUtil::createOrUpdate(UssdUtil::formatPhoneNo($requestPhrase[1])))->user_id;
        }

        if (count($requestPhrase) == 1) {
            $response = "CON Enter Customer's phone e.g 07220000000";
            UssdUtil::replyUssd($response);
        } elseif (count($requestPhrase) == 2) {
            $booking = UssdUtil::getBookingInsure($customerId);
            if ($booking->isEmpty()) {
                $response = "END The customer does not have an existing booking.\n";
            } else {
                $response = "CON Select the Product to Insure\n";
                $response .= self::create_payment_session($customerId, $booking, $phoneNumber, $sessionId);
            }
            UssdUtil::replyUssd($response);
        } elseif (count($requestPhrase) == 3) {

            $paymentStep = PaymentSteps::where('session_id', $sessionId)->first();
            $paymentStep->payment_selection = (intval($requestPhrase[2]) - 1);
            $payment_session_List = self::get_payment_session($sessionId);
            $product_code = unserialize($payment_session_List->payment_list)[$paymentStep->payment_selection];
            $paymentStep->payment_reference = $product_code;
            $paymentStep->save();

            $booking = UssdUtil::getBookingByRef($product_code);
            $product = UssdUtil::getProductById($booking->product_id);
            $customer = UssdUtil::getCustomer($customerId);
            //Store Product
            self::storeProduct($sessionId, $product, $userId, $phoneNumber, $customer);
            $bookingInsurance = BookingInsurance::where('session_id', $sessionId)->first();
            $customer = UssdUtil::getCustomer($customerId);
            $data = UssdUtil::buildData($bookingInsurance, $customer);
            // dd($bookingInsurance);
            $insuranceFeedback = (new self)->guzzlePostJson(env('BROKER_INSURANCE_URL'), $data);
            $premium = json_decode($insuranceFeedback);
            // Business logic for first level response
            $response = "CON Cover Premium is Ksh " . ceil($premium->data->premiums) . ".Please Enter Insurance Deposit";
            UssdUtil::replyUssd($response);
            $bookingInsurance->product_proposed_premium = ceil($premium->data->premiums);
            $bookingInsurance->product_proposed_premium_days = $premium->data->period;
            $bookingInsurance->save();
        } elseif (count($requestPhrase) == 4) {
            $response = "END Please input or Ask the customer to input  M-PESA Pin";
            UssdUtil::replyUssd($response);
            $bookingInsurance = BookingInsurance::where('session_id', $sessionId)->first();
            $bookingInsurance->product_premium_deposit = $requestPhrase[3];
            $bookingInsurance->save();
            event(new CreateInsuranceEvent($bookingInsurance));
        }


    }

    static function flexInsure($userId, $phoneNumber, $sessionId, $requestPhrase)
    {
        $customer = UssdUtil::getCustomer($userId);
       if (count($requestPhrase) == 1) {

            $paymentStep = PaymentSteps::where('session_id', $sessionId)->first();
            $paymentStep->payment_selection = (intval($requestPhrase[0]) - 1);
            $payment_session_List = self::get_payment_session($sessionId);
            $product_code = unserialize($payment_session_List->payment_list)[$paymentStep->payment_selection];
            $paymentStep->payment_reference = $product_code;
            $paymentStep->save();

            $booking = UssdUtil::getBookingByRef($product_code);
            $product = UssdUtil::getProductById($booking->product_id);
            $promoter = UssdUtil::getPromoter(env('BROKER_DEFAULT_PROMOTER'));
            $promoterPhone = $promoter ? $promoter->phone_number : $phoneNumber;
            self::storeProduct($sessionId, $product, env('BROKER_DEFAULT_PROMOTER'), $promoterPhone, $customer);

            $bookingInsurance = BookingInsurance::where('session_id', $sessionId)->first();
            $data = UssdUtil::buildData($bookingInsurance, $customer);
            // dd($bookingInsurance);
            $insuranceFeedback = (new self)->guzzlePostJson(env('BROKER_INSURANCE_URL'), $data);
            $premium = json_decode($insuranceFeedback);
            // Business logic for first level response
            $response = "CON Cover Premium is Ksh " . ceil($premium->data->premiums) . ".Please Enter Insurance Deposit";
            UssdUtil::replyUssd($response);
            $bookingInsurance->product_proposed_premium = ceil($premium->data->premiums);
            $bookingInsurance->product_proposed_premium_days = $premium->data->period;
            $bookingInsurance->save();
        } elseif (count($requestPhrase) == 2) {
            $response = "END Please input or Ask the customer to input  M-PESA Pin";
            UssdUtil::replyUssd($response);
            $bookingInsurance = BookingInsurance::where('session_id', $sessionId)->first();
            $bookingInsurance->product_premium_deposit = $requestPhrase[1];
            $bookingInsurance->save();
            event(new CreateInsuranceEvent($bookingInsurance));
        }


    }

    static function storeProduct($sessionId, $product, $promoter_id, $promoterPhone, $customer)
    {
        $bookingInsurance = new BookingInsurance();
        $bookingInsurance->product_name = "Insurance Cover for " . $product->product_name;
        $bookingInsurance->session_id = $sessionId;
        $bookingInsurance->user_id = 0;
        $bookingInsurance->promoter_id = $promoter_id;
        $bookingInsurance->promoter_phone = $promoterPhone;
        $bookingInsurance->outlet_name = "";
        $bookingInsurance->merchant_name = "";
        $bookingInsurance->product_serial = "00000";
        $bookingInsurance->customer_tag = Uuid::uuid();
        $bookingInsurance->product_cost = $product->flexpay_price;
        $bookingInsurance->user_id = $customer->user_id;
        $bookingInsurance->customer_number = ($promoter_id != $customer->user_id) ? $customer->phone_number_1 : $promoterPhone;
        $bookingInsurance->save();
        return $bookingInsurance;
    }

    static function get_payment_session($sessionId)
    {
        return PaymentSteps::where('session_id', $sessionId)->where('session_status', 'open')->first();
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


}
