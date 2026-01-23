<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Testimony;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use Maatwebsite\Excel\Facades\Excel;

class AdminTestimonyController extends Controller
{
    // 1. Get all testimonies for the dashboard list
    public function index()
    {
        return response()->json(
            Testimony::with('user:id,email')->latest()->get()
        );
    }

    // 2. Admin posts a testimony (via Form)
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title'     => 'required|string|max:255',
            'format'    => 'required|in:text,video',
            'full_name' => 'required|string',
            'testimony' => 'required_if:format,text',
            'video'     => 'required_if:format,video|file|mimes:mp4,mov|max:50000',
        ]);

        if ($validator->fails()) return response()->json($validator->errors(), 422);

        $videoPath = $request->hasFile('video')
            ? $request->file('video')->store('testimonies/videos', 'public')
            : null;

        $testimony = Testimony::create([
            'user_id'   => auth()->id(), // Admin as creator
            'title'     => $request->title,
            'format'    => $request->format,
            'full_name' => $request->full_name,
            'group'     => 'Admin Post',
            'church'    => 'Admin Post',
            'testimony' => $request->testimony,
            'video_url' => $videoPath,
        ]);

        return response()->json(['message' => 'Posted successfully', 'data' => $testimony]);
    }

    // 3. Update Testimony
    public function update(Request $request, $id)
    {
        $testimony = Testimony::findOrFail($id);
        $testimony->update($request->all());
        return response()->json(['message' => 'Updated successfully', 'data' => $testimony]);
    }

    // 4. Delete Testimony
    public function destroy($id)
    {
        $testimony = Testimony::findOrFail($id);
        if ($testimony->video_url) Storage::disk('public')->delete($testimony->video_url);
        $testimony->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }

    // 5. Download Testimonies as CSV
   public function downloadExcel()
    {
        return Excel::download(new TestimoniesExport, 'testimonies.xlsx');
    }

    /**
     * Download as Word (.docx)
     */
    public function downloadWord()
    {
        $testimonies = Testimony::all();
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();

        $section->addTitle('Testimony Submissions Report', 1);
        $section->addText('Generated on: ' . now()->toDayDateTimeString());
        $section->addTextBreak(1);

        foreach ($testimonies as $t) {
            $section->addText("Full Name: {$t->full_name}", ['bold' => true]);
            $section->addText("Title: {$t->title}");
            $section->addText("Format: {$t->format}");
            $section->addText("Content: {$t->testimony}");
            $section->addText("Date: " . $t->created_at->format('Y-m-d H:i'));
            $section->addHorizontalLine();
            $section->addTextBreak(1);
        }

        $fileName = 'testimonies_' . now()->format('Y-m-d') . '.docx';
        $tempFile = tempnam(sys_get_temp_dir(), 'word');
        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save($tempFile);

        return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);
    }
}
