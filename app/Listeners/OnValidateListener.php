<?php

namespace App\Listeners;

use Carbon\Carbon;
use App\Helpers\UssdUtil;
use App\Events\BookingEvent;
use App\Events\RegisterEvent;
use App\Traits\DataTransferTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Events\ValidateAndConfirmEvent;


/**
 * @property array data3
 * @property array data2
 * @property array data
 */
class OnValidateListener
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


    public function handle(ValidateAndConfirmEvent $event)
    {
        if ($event->receipt) {
            $validBooking = UssdUtil::validateReceipt($event->receipt);
            if (!is_null($validBooking)) {
                $amount_paid = UssdUtil::getTotalPaid($validBooking->booking_id);
                //reply with confirmation
                $promoterResponse = ['recipients' => $event->phoneNumber,
                    'template_id' => '5',
                    'receipt_code' => $validBooking->receipt_code,
                    'product_name' => $validBooking->product_name,
                    'booking_price' => $validBooking->booking_price,
                    'amount_paid' => $amount_paid,
                    'phone_no' => UssdUtil::getCustomerCare()];
                $this->sendMessage($promoterResponse);

                DB::table('product_booking_receipt')
                    ->where('receipt_no', $validBooking->receipt_code)
                    ->update([
                        'receipt_status' => 'closed',
                        'closed_by' => $event->userId,
                        'validated_at' => Carbon::now()
                        ]);

                DB::table('product_booking')
                    ->where('id', $validBooking->booking_id)
                    ->where('booking_status', 'closed')
                    ->update(['validation_price' => $validBooking->booking_price]);

                //send the customer message
                $customerResponse = ['recipients' => $validBooking->phone_number_1,
                    'template_id' => '4',
                    'receipt_code' => $validBooking->receipt_code,
                    'phone_no' => UssdUtil::getCustomerCare()];
                $this->sendMessage($customerResponse);

                // Send survey Message
                /* $customerSurvey = ['recipients' => $validBooking->phone_number_1,
                    'template_id' => '6',
                    'url' => 'https://bit.ly/2wqRnbl',
                    'flex_site' => 'https://marketplace.flexpay.co.ke/'];
                $this->sendMessage($customerSurvey); */
                $customerAppInvite = ['recipients' => $validBooking->phone_number_1,'template_id' => '50'];
                $this->sendMessage($customerAppInvite);
                try {
                    $this->sendMoneyPayment($validBooking->booking_price, $validBooking->booking_id);
                } catch (\Exception $e) {
                    throw $e;
                }
            } else {
                Log::error("OnValidateListener:handle:null" . json_encode($validBooking));
            }
        }
    }


    function sendMessage($template)
    {
        if (is_array($template)) {
            $smsResponse = (new self)->guzzlePost(env('SMS_ENDPOINT'), $template);
        } else {
            Log::critical("OnValidateListener:sendMessageTemplate========>Error");
        }


    }

    function topUpMerchantWallet($booking_id)
    {
        $data = ['booking_id' => $booking_id];
        $commissionResponse = (new self)->guzzlePost(env('BOOKING_COMMISSION'), $data);
        if ($commissionResponse) {
            Log::critical("OnValidateListener:topUpMerchantWallet==" . $commissionResponse);
        } else {
            Log::critical("OnValidateListener:topUpMerchantWallet-Error==" . $commissionResponse);
        }
    }

    public function sendMoneyPayment($amount, $booking_id)
    {
        $data = [
            'amount' => ceil($amount),
            'booking_id' => $booking_id
        ];
        $send_payment = (new self)->guzzlePost(env('BOOKING_B2B_ENDPOINT'), $data);

    }

}
