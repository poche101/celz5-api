<?php

namespace App\Policies;

use App\Models\CalendarEvent;
use App\Models\User;

class CalendarEventPolicy
{
    public function view(User $user, CalendarEvent $event): bool
    {
        return $event->user_id === $user->id ||
               $event->visibility === 'public' ||
               $event->subscriptions()->where('user_id', $user->id)
                    ->where('status', 'accepted')
                    ->exists();
    }

    public function update(User $user, CalendarEvent $event): bool
    {
        return $event->user_id === $user->id ||
               $event->subscriptions()->where('user_id', $user->id)
                    ->where('status', 'accepted')
                    ->whereIn('permission', ['editor', 'owner'])
                    ->exists();
    }

    public function delete(User $user, CalendarEvent $event): bool
    {
        return $event->user_id === $user->id ||
               $event->subscriptions()->where('user_id', $user->id)
                    ->where('status', 'accepted')
                    ->where('permission', 'owner')
                    ->exists();
    }

    public function manageImages(User $user, CalendarEvent $event): bool
    {
        return $this->update($user, $event);
    }

    public function updateOwnSubscription(User $user, CalendarEvent $event): bool
    {
        // User can update their own subscription status
        return $event->subscriptions()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->exists();
    }

    public function unsubscribe(User $user, CalendarEvent $event): bool
    {
        // User can unsubscribe themselves
        return $event->subscriptions()
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending', 'accepted'])
            ->exists();
    }

    public function inviteUsers(User $user, CalendarEvent $event): bool
    {
        return $this->update($user, $event);
    }
}