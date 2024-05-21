<?php
namespace App\Http\Controllers;

use Carbon\Carbon;
use Carbon\CarbonTimeZone;
use Illuminate\Http\Request;
use illuminate\Support\Facades\Http;
use illuminate\Support\Facades\Storage;
use GuzzleHttp\Client;
use Throwable;
use App\Models\StkData;
use GuzzleHttp\Exception\RequestException;

class Payment extends Controller{
    public function token(){
        $client = new Client();

        $consumerKey = 'DoJfmC4HdY5yUuo6rmiScqsHCYt5AGHHuZJJtjhyBJI0A7Nh';
        $consumerSecret = 'cmIOUZVJ5Uo0jZopLjdjKHpKDZdC4gcAhGUdqoloUG1qoR8rH7cgaM7Es9gYNw7V';
        $url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
        //$response = Http::withBasicAuth($consumerKey, $consumerSecret) -> get($url);
        //return $response['access_token'];

        // Encode the credentials
        $credentials = base64_encode($consumerKey . ':' . $consumerSecret);

        try {
        //Make the request to the Daraja API
            $response = $client->request('GET', $url, [
                'headers' => [
                    'Authorization' => 'Basic ' . $credentials,
                ],
            ]);

           $body = json_decode($response->getBody(), true);
            return response()->json($body);

        } catch (\Exception $e) {
            return response()->json([
               'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function initiateStkPush(){
        $client = new Client();
        $accessToken = $this -> token();
        $url = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
        $passkey = 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919'; 
        $BusinessShortCode='174379';
        $Timestamp = Carbon::now()->format('YmdHis');
        $password = base64_encode($BusinessShortCode.$passkey.$Timestamp);
        $TransactionType= 'CustomerPayBillOnline';
        $Amount = 1;
        $PartyA = 254721630939;
        $PartyB = '174379';
        $PhoneNumber = 25472160939;
        $CallbackUrl = 'https://9a29-154-159-237-18.ngrok-free.app/payments/stkcallback';
        $AccountReference = 'Learnsoft Beliotech Solutions Ltd';
        $TransactionDesc='payment software services'; 

        $stkpushdata = [
            'BusinessShortCode'=>$BusinessShortCode,
            'Password'=>$password,
            'Timestamp'=>$Timestamp,
            'TransactionType'=>$TransactionType,
            'Amount' => $Amount,
            'PartyA' => $PartyA,
            '$PartyB'=> $PartyB,
            '$PhoneNuber'=> $PhoneNumber,
            'CallbackUrl'=>$CallbackUrl,
            'AccountReference'=>$AccountReference,
            'TransactionDesc'=>$TransactionDesc,
        ];

        
        try {
            $client = new Client();
            $response = $client->request('POST', '$url', [
                'headers' => [
                    'Authorization'=> 'Bearer '.$accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $stkpushdata,
            ]);

            $body = json_decode($response->getBody(), true);
            //return response()->json($body);
            $res = Json_decode(response()->json($body));

            $ResponseCode = $res ->ResponseCode;
            if ($ResponseCode == 0) {
                $MerchantRequestID =$res ->MerchantRequestID;
                $CheckoutRequestID=$res ->CheckoutRequestID;
                $CustomerMessage = $res ->CustomerMessage;

                //save to database
                $payment= new StkData;
                $payment ->phone=$PhoneNumber;
                $payment ->amount=$Amount;
                $payment ->reference=$AccountReference;
                $payment ->description=$TransactionDesc;
                $payment ->MerchantRequestID= $MerchantRequestID;
                $payment ->CheckoutRequestID= $CheckoutRequestID;
                $payment ->status= 'Requested';
                $payment ->save();

                return $CustomerMessage;
            }

    

        } catch (RequestException $e) {
            return response()->json([
                'error' => 'Error initiating STK Push: ' . $e->getMessage(),
            ], 500);
        }
    

    }
    //public function stkCallback(){
      //  $data=file_get_contents()
    //}
}