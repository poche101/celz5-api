<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Testimony;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TestimonyController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Submit Testimony from the Floating Chat Form
     */
    public function submitForm(Request $request)
    {
        // 1. Validate the four requested fields
        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:255',
            'group'     => 'required|string|max:255',
            'church'    => 'required|string|max:255',
            'testimony' => 'required|string|min:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        // 2. Save the submission
        Testimony::create([
            'user_id'   => auth()->id(),
            'full_name' => $request->full_name,
            'group'     => $request->group,
            'church'    => $request->church,
            'testimony' => $request->testimony,
        ]);

        // 3. Return the success message
        return response()->json([
            'status'  => 'success',
            'message' => 'Thank you! Your testimony has been submitted successfully.'
        ], 201);
    }
}
