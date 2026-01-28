<?php

// app/Http/Controllers/Api/EventRegistrationController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EventRegistrationController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:50',
            'full_name' => 'required|string|max:255',
            'phone_number' => 'required|string|max:20',
            'email_address' => 'required|email|max:255',
            'group_name' => 'required|string|max:255',
            'church_name' => 'required|string|max:255',
            'cell_name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $registration = EventRegistration::create($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Registration Successful',
            'data' => $registration
        ], 201);
    }
}
