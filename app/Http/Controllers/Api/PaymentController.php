<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\UserCard;
use App\Models\PaymentSetting; // Added to fetch Admin settings
use App\Services\ExpressPayService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function initiatePayment(Request $request, ExpressPayService $expressPay)
    {
        // 1. Validate Input
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'type' => 'required|in:offering,tithe,partnership',
            'card_token' => 'nullable|string',
        ]);

        // 2. Fetch Admin Configuration for this Giving Type
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
            'user_id' => auth()->id(),
            'amount' => $request->amount,
            'type' => $request->type,
            'transaction_reference' => $reference,
            'status' => 'pending',
            // We store which merchant account this was intended for
            'merchant_id' => $settings->merchant_id
        ]);

        // 4. Process via ExpressPay Service
        // We pass the dynamic settings (like merchant_id) to the service
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
    // 1. Log the incoming data (Crucial for debugging payments)
    \Log::info('ExpressPay Webhook Received:', $request->all());

    // 2. Locate the transaction in your database
    $payment = Payment::where('transaction_reference', $request->order_id)->first();

    if (!$payment) {
        return response()->json(['message' => 'Transaction not found'], 404);
    }

    // 3. Update based on ExpressPay status code (assuming '00' is success)
    if ($request->result_code == '00') {
        $payment->update(['status' => 'success']);

        // You could trigger an event here:
        // event(new GivingReceived($payment));
    } else {
        $payment->update(['status' => 'failed']);
    }

    // 4. Always return a 200 OK so ExpressPay knows you got the message
    return response()->json(['status' => 'acknowledged']);
}
}
