<?php
namespace App\Http\Controllers;

use Carbon\Carbon;
use Carbon\CarbonTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
//use illuminate\Support\Facades\Storage;
use GuzzleHttp\Client;
use Throwable;
use App\Models\StkData;
use GuzzleHttp\Exception\RequestException;
use Storage;
class Payment extends Controller{
    public function token(){
        $client = new Client();

        $consumerKey = '8SASglNPR348ARpPGmAqaK1Gre2EaSGKsCB6v8zcFcSpJjWg';
        $consumerSecret = 'olumDpkcEUmBr4T7DnkoKtKVYAkcKCdLOcO0NAyeMCp6dqGcjkhpAf3HK8585wBM';
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
    
        
    
        
        
    
    public function initiateStkPush() {
        $client = new Client();
        
        $Stoken = json_decode($this->token()->content(), true);
        $accessToken = $Stoken["access_token"]; // Assuming the key is "access_token"
;
        
        // Define the URL for the STK Push request
        $url = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
        
        // Define the passkey and other required variables
        $passkey = 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919';
        $BusinessShortCode = '174379';
        $Timestamp = Carbon::now()->format('YmdHis');
        $password = base64_encode($BusinessShortCode . $passkey . $Timestamp);
        $TransactionType = 'CustomerPayBillOnline';
        $Amount = 1;
        $PartyA = '254721630939'; // Should be in string format
        $PartyB = '174379';
        $PhoneNumber = '254721630939'; // Should be in string format
        $CallBackURL = 'https://89a3-102-215-33-72.ngrok-free.app/payments/stkcallback'; // Correct key name
        $AccountReference = 'Learnsoft Beliotech Solutions Ltd';
        $TransactionDesc = 'payment software services';
    
        // Create the data array for the STK Push request
        $stkpushdata = [
            'BusinessShortCode' => $BusinessShortCode,
            'Password' => $password,
            'Timestamp' => $Timestamp,
            'TransactionType' => $TransactionType,
            'Amount' => $Amount,
            'PartyA' => $PartyA,
            'PartyB' => $PartyB,
            'PhoneNumber' => $PhoneNumber,
            'CallBackURL' => $CallBackURL, // Correct key name
            'AccountReference' => $AccountReference,
            'TransactionDesc' => $TransactionDesc,
        ];
    
        try {
            // Make the POST request to the STK Push URL
            $response = $client->request('POST', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken, // Correctly setting the Authorization header
                    'Content-Type' => 'application/json',
                ],
                'json' => $stkpushdata,
            ]);
    
            // Decode the response body
            $body = json_decode($response->getBody(), true);
    
            // Return the response as JSON
            //return response()->json($body);
            $res = json_decode(response() ->json($body));
           $ResponseCode=$res->ResponseCode;

        if($ResponseCode==0){
            $MerchantRequestID=$res->MerchantRequestID;
            $CheckoutRequestID=$res->CheckoutRequestID;
            $CustomerMessage=$res->CustomerMessage;

            //save to database
            $payment= new StkData;
            $payment->phone=$PhoneNumber;
            $payment->amount=$Amount;
            $payment->reference=$AccountReference;
            $payment->description=$TransactionDesc;
            $payment->MerchantRequestID=$MerchantRequestID;
            $payment->CheckoutRequestID=$CheckoutRequestID;
            $payment->status='Requested';
            $payment->save();

            return $CustomerMessage;
        }

        } catch (\Exception $e) {
            // Handle exceptions and return the error message
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    
    
    
    
    
    public function stkcallback(){
        $data=file_get_contents('php://input');
        Storage::disk('local')->put('stk.txt',$data);

        $response=json_decode($data);

        $ResultCode = $response->Body->stkCallback->ResultCode;

        if($ResultCode==0){
            $MerchantRequestID=$response->Body->stkCallback->MerchantRequestID;
            $CheckoutRequestID=$response->Body->stkCallback->CheckoutRequestID;
            $ResultDesc=$response->Body->stkCallback->ResultDesc;
            $Amount=$response->Body->stkCallback->CallbackMetadata->Item[0]->Value;
            $MpesaReceiptNumber=$response->Body->stkCallback->CallbackMetadata->Item[1]->Value;
            //$Balance=$response->Body->stkCallback->CallbackMetadata->Item[2]->Value;
            $TransactionDate=$response->Body->stkCallback->CallbackMetadata->Item[3]->Value;
            $PhoneNumber=$response->Body->stkCallback->CallbackMetadata->Item[3]->Value;

            $payment=StkData::where('CheckoutRequestID',$CheckoutRequestID)->firstOrfail();
            $payment->status='Paid';
            $payment->TransactionDate=$TransactionDate;
            $payment->MpesaReceiptNumber=$MpesaReceiptNumber;
            $payment->ResultDesc=$ResultDesc;
            $payment->save();

        }else{

        $CheckoutRequestID=$response->Body->stkCallback->CheckoutRequestID;
        $ResultDesc=$response->Body->stkCallback->ResultDesc;
        $payment=StkData::where('CheckoutRequestID',$CheckoutRequestID)->firstOrfail();
        
        $payment->ResultDesc=$ResultDesc;
        $payment->status='Failed';
        $payment->save();

        }

    }
}