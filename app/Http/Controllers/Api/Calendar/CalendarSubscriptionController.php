<?php

namespace App\Http\Controllers\Api\Calendar;

use App\Http\Controllers\Controller;
use App\Http\Requests\Calendar\Subscription\StoreSubscriptionRequest;
use App\Http\Requests\Calendar\Subscription\UpdateSubscriptionRequest;
use App\Http\Requests\Calendar\Subscription\InviteSubscriptionRequest;
use App\Models\CalendarEvent;
use App\Models\CalendarSubscription;
use App\Models\User;
use App\Services\CalendarSubscriptionService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;

class CalendarSubscriptionController extends Controller
{
    protected $subscriptionService;
    protected $notificationService;

    public function __construct(
        CalendarSubscriptionService $subscriptionService,
        NotificationService $notificationService
    ) {
        $this->subscriptionService = $subscriptionService;
        $this->notificationService = $notificationService;
    }

    /**
     * Get all subscriptions for an event
     */
    public function index(Request $request, CalendarEvent $event)
    {
        Gate::authorize('view', $event);

        $perPage = $request->input('per_page', 15);
        $status = $request->input('status');
        $permission = $request->input('permission');

        $query = $event->subscriptions()->with('user');

        if ($status) {
            $query->where('status', $status);
        }

        if ($permission) {
            $query->where('permission', $permission);
        }

        $subscriptions = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $subscriptions,
            'meta' => [
                'total' => $subscriptions->total(),
                'current_page' => $subscriptions->currentPage(),
                'per_page' => $subscriptions->perPage(),
                'last_page' => $subscriptions->lastPage()
            ]
        ]);
    }

    /**
     * Create a new subscription
     */
    public function store(StoreSubscriptionRequest $request, CalendarEvent $event)
    {
        Gate::authorize('update', $event);

        $data = $request->validated();
        $userId = $data['user_id'];

        // Check if user is already subscribed
        $existingSubscription = $event->subscriptions()
            ->where('user_id', $userId)
            ->first();

        if ($existingSubscription) {
            return response()->json([
                'success' => false,
                'message' => 'User is already subscribed to this event'
            ], 409);
        }

        $subscription = $this->subscriptionService->createSubscription(
            $event,
            $userId,
            $data['permission'] ?? 'viewer',
            $data['status'] ?? 'pending'
        );

        // Send notification to the invited user
        $this->notificationService->sendCalendarInvitation(
            $subscription->user,
            $event,
            auth()->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Subscription created successfully',
            'data' => $subscription->load('user')
        ], 201);
    }

    /**
     * Update a subscription
     */
    public function update(UpdateSubscriptionRequest $request, CalendarEvent $event, CalendarSubscription $subscription)
    {
        // Verify the subscription belongs to the event
        if ($subscription->calendar_event_id !== $event->id) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription not found for this event'
            ], 404);
        }

        // Check permissions
        if (auth()->id() === $subscription->user_id) {
            // User can only update their own status
            Gate::authorize('updateOwnSubscription', [$event, $subscription]);
        } else {
            // Event owner or user with editor permission can update subscription
            Gate::authorize('update', $event);
        }

        $data = $request->validated();
        
        $subscription = $this->subscriptionService->updateSubscription(
            $subscription,
            $data
        );

        // Send notification if status changed to accepted
        if (isset($data['status']) && $data['status'] === 'accepted') {
            $this->notificationService->sendCalendarInvitationAccepted(
                $event->user,
                $event,
                $subscription->user
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Subscription updated successfully',
            'data' => $subscription->load('user')
        ]);
    }

    /**
     * Delete a subscription
     */
    public function destroy(CalendarEvent $event, CalendarSubscription $subscription)
    {
        // Verify the subscription belongs to the event
        if ($subscription->calendar_event_id !== $event->id) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription not found for this event'
            ], 404);
        }

        // Check permissions
        if (auth()->id() === $subscription->user_id) {
            // User can unsubscribe themselves
            Gate::authorize('unsubscribe', [$event, $subscription]);
        } else {
            // Event owner or user with editor permission can remove subscription
            Gate::authorize('update', $event);
        }

        $subscription->delete();

        // Send notification if needed
        if (auth()->id() !== $subscription->user_id) {
            $this->notificationService->sendCalendarRemoval(
                $subscription->user,
                $event,
                auth()->user()
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Subscription removed successfully'
        ]);
    }

    /**
     * Invite multiple users to an event
     */
    public function invite(InviteSubscriptionRequest $request, CalendarEvent $event)
    {
        Gate::authorize('update', $event);

        $data = $request->validated();
        $userEmails = $data['emails'];
        $permission = $data['permission'] ?? 'viewer';

        $results = $this->subscriptionService->inviteMultipleUsers(
            $event,
            $userEmails,
            $permission,
            auth()->id()
        );

        return response()->json([
            'success' => true,
            'message' => 'Invitations sent successfully',
            'data' => $results
        ]);
    }

    /**
     * Get user's calendar subscriptions
     */
    public function userSubscriptions(Request $request)
    {
        $user = auth()->user();
        $perPage = $request->input('per_page', 15);
        $status = $request->input('status');
        $permission = $request->input('permission');

        $query = CalendarSubscription::with(['event', 'event.user'])
            ->where('user_id', $user->id);

        if ($status) {
            $query->where('status', $status);
        }

        if ($permission) {
            $query->where('permission', $permission);
        }

        $subscriptions = $query->latest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $subscriptions,
            'meta' => [
                'total' => $subscriptions->total(),
                'pending_count' => CalendarSubscription::where('user_id', $user->id)
                    ->where('status', 'pending')
                    ->count(),
                'accepted_count' => CalendarSubscription::where('user_id', $user->id)
                    ->where('status', 'accepted')
                    ->count()
            ]
        ]);
    }

    /**
     * Accept a subscription invitation
     */
    public function accept(CalendarEvent $event, CalendarSubscription $subscription)
    {
        // Verify the subscription belongs to the event and user
        if ($subscription->calendar_event_id !== $event->id || 
            $subscription->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription not found'
            ], 404);
        }

        if ($subscription->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Invitation has already been ' . $subscription->status
            ], 400);
        }

        $subscription->update([
            'status' => 'accepted',
            'accepted_at' => now()
        ]);

        // Notify event owner
        $this->notificationService->sendCalendarInvitationAccepted(
            $event->user,
            $event,
            auth()->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Invitation accepted successfully',
            'data' => $subscription->load(['event', 'event.user'])
        ]);
    }

    /**
     * Decline a subscription invitation
     */
    public function decline(CalendarEvent $event, CalendarSubscription $subscription)
    {
        // Verify the subscription belongs to the event and user
        if ($subscription->calendar_event_id !== $event->id || 
            $subscription->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription not found'
            ], 404);
        }

        if ($subscription->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Invitation has already been ' . $subscription->status
            ], 400);
        }

        $subscription->update([
            'status' => 'declined',
            'declined_at' => now()
        ]);

        // Notify event owner
        $this->notificationService->sendCalendarInvitationDeclined(
            $event->user,
            $event,
            auth()->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Invitation declined successfully'
        ]);
    }

    /**
     * Bulk update subscriptions
     */
    public function bulkUpdate(Request $request, CalendarEvent $event)
    {
        Gate::authorize('update', $event);

        $request->validate([
            'subscriptions' => ['required', 'array'],
            'subscriptions.*.id' => ['required', 'exists:calendar_subscriptions,id'],
            'subscriptions.*.permission' => ['nullable', 'in:viewer,editor'],
            'subscriptions.*.status' => ['nullable', 'in:pending,accepted,declined']
        ]);

        $results = [];
        
        DB::transaction(function () use ($event, $request, &$results) {
            foreach ($request->input('subscriptions') as $subscriptionData) {
                $subscription = CalendarSubscription::find($subscriptionData['id']);
                
                // Verify subscription belongs to this event
                if ($subscription->calendar_event_id !== $event->id) {
                    continue;
                }

                $updateData = array_filter($subscriptionData, function ($key) {
                    return $key !== 'id';
                }, ARRAY_FILTER_USE_KEY);

                $subscription->update($updateData);
                $results[] = $subscription->load('user');
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Subscriptions updated successfully',
            'data' => $results
        ]);
    }

    /**
     * Bulk delete subscriptions
     */
    public function bulkDestroy(Request $request, CalendarEvent $event)
    {
        Gate::authorize('update', $event);

        $request->validate([
            'subscription_ids' => ['required', 'array'],
            'subscription_ids.*' => ['exists:calendar_subscriptions,id']
        ]);

        $deletedCount = CalendarSubscription::where('calendar_event_id', $event->id)
            ->whereIn('id', $request->input('subscription_ids'))
            ->delete();

        return response()->json([
            'success' => true,
            'message' => "{$deletedCount} subscriptions deleted successfully",
            'data' => [
                'deleted_count' => $deletedCount
            ]
        ]);
    }

    /**
     * Check if a user is subscribed to an event
     */
    public function checkSubscription(CalendarEvent $event)
    {
        $userId = auth()->id();
        
        $subscription = $event->subscriptions()
            ->where('user_id', $userId)
            ->first();

        $isOwner = $event->user_id === $userId;
        $canEdit = $isOwner || ($subscription && in_array($subscription->permission, ['editor', 'owner']));

        return response()->json([
            'success' => true,
            'data' => [
                'is_subscribed' => !is_null($subscription),
                'is_owner' => $isOwner,
                'can_edit' => $canEdit,
                'subscription' => $subscription ? [
                    'id' => $subscription->id,
                    'permission' => $subscription->permission,
                    'status' => $subscription->status,
                    'created_at' => $subscription->created_at
                ] : null
            ]
        ]);
    }
}