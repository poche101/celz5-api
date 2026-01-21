<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\Comment;
use App\Models\Attendance;
use Illuminate\Http\Request;

class AdminStreamController extends Controller
{
    // Create Program
    public function storeProgram(Request $request)
    {
        $data = $request->validate([
            'title' => 'required',
            'stream_link' => 'required|url',
            'start_time' => 'required|date'
        ]);

        $program = Program::create($data);
        return response()->json($program, 201);
    }

    // Get Attendance List
    public function getAttendance($programId)
    {
        return Attendance::where('program_id', $programId)
            ->with('user:id,name,email,church,group')
            ->get();
    }

    // Moderate Comments
    public function deleteComment($id)
    {
        Comment::destroy($id);
        return response()->json(['message' => 'Comment removed by admin']);
    }
}
