<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\Comment;
use App\Models\Attendance;
use Illuminate\Http\Request;

class StreamController extends Controller
{
    /**
     * Protect the entire controller
     * Only logged-in users (via Sanctum) can access these methods
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    // 1. Enter Stream (Handles Name & Attendance)
    public function enterStream(Request $request, $programId)
    {
        // Because of the middleware, $user is guaranteed to exist here
        $user = $request->user();

        // If user hasn't set a display name yet, use their registered name as default
        // or require them to provide one.
        if (!$user->stream_display_name) {
            if ($request->has('display_name')) {
                $user->update(['stream_display_name' => $request->display_name]);
            } else {
                // Default to their full name if no display name is provided yet
                $user->update(['stream_display_name' => $user->name]);
            }
        }

        // Record Attendance (Once per program)
        Attendance::firstOrCreate([
            'user_id' => $user->id,
            'program_id' => $programId
        ]);

        $program = Program::findOrFail($programId);

        return response()->json([
            'program' => $program,
            'user_details' => [
                'name' => $user->stream_display_name,
                'picture' => $user->profile_picture,
                'group' => $user->group,
                'church' => $user->church
            ]
        ]);
    }

    // 2. Post Comment
    public function postComment(Request $request, $programId)
    {
        $request->validate(['message' => 'required|string']);

        $comment = Comment::create([
            'user_id' => auth()->id(),
            'program_id' => $programId,
            'message' => $request->message
        ]);

        // Load user info for immediate UI update
        $comment->load('user:id,name,stream_display_name,profile_picture');

        return response()->json(['message' => 'Comment posted', 'comment' => $comment]);
    }

    // 3. Get Comments (With Pagination)
    public function getComments($programId)
    {
        return Comment::where('program_id', $programId)
            ->with('user:id,name,stream_display_name,profile_picture,group,church')
            ->latest()
            ->paginate(15);
    }
}
