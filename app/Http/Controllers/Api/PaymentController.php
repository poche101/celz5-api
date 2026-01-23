<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\UserCard;
use App\Models\PaymentSetting;
use App\Services\ExpressPayService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function __construct()
    {
        // Redirects to login automatically if no Bearer token is present
        $this->middleware('auth:sanctum')->except('handleWebhook');
    }

    public function initiatePayment(Request $request, ExpressPayService $expressPay)
    {
        $user = $request->user();

        // 1. Check if profile is incomplete (Redirect logic for Frontend)
        if (empty($user->church) || empty($user->group) || empty($user->cell)) {
            return response()->json([
                'status' => 'profile_incomplete',
                'message' => 'Please update your profile details (Church, Group, Cell) before making a payment.',
                'user' => $user
            ], 403);
        }

        // 2. Validate Input
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'type' => 'required|in:offering,tithe,partnership',
            'card_token' => 'nullable|string',
        ]);

        // 2. Fetch Admin Configuration
        $settings = PaymentSetting::where('giving_type', $request->type)
            ->where('is_active', true)
            ->first();

        if (!$settings) {
            return response()->json([
                'status' => 'error',
                'message' => "Payment details for {$request->type} have not been configured by the admin."
            ], 400);
        }

        $reference = 'CH-' . Str::upper(Str::random(10));

        // 3. Create the Database Record
        $payment = Payment::create([
            'user_id' => $user->id,
            'amount' => $request->amount,
            'type' => $request->type,
            'transaction_reference' => $reference,
            'status' => 'pending',
            'merchant_id' => $settings->merchant_id
        ]);

        // 4. Process via ExpressPay Service
        $response = $expressPay->processPayment($payment, $request->card_token, $settings);

        return response()->json([
            'status' => 'success',
            'account_name' => $settings->account_name,
            'bank' => $settings->bank_name,
            'data' => $response
        ]);
    }

    public function saveCard(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'last_four' => 'required|string|max:4',
            'card_type' => 'required|string'
        ]);

        UserCard::updateOrCreate(
            ['user_id' => auth()->id(), 'card_token' => $request->token],
            ['last_four' => $request->last_four, 'card_type' => $request->card_type]
        );

        return response()->json(['message' => 'Card saved for future payments']);
    }

    public function handleWebhook(Request $request)
    {
        \Log::info('ExpressPay Webhook Received:', $request->all());

        $payment = Payment::where('transaction_reference', $request->order_id)->first();

        if (!$payment) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        if ($request->result_code == '00') {
            $payment->update(['status' => 'success']);
        } else {
            $payment->update(['status' => 'failed']);
        }

        return response()->json(['status' => 'acknowledged']);
    }
}
