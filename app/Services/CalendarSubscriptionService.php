<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\CalendarSubscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CalendarSubscriptionService
{
    public function createSubscription(
        CalendarEvent $event,
        int $userId,
        string $permission = 'viewer',
        string $status = 'pending'
    ): CalendarSubscription {
        return DB::transaction(function () use ($event, $userId, $permission, $status) {
            // Remove any existing subscription for this user and event
            CalendarSubscription::where('calendar_event_id', $event->id)
                ->where('user_id', $userId)
                ->delete();

            return CalendarSubscription::create([
                'calendar_event_id' => $event->id,
                'user_id' => $userId,
                'permission' => $permission,
                'status' => $status,
                'subscribed_at' => now(),
                'accepted_at' => $status === 'accepted' ? now() : null
            ]);
        });
    }

    public function updateSubscription(CalendarSubscription $subscription, array $data): CalendarSubscription
    {
        $updates = [];

        if (isset($data['permission'])) {
            $updates['permission'] = $data['permission'];
        }

        if (isset($data['status'])) {
            $updates['status'] = $data['status'];
            
            if ($data['status'] === 'accepted') {
                $updates['accepted_at'] = now();
            } elseif ($data['status'] === 'declined') {
                $updates['declined_at'] = now();
            }
        }

        if (!empty($updates)) {
            $subscription->update($updates);
        }

        return $subscription;
    }

    public function inviteMultipleUsers(
        CalendarEvent $event,
        array $emails,
        string $permission = 'viewer',
        int $inviterId
    ): array {
        $users = User::whereIn('email', $emails)->get();
        $results = [
            'successful' => [],
            'failed' => []
        ];

        DB::beginTransaction();

        try {
            foreach ($users as $user) {
                // Skip if user is the event owner
                if ($user->id === $event->user_id) {
                    $results['failed'][] = [
                        'email' => $user->email,
                        'reason' => 'User is the event owner'
                    ];
                    continue;
                }

                // Check for existing subscription
                $existing = CalendarSubscription::where('calendar_event_id', $event->id)
                    ->where('user_id', $user->id)
                    ->first();

                if ($existing) {
                    if ($existing->status === 'pending') {
                        $results['failed'][] = [
                            'email' => $user->email,
                            'reason' => 'Already invited (pending)'
                        ];
                    } elseif ($existing->status === 'accepted') {
                        $results['failed'][] = [
                            'email' => $user->email,
                            'reason' => 'Already subscribed'
                        ];
                    } else {
                        // If declined, update to pending
                        $existing->update([
                            'status' => 'pending',
                            'permission' => $permission,
                            'declined_at' => null
                        ]);
                        $results['successful'][] = $user->email;
                    }
                    continue;
                }

                // Create new subscription
                CalendarSubscription::create([
                    'calendar_event_id' => $event->id,
                    'user_id' => $user->id,
                    'permission' => $permission,
                    'status' => 'pending',
                    'subscribed_at' => now()
                ]);

                $results['successful'][] = $user->email;
            }

            DB::commit();
            return $results;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function getUserEvents(int $userId, array $filters = [])
    {
        $query = CalendarEvent::whereHas('subscriptions', function ($q) use ($userId) {
            $q->where('user_id', $userId)
              ->where('status', 'accepted');
        })->orWhere('user_id', $userId) // Include events they own
          ->with(['user', 'images']);

        if (isset($filters['start_date']) && isset($filters['end_date'])) {
            $query->whereBetween('start_time', [
                $filters['start_date'],
                $filters['end_date']
            ]);
        }

        if (isset($filters['type'])) {
            $query->whereIn('type', (array)$filters['type']);
        }

        return $query->orderBy('start_time', 'asc')->get();
    }

    public function getPendingInvitations(int $userId)
    {
        return CalendarSubscription::with(['event', 'event.user'])
            ->where('user_id', $userId)
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function countPendingInvitations(int $userId): int
    {
        return CalendarSubscription::where('user_id', $userId)
            ->where('status', 'pending')
            ->count();
    }
}