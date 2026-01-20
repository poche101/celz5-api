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
     * Store a new video and poster
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'poster'      => 'required|image|mimes:jpeg,png,jpg|max:2048', // 2MB max for poster
            'video'       => 'required|mimes:mp4,mov,avi|max:51200',     // 50MB max for video
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Handle Poster Upload
        $posterPath = $request->file('poster')->store('posters', 'public');

        // Handle Video Upload
        $videoPath = $request->file('video')->store('videos', 'public');

        $video = Video::create([
            'title'        => $request->title,
            'description'  => $request->description,
            'poster_path'  => Storage::url($posterPath), // Returns /storage/posters/filename.jpg
            'video_path'   => Storage::url($videoPath),  // Returns /storage/videos/filename.mp4
            'user_id'      => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Video uploaded successfully',
            'video'   => $video
        ], 201);
    }

    /**
     * Delete a video and its files
     */
    public function destroy(Video $video)
    {
        // Remove the "/storage/" prefix to get the relative path for deletion
        $oldPoster = str_replace('/storage/', '', $video->poster_path);
        $oldVideo  = str_replace('/storage/', '', $video->video_path);

        Storage::disk('public')->delete([$oldPoster, $oldVideo]);

        $video->delete();

        return response()->json(['message' => 'Video and associated files deleted.']);
    }
}
