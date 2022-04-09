<?php

/**
 * Created by PhpStorm.
 * User: mosesgathecha
 * Date: 20/03/2019
 * Time: 15:45
 */

namespace App\Helpers;


use App\Coupon;
use Carbon\Carbon;
use App\CouponUtil;
use Illuminate\Support\Str;
use App\Events\OpenWalletEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UssdUtil
{

    static $GO_BACK = "98";

    static $GO_TO_MAIN_MENU = "99";

    public static function getOutlet($outlet_id)
    {
        $outlet = DB::table('lp_outlets')->where('id', $outlet_id)->first();
        return $outlet;
    }

    public static function getCustomerCare()
    {
        $phone = DB::table('lp_customer_care')->first();
        if (!is_null($phone)) {
            return $phone->phone_no;
        }
    }

    public static function getLoanBooking($user_id)
    {
        $bookings = DB::table('lp_products')->join('product_booking', 'product_booking.product_id', '=', 'lp_products.id')->where('product_booking.user_id', $user_id)->where('booking_status', 'open')->where('booking_on_credit', '1')->orderBy('product_booking.id', 'DESC')->limit(5)->get();
        return $bookings;
    }

    public static function getBookings($user_id)
    {
        $bookings = DB::table('lp_products')
            ->join('product_booking', 'product_booking.product_id', '=', 'lp_products.id')
            ->where('product_booking.user_id', $user_id)
            ->where('booking_status', 'open')
            ->whereNull('product_booking.deleted_at')
            ->orderBy('product_booking.id', 'DESC')
            ->limit(5)
            ->get();
        return $bookings;
    }

    public static function getCompleteBookings($user_id)
    {
        $bookings = DB::table('lp_products')->join('product_booking', 'product_booking.product_id', '=', 'lp_products.id')->where('product_booking.user_id', $user_id)->where('booking_status', 'closed')->orderBy('product_booking.id', 'DESC')->limit(5)->get();
        return $bookings;
    }

    public static function getBookingHealth($user_id)
    {
        $list = explode(',', self::merchantHospital());
        $bookings = DB::table('lp_products')
            ->join('product_booking', 'product_booking.product_id', '=', 'lp_products.id')
            ->where('product_booking.user_id', $user_id)
            ->whereIn('product_booking.merchant_id', $list)
            ->where('booking_status', 'open')
            ->whereNull('product_booking.deleted_at')
            ->orderBy('product_booking.id', 'DESC')
            ->limit(5)->get();
        return $bookings;
    }

    public static function getBookingInsure($user_id)
    {
        $bookings = DB::table('lp_products')
            ->join('product_booking', 'product_booking.product_id', '=', 'lp_products.id')
            ->where('product_booking.merchant_id', '<>', env('BROKER_INSURANCE_ID'))
            ->where('product_booking.user_id', $user_id)
            ->where('booking_status', 'closed')
            ->whereNull('product_booking.deleted_at')
            ->orderBy('product_booking.id', 'DESC')
            ->limit(7)
            ->get();
        return $bookings;
    }

    public static function getProduct($product_code)
    {
        $bookings = DB::table('lp_products')->where('product_code', $product_code)->first();
        return $bookings;
    }

    public static function hasLoanProduct($userId)
    {
        return DB::table('product_booking')->where('user_id', $userId)->where('booking_on_credit', '1')->exists();
    }

    public static function hasCompleteBooking($userId)
    {
        return DB::table('product_booking')->where('user_id', $userId)->where('receipt_status', 'open')->exists();
    }


    public static function getBookingByRef($booking_reference)
    {
        $bookings = DB::table('product_booking')->where('product_booking.booking_reference', $booking_reference)->first();
        return $bookings;
    }

    public static function getBookingById($booking_id)
    {
        $booking = DB::table('product_booking')->where('product_booking.id', $booking_id)->first();
        return $booking;
    }

    public static function getBookingWithProduct($booking_reference)
    {
        $bookings = DB::table('product_booking')->join('lp_products', 'lp_products.id', '=', 'product_booking.product_id')->where('product_booking.booking_reference', $booking_reference)->first();
        return $bookings;
    }

    public static function getProductById($productId)
    {
        $product = DB::table('lp_products')->find($productId);
        return $product;
    }

    public static function createOrUpdate($phoneNumber, $email = '', $firstName = 'Customer', $lastName = 'Customer')
    {
        //Promoter can make mistake and dial wrong number
        $phoneNo = self::formatPhoneNo($phoneNumber);
        $customer = DB::table('lp_customers')->join('lp_users', 'lp_users.id', '=', 'lp_customers.user_id')->where('phone_number_1', $phoneNumber)->where('lp_users.is_verified', 1)->first();
        if (is_null($customer)) {
            $customer = DB::table('lp_customers')->where('phone_number_1', $phoneNumber)->first();
            if (!is_null($customer)) {
                DB::table('lp_users')->where('id', $customer->user_id)->update(['is_verified' => 1]);
            } else if (is_null($customer)) {
                $user_id = DB::table('lp_users')->insertGetId(['user_type' => 1, 'is_verified' => 1, 'password' => Hash::make($phoneNo), 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()]);
                DB::table('lp_customers')->insert(['first_name' => $firstName, 'last_name' => $lastName, 'phone_number_1' => $phoneNo, 'user_id' => $user_id, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()]);
                $customer = DB::table('lp_customers')->join('lp_users', 'lp_users.id', '=', 'lp_customers.user_id')->where('phone_number_1', $phoneNumber)->where('lp_users.is_verified', 1)->first();
                event(new OpenWalletEvent($user_id));
            } else {
                event(new OpenWalletEvent($customer->user_id));
            }
        }
        return $customer;
    }

    public static function getWalletBalance($user_id)
    {
        self::resetWallet($user_id);
        $balance = DB::table('lp_wallet_balance')->where('user_id', $user_id)->first();
        $amount_balance = $balance ? ($balance->total_credit - $balance->total_debit) : 0;
        return $amount_balance;
    }

    public static function getWalletAvailableBalance($user_id)
    {
        $total_credit = DB::table('lp_wallet_credit')->where('user_id', $user_id)->where('account_type', 'refund')->sum('amount');
        $total_debit = DB::table('lp_wallet_debit')->where('user_id', $user_id)->where('account_type', 'refund')->sum('amount');
        return ($total_credit - $total_debit);
    }

    public static function getSuperWalletBalance($user_id)
    {
        self::resetSuperWallet($user_id);
        $balance = DB::table('lp_super_wallet_balance')->where('user_id', $user_id)->first();
        $amount_balance = $balance ? ($balance->total_credit - $balance->total_debit) : 0;
        return $amount_balance;
    }

    public static function getCustomer($user_id)
    {

        return DB::table('lp_customers')->join('lp_users', 'lp_users.id', '=', 'lp_customers.user_id')->where('user_id', $user_id)->first();
    }

    public static function getPromoterByPhone($phone)
    {
        return DB::table('lp_promoters')
            ->where('phone_number', $phone)
            ->where('status', 1)
            ->first();
    }

    public static function getPromoter($user_id)
    {
        return DB::table('lp_promoters')->where('user_id', $user_id)->first();
    }

    public static function isPromoter($user_id)
    {
        if (intval($user_id) == 379) {
            return true;
        } else {
            return DB::table('lp_promoters')->where('user_id', $user_id)->exists();
        }
    }

    public static function getMerchant($outlet_id)
    {
        return DB::table('lp_merchants')->where('id', $outlet_id)->first();
    }

    static function close_session($userId)
    {
        DB::table('lp_payment_steps')->where('user_id', $userId)->update(array('session_status' => 'closed'));
    }

    public static function formatPhoneNo($phone_no)
    {
        $phoneNumber = self::formatPhoneNumber($phone_no);
        return preg_replace('/\D/', '', $phoneNumber);
    }


    static function reply_index($booking)
    {
        $message = "";
        for ($i = 0; $i < count($booking); $i++) {
            $paidAmount = self::getTotalPaid($booking[$i]->id);
            $product = explode(' ', $booking[$i]->product_name);
            $product_name = (sizeof($product) >= 2 ? $product[0] . " " . $product[1] : $product[0]);
            $message .= ($i + 1) . "." . $product_name . ' (bal,' . ($booking[$i]->booking_price - $paidAmount) . ')' . PHP_EOL;
        }
        return $message;
    }


    static function build_array($booking)
    {
        $message = array();
        foreach ($booking as $book) {
            $message[] = $book->booking_reference;
        }
        return $message;
    }

    static function balance_index($booking)
    {
        $message = "";
        for ($i = 0; $i < count($booking); $i++) {
            $paidAmount = self::getTotalPaid($booking[$i]->id);
            $message .= ($i + 1) . "." . $booking[$i]->product_name . '(-' . ($booking[$i]->booking_price - $paidAmount) . ')' . PHP_EOL;
        }
        return $message;
    }

    static function canLoop($mixed)
    {
        return is_array($mixed) && $mixed instanceof \Traversable ? true : false;
    }

    public static function getTotalPaid($bookingId)
    {
        $totalPayment = DB::table('product_booking_payments')->where('booking_id', $bookingId)->sum('payment_amount');
        return $totalPayment;
    }

    public static function isReceipt($receiptNumber)
    {
        return DB::table('product_booking_receipt')->where('product_booking_receipt.receipt_no', $receiptNumber)->where('receipt_status', 'open')->exists();
    }

    public static function promoterAllowed($receiptNumber, $promoter)
    {
        $merchantIdOutlet = DB::table('lp_outlets')->where('id', $promoter->outlet_id)->value('merchant_id');
        $is_allowed = DB::table('product_booking_receipt')->where('receipt_no', $receiptNumber)->where('merchant_id', $merchantIdOutlet)->first();
        return $is_allowed;
        // return DB::table('product_booking_receipt')->where('product_booking_receipt.receipt_no', $receiptNumber)->where('merchant_id', $merchantIdOutlet)->first();
    }

    public static function validateReceipt($receiptNumber)
    {
        $detail = DB::table('lp_products')
            ->join('product_booking', 'lp_products.id', '=', 'product_booking.product_id')
            ->join('lp_customers', 'product_booking.user_id', '=', 'lp_customers.user_id')
            ->join('product_booking_receipt', 'product_booking.id', '=', 'product_booking_receipt.booking_id')
            ->select(
                'product_booking_receipt.receipt_status as receipt_status',
                'lp_customers.first_name as first_name',
                'lp_customers.phone_number_1 as phone_number_1',
                'lp_products.flexpay_price as flexpay_price',
                'lp_products.product_name as product_name',
                'lp_products.product_code as product_code',
                'product_booking.id as booking_id',
                'product_booking.booking_price as booking_price',
                'product_booking_receipt.receipt_no as receipt_code'
            )
            ->where('product_booking_receipt.receipt_no', $receiptNumber)
            ->where('receipt_status', 'open')
            ->first();
        return $detail;
    }


    static function replyUssd($response)
    {
        static::respondOK($response);
    }

    /**
     * Respond 200 OK with an optional
     * This is used to return an acknowledgement response indicating that the request has been accepted and then the script can continue processing
     *
     * @param null $text
     */
    static public function respondOK($text = null)
    {
        // check if fastcgi_finish_request is callable
        if (is_callable('fastcgi_finish_request')) {
            if ($text !== null) {
                echo $text;
            }
            /*
             * http://stackoverflow.com/a/38918192
             * This works in Nginx but the next approach not
             */
            session_write_close();
            fastcgi_finish_request();
            return;
        }

        ignore_user_abort(true);

        ob_start();

        if ($text !== null) {
            echo $text;
        }

        $serverProtocol = filter_input(INPUT_SERVER, 'SERVER_PROTOCOL', FILTER_SANITIZE_STRING);
        header($serverProtocol . ' 200 OK');
        // Disable compression (in case content length is compressed).
        header('Content-Encoding: none');
        header('Content-Length: ' . ob_get_length());

        // Close the connection.
        header('Connection: close');

        ob_end_flush();
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }

    static function resetWallet($user_id)
    {
        $total_credit = DB::table('lp_wallet_credit')->where('user_id', $user_id)->where('account_type', 'booking')->sum('amount');
        $total_debit = DB::table('lp_wallet_debit')->where('user_id', $user_id)->where('account_type', 'booking')->sum('amount');
        DB::table('lp_wallet_balance')
            ->where('user_id', $user_id)
            ->update(['total_credit' => $total_credit, 'total_debit' => $total_debit]);
    }


    static function buildData($insuranceSteps, $customer)
    {

        $dataPush = array(
            'merchant_name' => $insuranceSteps->merchant_name,
            'merchant_outlet' => $insuranceSteps->outlet_name,
            'product_name' => $insuranceSteps->product_name,
            'product_serial' => $insuranceSteps->product_serial,
            'booking_code' => $insuranceSteps->product_serial,
            'booking_value' => $insuranceSteps->product_cost,
            'checkout_receipt_no' => $insuranceSteps->receipt_no,
            'date_completed' => date('Y-m-d H:i:s'),
            'date_picked' => date('Y-m-d H:i:s'),
            'customer' => array(
                'customer_name1' => $customer->first_name,
                'customer_name2' => $customer->last_name,
                'customer_phone' => $customer->phone_number_1 ? $customer->phone_number_1 : $insuranceSteps->customer_number,
                'customer_email' => $customer->email ? $customer->email : $insuranceSteps->customer_email,

            )

        );
        return json_encode($dataPush);
    }

    static function resetSuperWallet($user_id)
    {
        $total_credit = DB::table('lp_super_wallet_credit')->where('user_id', $user_id)->sum('amount');
        $total_debit = DB::table('lp_super_wallet_debit')->where('user_id', $user_id)->sum('amount');

        DB::table('lp_super_wallet_balance')
            ->where('user_id', $user_id)
            ->update(['total_credit' => $total_credit, 'total_debit' => $total_debit]);
    }

    static function buildKwetuData($customer, $booking)
    {
        $outlet = self::getOutlet($booking->outlet_id);
        $merchant = self::getMerchant($outlet->merchant_id);
        $amount = self::getTotalPaid($booking->id);
        $requestBody = [
            'booking_id' => $booking->id,
            'booking_code' => $booking->booking_reference,
            'booking_amount' => $booking->booking_price,
            'product_name' => $booking->product_name,
            'booking_date' => Carbon::parse($booking->created_at)->toDateString(),
            'merchant' => $merchant->merchant_name,
            'merchant_outlet' => $outlet->outlet_name,
            'customer' => [
                'customer_id' => $customer->user_id,
                'customer_name1' => $customer->first_name,
                'customer_name2' => $customer->last_name,
                'customer_national_id' => $customer->id_number,
                'customer_phone' => $customer->phone_number_1

            ],
            'payment' => [
                'amount' => $amount,
                'business_number' => '700164',
            ]

        ];
        UssdUtil::createLoan($customer->user_id, $booking->id, $customer->phone_number_1, $booking->booking_price);
        return json_encode($requestBody);
    }

    public static function createLoan($customer_id, $booking_id, $phone_number, $loan_amount)
    {
        $customerLoan = DB::table('lp_loan_session')->where('booking_id', $booking_id)->first();
        if ($customerLoan) {
            return $customerLoan;
        } else {
            DB::table('lp_loan_session')->insert([
                'customer_id' => $customer_id,
                'booking_id' => $booking_id,
                'phone_number' => $phone_number,
                'loan_amount' => $loan_amount
            ]);
            $customerLoan = DB::table('lp_loan_session')->where('booking_id', $booking_id)->first();
            return $customerLoan;
        }
    }

    //Hospital List
    static function reply_merchant($merchantList)
    {
        $message = "";
        for ($i = 0; $i < count($merchantList); $i++) {
            $message .= ($i + 1) . "." . (self::contains($merchantList[$i]->merchant_name, 'Flexpay') ? 'Personal goal' : $merchantList[$i]->merchant_name) . PHP_EOL;
        }
        return $message;
    }

    static function contains($needle, $haystack)
    {
        return strpos($haystack, $needle) !== false;
    }

    //Product List
    static function reply_chama_product($productList)
    {
        $message = "";
        for ($i = 0; $i < count($productList); $i++) {
            $message .= ($i + 1) . "." . $productList[$i]->product_name . PHP_EOL;
        }
        return $message;
    }

    static function build_merchant($merchantList)
    {
        $message = [];
        foreach ($merchantList as $item) {
            $message[] = $item->id;
        }
        return $message;
    }

    public static function hospitalList()
    {
        $list = explode(',', self::merchantHospital());

        return DB::table('lp_merchants')->whereIn('id', $list)->get();
    }

    //Outlet List
    static function reply_outlet($outletList)
    {
        $message = "";
        for ($i = 0; $i < count($outletList); $i++) {
            $message .= ($i + 1) . "." . $outletList[$i]->outlet_name . PHP_EOL;
        }
        return $message;
    }


    static function build_outlet($outletList)
    {
        $message = [];
        foreach ($outletList as $item) {
            $message[] = $item->id;
        }
        return $message;
    }

    public static function outletList($merchant_id)
    {
        return DB::table('lp_outlets')->where('merchant_id', $merchant_id)->get();
    }

    //Product List
    static function reply_product($productList)
    {
        $message = "";
        for ($i = 0; $i < count($productList); $i++) {
            $message .= ($i + 1) . "." . $productList[$i]->product_name . '(Ksh. ' . $productList[$i]->flexpay_price . ')' . PHP_EOL;
        }
        return $message;
    }


    static function build_product($productList)
    {
        $message = [];
        foreach ($productList as $item) {
            $message[] = $item->id;
        }
        return $message;
    }

    public static function productList($outlet_id, $merchant_id)
    {
        return DB::table('lp_products')
            ->where('outlet_id', $outlet_id)
            ->where('merchant_id', $merchant_id)
            ->where('product_long_description', 'MamaPrime')
            ->get();
    }


    protected static function merchantHospital()
    {
        return env('HEALTH_FACILITY_LIST');
    }

    public static function redeemFlexPoint($userId)
    {
        $wallet = self::getWallet($userId);
        $pointList = self::getPointList($userId);
        $totalKash = 0;
        if ($wallet) {
            foreach ($pointList as $pt) {
                $redeemedKash = floor((env('FLEX_LOYALTY_POINT_PERCENTAGE', 10) * $pt->booking_points) / 100);
                $totalKash = $totalKash + $redeemedKash;
                $customer = self::getCustomer($userId);
                $moneyInId = DB::table('lp_money_in')->insertGetId(
                    [
                        'first_name' => $customer->first_name, 'last_name' => $customer->last_name,
                        'transaction_code' => $pt->receipt_no, 'transaction_amount' => $redeemedKash,
                        'booking_reference' => $pt->receipt_no, 'transaction_date' => Carbon::now()->toDateTimeString(),
                        'payment_method_id' => 0, 'payment_details' => "Royalty Point Redemption Reward",
                        'money_in_status' => '1', 'user_id' => $userId
                    ]
                );
                if (isset($moneyInId)) {
                    self::updatePoints($userId, $pt->id);
                    DB::table('lp_wallet_credit')->insert(
                        [
                            'user_id' => $userId, 'wallet_id' => $wallet->id,
                            'account_number' => $wallet->account_number, 'amount' => $redeemedKash,
                            'money_in_id' => $moneyInId, 'source' => 'LoyaltyPointsRedemption',
                            'created_at' => Carbon::now(), 'updated_at' => Carbon::now()
                        ]
                    );
                }
            }
        }
        return $totalKash;
    }


    private static function getPointList($userId)
    {
        return DB::table('lp_loyalty_points')->where('user_id', $userId)->where('is_redeemed', 'no')->get();
    }

    public static function getPoints($userId)
    {
        return DB::table('lp_loyalty_points')->where('user_id', $userId)->where('is_redeemed', 'no')->sum('booking_points');
    }

    protected static function updatePoints($userId, $loyalty_id)
    {
        return DB::table('lp_loyalty_points')->where('user_id', $userId)->where('id', $loyalty_id)->update(['is_redeemed' => 'yes']);
    }

    protected static function getWallet($userId)
    {
        return DB::table('lp_wallet_account')->where('user_id', $userId)->first();
    }


    public static function chamaProduct($productTags)
    {
        if (empty($productTags)) return [];
        return DB::table('lp_products')
            ->where('product_long_description', $productTags)
            ->orderBy('merchant_price', 'ASC')
            ->get();
    }

    public static function chamaComplete($user_id)
    {
        $listType = explode(',', self::productTypeChama());
        $chamaBooking = DB::table('lp_products')
            ->join('product_booking', 'product_booking.product_id', '=', 'lp_products.id')
            ->where('product_booking.user_id', $user_id)
            ->whereIn('product_long_description', $listType)
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('product_booking_payments')
                    ->whereColumn('product_booking_payments.booking_id', 'product_booking.id');
            })
            ->orderBy('product_booking.id', 'DESC')
            ->limit(7)
            ->select(
                'lp_products.product_name as product_name',
                'lp_products.product_code as product_code',
                'product_booking.id as booking_id',
                'product_booking.booking_status',
                'product_booking.booking_price as booking_price'
            )
            ->get();
        $chamaBooking->map(function ($item, $key) use ($chamaBooking) {
            if (self::isBookingCollected($item->booking_id)) {
                $chamaBooking->forget($key);
            }
        });
        return $chamaBooking;
    }


    static function reply_chama_complete($productList)
    {
        $message = "";
        for ($i = 0; $i < count($productList); $i++) {
            $total_paid = self::getTotalPaid($productList[$i]->booking_id);
            $message .= ($i + 1) . "." . $productList[$i]->product_name . "(" . $total_paid . ")" . PHP_EOL;
        }
        return $message;
    }

    public static function getReferralCode()
    {
        $referral_code = self::getCode();
        $validator = Validator::make(['referral_code' => $referral_code], ['referral_code' => 'unique:lp_referrals,referral_code']);
        if ($validator->fails()) {
            self::getCode();
        } else {
            return $referral_code;
        }
    }


    public static function getCode()
    {
        $keyspace = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $length = 8;
        $pieces = [];
        $max = mb_strlen($keyspace, '8bit') - 1;
        for ($i = 0; $i < $length; ++$i) {
            $pieces[] = $keyspace[random_int(0, $max)];
        }
        return implode('', $pieces);
    }

    public static function createReferral($referralNumber, $phoneNumber, $userId)
    {
        $referralCustomer = UssdUtil::createOrUpdate($referralNumber);
        $referralCode = UssdUtil::getReferralCode();
        $id = DB::table('lp_referrals')->insertGetId([
            'referral_code' => $referralCode,
            'user_id' => $referralCustomer->user_id,
            'referee_phone_number' => $phoneNumber,
            'referee_user_id' => $userId,
            'referral_amount' => env('REFERRAL_AMOUNT', 100),
            'created_at' => Carbon::now()->toDateTimeString(),
            'updated_at' => Carbon::now()->toDateTimeString()
        ]);
        return DB::table('lp_referrals')->find($id);
    }


    static function build_booking($bookingList)
    {
        $message = [];
        foreach ($bookingList as $item) {
            $message[] = $item->booking_id;
        }
        return $message;
    }

    protected static function productTypeChama()
    {
        return env('CHAMA_TAG_LIST');
    }

    protected static function goalMerchantList()
    {
        return env('GOAL_MERCHANT_LIST');
    }

    protected static function kapuMerchantList()
    {
        return env('KAPU_MERCHANT_LIST');
    }

    public static function flexPayPromoters()
    {
        return env('EXCEPT_PROMOTERS');
    }

    public static function getGoalMerchant()
    {
        $list = explode(',', self::goalMerchantList());
        return DB::table('lp_merchants')->whereIn('id', $list)->get();
    }

    public static function getKapuMerchant()
    {
        $list = explode(',', self::kapuMerchantList());
        return DB::table('lp_merchants')->whereIn('id', $list)->orderBy('id', 'DESC')->get();
    }

    public static function getMerchantById($merchant_id)
    {
        return DB::table('lp_merchants')->where('id', $merchant_id)->first();
    }

    public static function getMerchantByTill($merchant_code)
    {
        return DB::table('lp_merchants')->where('merchant_code', $merchant_code)->first();
    }

    public static function settlementExist($bookingId)
    {
        return DB::table('lp_goal_settlement')->where('booking_id', $bookingId)->exists();
    }

    public static function isBookingCollected($bookingId)
    {
        $collected = DB::table('product_booking_receipt')->where('booking_id', $bookingId)->first();
        return (!is_null($collected) && strcasecmp($collected->receipt_status, 'closed') == 0);
    }

    public static function customerExist($phoneNumber)
    {
        return DB::table('lp_customers')->join('lp_users', 'lp_users.id', '=', 'lp_customers.user_id')->where('phone_number_1', $phoneNumber)->where('lp_users.is_verified', 1)->exists();
    }

    private static function formatPhoneNumber($number, $strip_plus = true)
    {
        $number = preg_replace('/\s+/', '', $number);
        $replace = function ($needle, $replacement) use (&$number) {
            if (Str::startsWith($number, $needle)) {
                $pos = strpos($number, $needle);
                $length = \strlen($needle);
                $number = substr_replace($number, $replacement, $pos, $length);
            }
        };
        $replace('0', '+254');
        $replace('7', '+2547');
        $replace('1', '+2541');
        if ($strip_plus) {
            $replace('+254', '254');
        }
        return $number;
    }

    public static function isValidNewNumber($phoneNumber)
    {
        $newPrefix = substr($phoneNumber, 0, 5);
        return intval($newPrefix) == 25411;
    }

    public static function getReferralEarning($phoneNumber, $userId)
    {
        $sum = DB::table('lp_referrals')
            ->where('referee_phone_number', $phoneNumber)
            ->sum('referral_amount');
        $coupon = self::getReferralCoupon($userId);
        return (floatval($sum) + floatval($coupon));
    }

    public static function getReferralCoupon($userId)
    {
        return DB::table('lp_referrals')
            ->where('user_id', $userId)
            ->sum('referral_amount');
    }


    public static function getCoupon($code)
    {
        return Coupon::query()
            ->where('coupon_code', $code)
            ->where('coupon_status', 'on')
            ->first();
    }

    public static function hasConsumedCoupon($userId, $coupon_id)
    {
        return CouponUtil::query()
            ->where('user_id', $userId)
            ->where('coupon_id', $coupon_id)
            ->exists();
    }

    public static function hasConsumedRecently($userId)
    {
        $couponApplied = CouponUtil::query()->where('user_id', $userId)->latest()->first();
        if ($couponApplied) {
            return (Carbon::parse($couponApplied->created_at)->diffInMonths(Carbon::now(), true) < 3);
        }
        return false;
    }

    public static function getProcessedCoupon($coupon, $merchant_id, $user_id)
    {
        if ((!empty($coupon->user_id) && !is_null($coupon->user_id)) && $coupon->user_id != $user_id) {
            return "This coupon code is limited to a Customer";
        } elseif ((!empty($coupon->merchant_id) && !is_null($coupon->merchant_id)) && $coupon->merchant_id != $merchant_id) {
            return "This coupon code is limited to a Supplier";
        } else {
            return true;
        }
    }


    public static function savePayment($customer, $coupon_code, $amount, $reference)
    {
        $money_id = DB::table('lp_money_in')->insertGetId([
            'user_id' => $customer->user_id,
            'first_name' => isset($customer) ? $customer->first_name : '',
            'last_name' => isset($customer) ? $customer->last_name : '',
            'phone' => isset($customer) ? $customer->phone_number_1 : '',
            'transaction_amount' => $amount,
            'transaction_code' => $coupon_code . '-' . $reference,
            'transaction_date' => Carbon::now(),
            'booking_reference' => $reference,
            'payment_details' => 'Coupon',
            'payment_method_id' => '4',
            'money_in_status' => '1',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);
        return $money_id;
    }

    public static function ussdHandler($text)
    {
        return self::goBack(self::goToMainMenu($text));
    }

    public static function goBack($text)
    {
        $explodedText = explode("*", $text);
        while (array_search(self::$GO_BACK, $explodedText) != false) {
            $firstIndex = array_search(self::$GO_BACK, $explodedText);
            array_splice($explodedText, $firstIndex - 1, 2);
        }
        return join("*", $explodedText);
    }

    public static function goToMainMenu($text)
    {
        $explodedText = explode("*", $text);
        while (array_search(self::$GO_TO_MAIN_MENU, $explodedText) != false) {
            $firstIndex = array_search(self::$GO_TO_MAIN_MENU, $explodedText);
            $explodedText = array_slice($explodedText, $firstIndex + 1);
        }
        return join("*", $explodedText);
    }

    public static function numberDigit($number)
    {
        return preg_replace("/[^0-9]/", "", $number);
    }

    public static function getOutletIds($merchantId)
    {

        $outlets = ['347' => 522, '107' => 258, '319' => 487, '73' => 162, '157' => 243, '120' => 191, '116' => 187];
        return (array_key_exists($merchantId, $outlets) ? $outlets[$merchantId] : 483);
    }

    public static function createMerchant($email, $password, $merchant_name, $phoneNumber, $address, $userId)
    {
        $user_id = self::createAuth($email, $password);
        $meCode = self::generateMerchantCode();
        $id = DB::table('lp_merchants')->insertGetId([
            'user_id' => $user_id,
            'merchant_name' => $merchant_name,
            'agent_id' => $userId,
            'phone_number' => $phoneNumber,
            'address' => $address,
            'merchant_code' => $meCode,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);
        return DB::table('lp_merchants')->find($id);
    }
    //Updated file

    public static function createAuth($email, $password)
    {
        return DB::table('lp_users')->insertGetId([
            'email' => $email,
            'password' => self::bcrypt($password),
            'user_type' => 3,
            'is_verified' => 1,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);
    }

    public static function bcrypt($value, $options = [])
    {
        return app('hash')->make($value, $options);
    }

    public static function generateMerchantCode()
    {
        $merchant = DB::table('lp_merchants')->get();
        $number = !is_null($merchant) ?  $merchant->count() : 0;
        return "1" . sprintf("%'.06d\n", ($number + 1));
    }
}
