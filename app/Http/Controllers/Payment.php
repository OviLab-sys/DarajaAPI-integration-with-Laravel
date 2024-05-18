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

class Payment extends Controller
{
    public function token(){
        $client = new Client();

        $consumerKey = 'DoJfmC4HdY5yUuo6rmiScqsHCYt5AGHHuZJJtjhyBJI0A7Nh';
        $consumerSecret = 'cmIOUZVJ5Uo0jZopLjdjKHpKDZdC4gcAhGUdqoloUG1qoR8rH7cgaM7Es9gYNw7V';
        $url = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
        //$response = Http::withBasicAuth($consumerKey, $consumerSecret) -> get($url);

        // Encode the credentials
        $credentials = base64_encode($consumerKey . ':' . $consumerSecret);

        try {
            // Make the request to the Daraja API
            $response = $client->request('GET', 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials', [
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

    public function initiateStKPush(){
        $accessToken = $this -> token();
        $url = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
        $passkey = 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919'; 
        $BusinessShortCode='174379';
        $Timestamp = Carbon::now()->format('Y-m-d H:i:s');
        $password = base64_encode($BusinessShortCode.$passkey.$Timestamp);
        $TransactionType= 'CustomerPayBillOnline';
        $Amount = 1;
        $PartyA = 254721630939;
        $PartyB = '174379';
        $PhoneNumber = 25472160939;
        $CallbackUrl = 'https://9a29-154-159-237-18.ngrok-free.app/payments/stkcallback';
        $AccountReference = 'Coders base';
        $TransactionDesc='payment for good'; 

        try{
        $response=Http::withToken($accessToken) ->post($url,[
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
            'TransactionDesc'=>$TransactionDesc
        ]);
        } catch(Throwable $e){
            return $e->getMessage();
        }

        $res =json_decode($response);

        $ResponseCode = $res->ResponseCode;

        if ($ResponseCode==0){
            $MerchantRequestID=$res->MerchantRequestID;
            $CheckoutRequestID=$res->CheckoutRequestID;
            $CustomerMessage=$res->CustomerMessage;

            //save to Database
            $payment= new StkData();
            $payment->phone = $PhoneNumber;
            $payment->amount = $Amount;
            $payment->reference = $AccountReference;
            $payment->description = $TransactionDesc;
            $payment->MerchantRequestID = $MerchantRequestID;
            $payment->CheckoutRequestID=$CheckoutRequestID;
            $payment->status='Requested';
            $payment->save();
        }

    }

    public function stkcallBacks(){
        $data=file_get_contents('php://input');
        Storage::disk('local')->put('stk.txt',$data);
    }
}
