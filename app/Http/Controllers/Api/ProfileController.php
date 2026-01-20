<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /**
     * Get the authenticated user's profile with QR code
     */
    public function getProfile(Request $request)
{
    $user = $request->user();

    // Change format from 'png' to 'svg'
    $qrCode = QrCode::format('svg') 
                    ->size(300)
                    ->generate($user->unique_id ?? 'NO-ID');

    return response()->json([
        'user' => $user,
        // Update the mime type to image/svg+xml
        'qr_code_base64' => 'data:image/svg+xml;base64,' . base64_encode($qrCode)
    ]);
}

    /**
     * Update profile details and upload profile picture
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'title' => 'sometimes|string',
            'name' => 'sometimes|string|max:255',
            'birthday' => 'sometimes|date',
            'group' => 'sometimes|string',
            'church' => 'sometimes|string',
            'cell' => 'sometimes|string',
            'profile_picture' => 'sometimes|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        // Handle File Upload
        if ($request->hasFile('profile_picture')) {
            // Delete old picture if exists
            if ($user->profile_picture) {
                Storage::disk('public')->delete($user->profile_picture);
            }

            $path = $request->file('profile_picture')->store('profile_pics', 'public');
            $user->profile_picture = asset('storage/' . $path);
        }

        $user->update($request->except('profile_picture'));

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user
        ]);
    }
}
