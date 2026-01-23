<?php

namespace App\Http\Requests\Calendar\Subscription;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\CalendarSubscription;

class UpdateSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $subscription = $this->route('subscription');
        $user = auth()->user();

        $rules = [
            'permission' => ['sometimes', 'in:viewer,editor'],
            'status' => ['sometimes', 'in:pending,accepted,declined']
        ];

        // Users can only update their own subscription status
        if ($subscription && $subscription->user_id === $user->id) {
            $rules = [
                'status' => ['required', 'in:accepted,declined']
            ];
        }

        return $rules;
    }
}