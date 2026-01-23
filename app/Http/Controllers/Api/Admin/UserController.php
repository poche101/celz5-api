<?php

nnamespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function index() { return User::all(); }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'role' => 'required'
        ]);

        // Generate a random password for the user
        $plainPassword = Str::random(10);
        
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
            'password' => Hash::make($plainPassword),
        ]);

        return response()->json([
            'message' => 'User created',
            'temporary_password' => $plainPassword, // Send this to the user
            'user' => $user
        ]);
    }

    public function update(Request $request, User $user)
    {
        $user->update($request->only('name', 'email', 'role'));
        return response()->json(['message' => 'User updated', 'user' => $user]);
    }

    public function destroy(User $user)
    {
        $user->delete();
        return response()->json(['message' => 'User deleted']);
    }
}ss