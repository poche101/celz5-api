<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\Comment;
use App\Models\Attendance;
use Illuminate\Http\Request;

class StreamController extends Controller
{
    // 1. Enter Stream (Handles Name & Attendance)
    public function enterStream(Request $request, $programId)
    {
        $user = $request->user();

        // If user hasn't set a display name yet, require it
        if (!$user->stream_display_name) {
            $request->validate(['display_name' => 'required|string|max:255']);
            $user->update(['stream_display_name' => $request->display_name]);
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
