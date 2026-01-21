<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\Payment;
use App\Models\PaymentSetting;

class ExpressPayService
{
    protected $baseUrl;
    protected $apiKey;

    public function __construct()
    {
        // Pulled from your config/services.php and .env
        $this->baseUrl = config('services.expresspay.url');
        $this->apiKey = config('services.expresspay.key');
    }

    /**
     * Process payment using ExpressPay API
     * * @param Payment $payment The eloquent model for the transaction
     * @param string|null $cardToken The saved token for recurring/one-click billing
     * @param PaymentSetting|null $settings Admin-defined account configuration
     */
    public function processPayment(Payment $payment, $cardToken = null, $settings = null)
    {
        // Construct the payload for ExpressPay
        $payload = [
            'amount'    => $payment->amount,
            'currency'  => 'GHS',
            'order_id'  => $payment->transaction_reference,
            'token'     => $cardToken, // If null, ExpressPay triggers checkout UI

            // Use the merchant ID specifically set by the admin for this giving type
            'merchant_id' => $settings->merchant_id ?? config('services.expresspay.default_merchant_id'),

            'metadata' => [
                'type'         => $payment->type,
                'user_id'      => $payment->user_id,
                'account_name' => $settings->account_name ?? 'Church General',
            ],

            // Redirect URLs after payment completion
            'redirect_url' => config('services.expresspay.redirect_url'),
            'post_url'     => route('api.payments.webhook'), // Your webhook route
        ];

        // Execute the secure POST request
        $response = Http::withHeaders([
            'X-API-KEY'    => $this->apiKey,
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . '/transactions', $payload);

        return $response->json();
    }
}
