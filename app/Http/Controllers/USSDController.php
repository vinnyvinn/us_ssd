<?php

namespace App\Http\Controllers;


use App\Helpers\FlexpayUssd;
use App\Helpers\FlexpayUssdChannel2;
use App\Helpers\FlexpayUssdChannel3;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

ob_start();

class USSDController extends Controller
{

    public function ussdProcess(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'serviceCode' => 'required',
            'phoneNumber' => 'required',
            'sessionId' => 'required',
        ]);
        if ($validator->fails()) {
            return $validator->errors();
        } else {
            FlexpayUssd::receiveUssd($request);
        }
    }

    public function ussdChannel2(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'serviceCode' => 'required',
            'phoneNumber' => 'required',
            'sessionId' => 'required',
        ]);
        if ($validator->fails()) {
            return $validator->errors();
        } else {
            FlexpayUssdChannel2::receiveUssd($request);
        }
    }

    public function ussdChannel3(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'serviceCode' => 'required',
            'phoneNumber' => 'required',
            'sessionId' => 'required',
        ]);
        if ($validator->fails()) {
            return $validator->errors();
        } else {
            FlexpayUssdChannel3::receiveUssd($request);
        }
    }

}

ob_end_flush();
