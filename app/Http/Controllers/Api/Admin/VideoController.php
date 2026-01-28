<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class VideoController extends Controller
{
    /**
     * Get all archive videos for the Flutter UI
     */
    public function index()
    {
        // Fetch videos ordered by latest episode
        $videos = Video::orderBy('episode', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $videos
        ]);
    }

    /**
     * Store a new video and poster
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title'       => 'required|string|max:255',
            'episode'     => 'required|integer',
            'duration'    => 'required|string', // Format: "45:10"
            'description' => 'nullable|string',
            'poster'      => 'required|image|mimes:jpeg,png,jpg|max:5120', // Up to 5MB
            'video'       => 'required|mimes:mp4,mov,avi|max:102400',    // Up to 100MB
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Store files in the 'public' disk
        $posterPath = $request->file('poster')->store('posters', 'public');
        $videoPath = $request->file('video')->store('videos', 'public');

        $video = Video::create([
            'title'       => $request->title,
            'episode'     => $request->episode,
            'duration'    => $request->duration,
            'description' => $request->description,
            // We store the relative path. Our Model Accessors will handle the full URL.
            'poster_path' => 'storage/' . $posterPath,
            'video_path'  => 'storage/' . $videoPath,
            'user_id'     => auth()->id() ?? 1, // Fallback to ID 1 if auth is not set up yet
        ]);

        return response()->json([
            'message' => 'Higher Life Archive Updated',
            'video'   => $video
        ], 201);
    }

    /**
     * Delete a video and its physical files
     */
    public function destroy(Video $video)
    {
        // Convert 'storage/posters/file.jpg' back to 'posters/file.jpg' for deletion
        $oldPoster = str_replace('storage/', '', $video->poster_path);
        $oldVideo  = str_replace('storage/', '', $video->video_path);

        Storage::disk('public')->delete([$oldPoster, $oldVideo]);

        $video->delete();

        return response()->json(['message' => 'Video and associated files deleted.']);
    }
}
