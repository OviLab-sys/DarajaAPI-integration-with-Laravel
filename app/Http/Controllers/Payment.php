<?php
namespace App\Http\Controllers;

use Carbon\Carbon;
use Carbon\CarbonTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Client;
use Throwable;
use App\Models\StkData;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Storage;

//use Storage;


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
            $response = $client->request("GET", $url, [
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
        $CallBackURL = 'https://16dc-102-215-33-72.ngrok-free.app/payments/stkcallback'; // Correct key name
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
        
    

            //$res = json_decode(response() ->json($body));
            $ResponseCode = $body['ResponseCode'];

        if($ResponseCode==0){
            $MerchantRequestID=$body["MerchantRequestID"];
            $CheckoutRequestID=$body["CheckoutRequestID"];
            $CustomerMessage=$body["CustomerMessage"];

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
    

    }catch (\Exception $e) {
            // Handle exceptions and return the error message
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    
   
    public function stkcallback() {
        // Get the raw input data
        $data = file_get_contents('php://input');
    
        // Store the raw input data for debugging purposes
        Storage::disk('local')->put('stk_raw_input.txt', $data);
    
        // Check if data is empty
        if (empty($data)) {
            Storage::disk('local')->put('stk_empty_data_error.txt', 'Received empty input data.');
            return response()->json(['error' => 'Received empty input data'], 400);
        }
    
        // Decode the JSON data
        $response = json_decode($data);
    
        // Check for JSON parsing errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            $jsonError = json_last_error_msg();
            // Log the JSON error with raw input
            Storage::disk('local')->put('stk_json_error.txt', 'JSON Error: ' . $jsonError . "\nRaw Input: " . $data);
    
            // Handle the error (e.g., return an error response or log more details)
            return response()->json(['error' => 'Invalid JSON data received', 'message' => $jsonError], 400);
        }
    
        // Additional logging to inspect the decoded response
        Storage::disk('local')->put('stk_decoded_response.txt', print_r($response, true));
    
        // Check if the necessary structure exists in the response
        if (isset($response->Body->stkCallback->ResultCode)) {
            $stkCallback = $response->Body->stkCallback;
    
            $ResultCode = $stkCallback->ResultCode;
            $MerchantRequestID = $stkCallback->MerchantRequestID;
            $CheckoutRequestID = $stkCallback->CheckoutRequestID;
            $ResultDesc = $stkCallback->ResultDesc;
    
            if ($ResultCode == 0) {
                $CallbackMetadata = $stkCallback->CallbackMetadata->Item;
    
                $Amount = $CallbackMetadata[0]->Value;
                $MpesaReceiptNumber = $CallbackMetadata[1]->Value;
                $TransactionDate = $CallbackMetadata[3]->Value;
                $PhoneNumber = $CallbackMetadata[4]->Value;
    
                $payment = StkData::where('CheckoutRequestID', $CheckoutRequestID)->firstOrFail();
                $payment->status = 'Paid';
                $payment->TransactionDate = $TransactionDate;
                $payment->MpesaReceiptNumber = $MpesaReceiptNumber;
                $payment->ResultDesc = $ResultDesc;
                $payment->save();
            } else {
                $payment = StkData::where('CheckoutRequestID', $CheckoutRequestID)->firstOrFail();
                $payment->ResultDesc = $ResultDesc;
                $payment->status = 'Failed';
                $payment->save();
            }
        } else {
            // Log the missing structure error with raw input and decoded response
            Storage::disk('local')->put('stk_structure_error.txt', 'Missing expected structure in JSON data: ' . print_r($response, true) . "\nRaw Input: " . $data);
    
            // Handle the error (e.g., return an error response)
            return response()->json(['error' => 'Invalid callback structure'], 400);
        }
    }
} 