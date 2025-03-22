<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Stripe\Webhook;
use Razorpay\Api\Api;
use App\Models\Package;
use App\Models\Customer;
use App\Models\Payments;
use App\Libraries\Paypal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\UserPurchasedPackage;
use KingFlamez\Rave\Facades\Rave as Flutterwave;


class WebhookController extends Controller
{
    public function paystack()
    {
        $inputJSON = @file_get_contents("php://input");
        http_response_code(200);
        $input = json_decode($inputJSON, true);
        Log::info('Paystack Webhook Called');

        // Calculate HMAC
        $paystackSecretKey = system_setting('paystack_secret_key');
        $headerSignature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'];
        define('PAYSTACK_SECRET_KEY', $paystackSecretKey);
        $calculatedHMAC = hash_hmac('sha512', $inputJSON, PAYSTACK_SECRET_KEY);
        $verified = hash_equals($headerSignature, $calculatedHMAC);

        if ($verified) {
            Log::info('Paystack Webhook Signature Verified Successfully');
            switch ($input['event']) {
                case 'charge.success':
                    $user_id = $input['data']['metadata']['user_id'];
                    $package_id = $input['data']['metadata']['package_id'];

                    $payment = new Payments();
                    $payment->transaction_id = $input['data']['id'];
                    $payment->amount = ($input['data']['amount']) / 100;
                    $payment->package_id = $package_id;
                    $payment->customer_id = $user_id;
                    $payment->status = 1;
                    $payment->payment_gateway = "paystack";
                    $payment->save();
                    $start_date =  Carbon::now();

                    $user = Customer::find($user_id);
                    $package = Package::find($package_id);


                    if ($package) {
                        $user_package = new UserPurchasedPackage();
                        $user_package->modal()->associate($user);
                        $user_package->package_id = $package_id;
                        $user_package->start_date = $start_date;
                        $user_package->end_date = $package->duration != 0 ? Carbon::now()->addDays($package->duration) : NULL;
                        $user_package->save();

                        $user->subscription = 1;
                        $user->update();
                    }
                    break;
            }
        } else {
            Log::error('!! Paystack Webhook Signature Verification Failed Payment Failed !!');
        }
    }
    public function razorpay(Request $request)
    {
        Log::info('Razorpay Webhook Called');
        // get the json data of payment
        $webhookBody = $request->getContent();
        $webhookBody = file_get_contents('php://input');
        $data = json_decode($webhookBody, true);

        $razorPayApiKey = system_setting('razor_key');
        $razorPaySecretKey = system_setting('razor_secret');
        $api = new Api($razorPayApiKey, $razorPaySecretKey);

        // gets the signature from header
        $webhookSignature = $request->header('X-Razorpay-Signature');
        $webhookSecret = system_setting('razor_webhook_secret');

        //checks the signature
        $expectedSignature = hash_hmac("SHA256", $webhookBody, $webhookSecret);

        if ($expectedSignature == $webhookSignature) {
            Log::info("Razorpay Signature Matched");
            $api->utility->verifyWebhookSignature($webhookBody, $webhookSignature, $webhookSecret);

            switch ($data['event']) {
                case 'payment.authorized':
                    $user_id = $data['payload']['payment']['entity']['notes']['user_id'];
                    $package_id = $data['payload']['payment']['entity']['notes']['package_id'];

                    $payment = new Payments();
                    $payment->transaction_id = $data['payload']['payment']['entity']['id'];
                    $payment->amount = ($data['payload']['payment']['entity']['amount']) / 100;
                    $payment->package_id = $package_id;
                    $payment->customer_id = $user_id;
                    $payment->status = 1;
                    $payment->payment_gateway = "razorpay";
                    $payment->save();
                    $start_date =  Carbon::now();

                    $user = Customer::find($user_id);
                    $package = Package::find($package_id);


                    if ($package) {
                        $user_package = new UserPurchasedPackage();
                        $user_package->modal()->associate($user);
                        $user_package->package_id = $package_id;
                        $user_package->start_date = $start_date;
                        $user_package->end_date = $package->duration != 0 ? Carbon::now()->addDays($package->duration) : NULL;
                        $user_package->save();
                        $user->subscription = 1;
                        $user->update();
                    }
                    break;
            }

            Log::info("Payment Done Successfully");
        } else {
            Log::error("Razorpay Signature Not Matched Payment Failed !!!!!!");
        }
    }
    public function paypal(Request $request)
    {
        Log::info('Paypal Webhook Called');
        $input = file_get_contents('php://input');

        $paypal = new Paypal();
        // Check if $input is not empty
        if (!empty($input)) {
            parse_str($input, $arr);
            $ipnCheck = $paypal->validate_ipn($arr);
            if ($ipnCheck) {
                Log::debug('paypal IPN valid');
            } else {
                Log::debug('paypal IPN Invalid');
            }
            switch ($arr['payment_status']) {
                case 'Completed':
                    $custom_data = explode(',', $arr['custom']);
                    $package_id = $custom_data[0];
                    $user_id = $custom_data[1];

                    $payment = new Payments();
                    $payment->transaction_id = $arr['txn_id'];
                    $payment->amount = ($arr['payment_gross']);
                    $payment->package_id = $package_id;
                    $payment->customer_id = $user_id;
                    $payment->status = 1;
                    $payment->payment_gateway = "paypal";
                    $payment->save();
                    $start_date =  Carbon::now();

                    $user = Customer::find($user_id);
                    $package = Package::find($package_id);

                    if ($package) {
                        $user_package = new UserPurchasedPackage();
                        $user_package->modal()->associate($user);
                        $user_package->package_id = $package_id;
                        $user_package->start_date = $start_date;
                        $user_package->end_date = $package->duration != 0 ? Carbon::now()->addDays($package->duration) : NULL;
                        $user_package->save();

                        $user->subscription = 1;
                        $user->update();
                    }
                    break;
            }
        } else {
            Log::debug('input is empty');
        }
    }
    public function stripe(Request $request)
    {
        Log::info('Stripe Webhook Called');
        // Get File Contents
        $payload = $request->getContent();
        // Get Webhook Secret From Webhook
        $secret = system_setting('stripe_webhook_secret_key');
        // Get Signature from Header
        $signatureHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'];
        try {
            // Create A Event
            $event = Webhook::constructEvent($payload, $signatureHeader, $secret);
            // Get Package ID
            $package_id = $event->data->object->metadata->packageId;
            // Get User ID
            $user_id = $event->data->object->metadata->userId;
            switch ($event->type) {
                case "payment_intent.succeeded":
                    // Add Entry in Payments as history
                    $payment = new Payments();
                    $payment->transaction_id = $event->data->object->id;
                    $payment->amount =  $event->data->object->amount / 100;
                    $payment->package_id = $package_id;
                    $payment->customer_id = $user_id;
                    $payment->status = 1;
                    $payment->payment_gateway = "stripe";
                    $payment->save();

                    // Start Date as current date
                    $start_date = Carbon::now();
                    // Get User Data
                    $user = Customer::find($user_id);
                    // Get Package Data
                    $package = Package::find($package_id);
                    // Check that package is not empty
                    if (collect($package)->isNotEmpty()) {
                        // Add Entry of package in User Purchased package to allocate the package
                        $user_package = new UserPurchasedPackage();
                        $user_package->modal()->associate($user);
                        $user_package->package_id = $package_id;
                        $user_package->start_date = $start_date;
                        $user_package->end_date = $package->duration != 0 ? Carbon::now()->addDays($package->duration) : NULL;
                        $user_package->save();

                        // Update the user's subscription to 1
                        $user->subscription = 1;
                        $user->update();
                    }
                    break;
                case 'payment_intent.payment_failed':
                    Log::error('Payment Failed');
                    break;
                default:
                    Log::error('Stripe Webhook : Received unknown event type');
            }
            Log::info('Stripe Webhook received Successfully');
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            // Invalid Signature Log
            return Log::error('Stripe Webhook verification failed');
        } catch (\Exception $e) {
            // Other Error Exception
            return Log::error('Stripe Webhook failed');
        }
    }
    public function flutterwave(Request $request){
        try {
            //This verifies the webhook is sent from Flutterwave
            $verified = Flutterwave::verifyWebhook();

            // Verify the transaction
            if ($verified) {
                $verificationData = Flutterwave::verifyTransaction($request->id);
                if ($verificationData['status'] === 'success') {
                    $data = (object)$verificationData['data'];
                    $metaData = (object)$verificationData['data']['meta'];
                    $packageId = $metaData->package_id;
                    $userId = $metaData->user_id;

                    // Add Entry in Payments as history
                    $payment = new Payments();
                    $payment->transaction_id = $data->id;
                    $payment->amount =  $data->amount;
                    $payment->package_id = $packageId;
                    $payment->customer_id = $userId;
                    $payment->status = 1;
                    $payment->payment_gateway = "flutterwave";
                    $payment->save();

                    // Start Date as current date
                    $startDate = Carbon::now();
                    // Get User Data
                    $user = Customer::find($userId);
                    // Get Package Data
                    $package = Package::find($packageId);
                    // Check that package is not empty
                    if (collect($package)->isNotEmpty()) {
                        // Add Entry of package in User Purchased package to allocate the package
                        $user_package = new UserPurchasedPackage();
                        $user_package->modal()->associate($user);
                        $user_package->package_id = $packageId;
                        $user_package->start_date = $startDate;
                        $user_package->end_date = $package->duration != 0 ? Carbon::now()->addDays($package->duration) : NULL;
                        $user_package->save();

                        // Update the user's subscription to 1
                        $user->subscription = 1;
                        $user->update();
                    }
                }else{
                    Log::error('Flutterwave Webhook Status Not Succeeded');
                }
            }else{
                Log::error('Flutterwave Webhook Verification Error');
            }
        }catch (\Exception $e) {
            // Other Error Exception
            return Log::error('Flutterwave Webhook failed');
        }
    }
}
