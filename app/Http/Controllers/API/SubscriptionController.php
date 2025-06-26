<?php

namespace App\Http\Controllers\API;

use Exception;
use App\Models\Plan;
use App\Models\Payment;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use App\Models\UserSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Srmklive\PayPal\Services\PayPal as PayPalClient;

class SubscriptionController extends Controller
{
    use ApiResponse;

    public function getPlans()
    {
        $plans = Plan::all()->map(function ($plan) {
            return [
                'id' => $plan->id,
                'name' => $plan->name,
                'price' => intval($plan->price),
                'duration' => $plan->duration,
            ];
        });

        return $this->sendResponse($plans, 'Plans fetched successfully');
    }

    public function getSubscription(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'plan_id' => 'required|exists:plans,id',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Failed', $validator->errors()->toArray(), 422);
        }
        $user = auth()->user();
        if (!$user) {
            return $this->sendError('Unauthorized', ['error' => 'User not authenticated'], 401);
        }
        try {
            $plan = Plan::findOrFail($request->plan_id);

            if (UserSubscription::where('user_id', $user->id)->where('status', 'active')->exists()) {
                return $this->sendError('Already Subscribed', ['error' => 'User already has an active subscription'], 400);
            }
            $subscription = UserSubscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'start_date' => now(),
                'end_date' => now()->addMonth(),
                'status' => 'pending',
            ]);
            $payment = Payment::create([
                'user_subscription_id' => $subscription->id,
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'amount' => $plan->price,
                'transaction_id' => substr(uniqid('txn_'), 0, 16),
                'payment_method' => 'paypal',
                'payment_date' => now(),
                'status' => 'pending',
            ]);
            $provider = new PayPalClient;
            $provider->setApiCredentials(config('paypal'));
            $provider->getAccessToken();
            $paymentResponse = $provider->createOrder([
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'amount' => [
                            'currency_code' => 'USD',
                            'value' => $plan->price,
                        ],
                    ],
                ],
                'application_context' => [
                    'return_url' => route("payment.success", ['payment_id' => $payment->id]),
                    'cancel_url' => route("payment.cancel"),
                ],
            ]);

            if (isset($paymentResponse['id'])) {
                foreach ($paymentResponse['links'] as $link) {
                    if ($link['rel'] == 'approve') {
                        return $this->sendResponse(['payment_url' => $link['href']], 'PayPal payment created successfully');
                    }
                }
            }

            return $this->sendError('PayPal Payment Failed', ['error' => 'Unable to create payment'], 500);
        } catch (Exception $e) {
            Log::error('Subscription Error: ' . $e->getMessage());
            return $this->sendError('Subscription Failed', ['error' => 'Something went wrong'], 500);
        }
    }

    public function success(Request $request)
    {
        $payment = Payment::find($request->payment_id);
        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        if ($payment->status !== 'pending') {
            return response()->json(['message' => 'Invalid payment status'], 400);
        }

        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        try {
            $paymentResponse = $provider->capturePaymentOrder($request->token);

            if (isset($paymentResponse['status']) && $paymentResponse['status'] === 'COMPLETED') {
                DB::beginTransaction();
                try {
                    // Update payment status
                    $payment->update([
                        'status' => 'successful',
                        'details' => json_encode($paymentResponse),
                    ]);

                    // Update subscription status
                    $subscription = $payment->userSubscription;
                    $subscription->update([
                        'status' => 'active',
                        'last_payment_date' => now(),
                    ]);

                    // Update user subscription fields
                    $user = $payment->userSubscription->user;
                    $user->update([
                        'is_subscribe' => true,
                        'subscribe_at' => now(),
                        'subscribe_expires_at' => $subscription->end_date,
                    ]);

                    DB::commit();
                    return redirect('https://maoiexperts.org/payment-success')->with([
                        'status' => true,
                        'message' => 'Payment and subscription activated successfully',
                    ], 200);
                } catch (Exception $e) {
                    DB::rollBack();
                    Log::error('Payment Success Error: ' . $e->getMessage());
                    return response()->json(['message' => 'Error processing payment'], 500);
                }
            }

            $payment->update(['status' => 'failed']);
            return response()->json(['message' => 'Payment capture failed'], 400);
        } catch (Exception $e) {
            Log::error('PayPal Capture Error: ' . $e->getMessage());
            return response()->json(['message' => 'Payment processing failed'], 500);
        }
    }
    public function cancel()
    {
        return redirect('https://maoiexperts.org/payment-failed')->with([
            'status' => false,
            'message' => 'Payment canceled',
        ], 200);
    }
    //user subscription
    public function subscriptionForUser()
    {
        $user = auth()->user();
        if (!$user) {
            return $this->sendError('Unauthorized', ['error' => 'User not authenticated'], 401);
        }
        $subscription = UserSubscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->latest()
            ->first() ?? [];

        return $this->sendResponse($subscription, 'Subscription retrieved successfully');
    }
}
