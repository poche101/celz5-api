<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /**
     * Display a listing of all payments with filters.
     */
    public function index(Request $request)
    {
        $query = Payment::with('user:id,name,email');

        // Filter by type (tithe, offering, partnership)
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by status (success, pending, failed)
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return response()->json([
            'status' => 'success',
            'total_revenue' => $query->where('status', 'success')->sum('amount'),
            'data' => $query->latest()->paginate(20)
        ]);
    }

    /**
     * Get detailed stats for the dashboard.
     */
    public function stats()
    {
        return response()->json([
            'summary' => [
                'tithe' => Payment::where('type', 'tithe')->where('status', 'success')->sum('amount'),
                'offering' => Payment::where('type', 'offering')->where('status', 'success')->sum('amount'),
                'partnership' => Payment::where('type', 'partnership')->where('status', 'success')->sum('amount'),
            ],
            'recent_transactions' => Payment::with('user')->latest()->limit(5)->get()
        ]);
    }

    /**
     * Manually verify or update a payment status.
     */
    public function updateStatus(Request $request, $id)
    {
        $payment = Payment::findOrFail($id);

        $request->validate([
            'status' => 'required|in:success,failed,pending'
        ]);

        $payment->update(['status' => $request->status]);

        return response()->json([
            'message' => 'Payment status updated successfully',
            'payment' => $payment
        ]);
    }
}
