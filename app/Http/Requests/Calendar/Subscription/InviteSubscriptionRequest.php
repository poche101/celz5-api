<?php

namespace App\Http\Requests\Calendar\Subscription;

use Illuminate\Foundation\Http\FormRequest;

class InviteSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'emails' => ['required', 'array', 'max:50'],
            'emails.*' => ['required', 'email', 'exists:users,email'],
            'permission' => ['required', 'in:viewer,editor'],
            'message' => ['nullable', 'string', 'max:500']
        ];
    }
}