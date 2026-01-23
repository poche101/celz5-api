<?php

namespace App\Http\Requests\Calendar\Subscription;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'permission' => ['required', 'in:viewer,editor'],
            'status' => ['nullable', 'in:pending,accepted,declined']
        ];
    }
}