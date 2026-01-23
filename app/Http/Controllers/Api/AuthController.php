<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Password;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Handle manual user registration (Simplified)
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:255',
            'email'     => 'required|string|email|max:255|unique:users',
            'phone'     => 'required|string|max:20|unique:users',
            'password'  => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name'      => $request->full_name,
            'email'     => $request->email,
            'phone'     => $request->phone,
            'password'  => Hash::make($request->password),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ], 201);
    }

    /**
     * Handle manual login
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid login credentials'], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ]);
    }

    /**
     * Update additional profile information
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user(); // Requires 'auth:sanctum' middleware

        $validator = Validator::make($request->all(), [
            'title'    => 'nullable|string|max:10',
            'birthday' => 'nullable|date',
            'group'    => 'nullable|string',
            'church'   => 'nullable|string',
            'cell'     => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user->update($request->only([
            'title', 'birthday', 'group', 'church', 'cell'
        ]));

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user
        ]);
    }

    /**
     * Handles the login/callback from KingsChat OAuth
     */
    public function handleKingsChatCallback(Request $request)
    {
        try {
            $kcUser = Socialite::driver('kingschat')->user();

            $user = User::updateOrCreate(
                ['kingschat_id' => $kcUser->getId()],
                [
                    'name'  => $kcUser->getName(),
                    'email' => $kcUser->getEmail(),
                    'profile_picture' => $kcUser->getAvatar(),
                ]
            );

            $token = $user->createToken('auth_token')->plainTextToken;

            // Check if profile needs more info
            $is_incomplete = empty($user->cell) || empty($user->church) || empty($user->group);

            return response()->json([
                'access_token' => $token,
                'token_type' => 'Bearer',
                'is_new' => $user->wasRecentlyCreated,
                'is_incomplete' => $is_incomplete,
                'user' => $user
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'KingsChat login failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Password Reset: Send Link
     */
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => 'Reset link sent to your email.'])
            : response()->json(['error' => 'Unable to send reset link.'], 400);
    }

    /**
     * Password Reset: Process
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => 'Password has been successfully reset.'])
            : response()->json(['error' => 'Invalid token or email.'], 400);
    }
}
