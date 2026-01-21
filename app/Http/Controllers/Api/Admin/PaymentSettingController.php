<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentSetting;
use Illuminate\Http\Request;

class PaymentSettingController extends Controller
{
    public function index()
    {
        return response()->json(PaymentSetting::all());
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'giving_type'    => 'required|in:tithe,offering,partnership',
            'account_name'   => 'required|string',
            'account_number' => 'required|string',
            'bank_name'      => 'required|string',
            'merchant_id'    => 'nullable|string'
        ]);

        $setting = PaymentSetting::updateOrCreate(
            ['giving_type' => $validated['giving_type']],
            $validated
        );

        return response()->json(['message' => 'Account details updated', 'data' => $setting]);
    }
}
