<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\LoanApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoanApplicationController extends Controller
{
    public function create()
    {
        return view('applications.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            // 'applicant_name' => 'required|string|max:255',
            // 'applicant_email' => 'required|email|max:255',
            'amount_requested' => 'required|numeric|min:0',
        ]);

        // Create a new loan application associated with the authenticated user
        $application = LoanApplication::create([
            'user_id' => Auth::id(),
            'amount_requested' => $validated['amount_requested'],
            'status' => 'pending',
        ]);

        // Redirect to the application details page with a success message
        return redirect()->route('applications.show', $application)
        ->with('success', 'Application submitted successfully. Please upload the required documents.');
    }

    public function show(LoanApplication $application)
    {
        // Ensure the authenticated user owns the application
        if ($application->user_id !== Auth::id()) {
            abort(403);
        }
        
        // Load related documents and AI assessment for display
        $application->load('documents', 'aiAssessment');

        // Return the application details view with the application data
        return view('applications.show', compact('application'));
    }

    public function uploadDocument(Request $request, LoanApplication $application)
    {
        // Ensure the authenticated user owns the application
        if ($application->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'type' => 'required|in:id,payslip,bank_statement',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        // Store the uploaded file in a secure location
        $path = $request->file('file')->store('documents', 'local');

        // Create a new document record associated with the loan application
        Document::create([
            'application_id' => $application->id,
            'type' => $validated['type'],
            'file_path' => $path,
            'uploaded_at' => now(),
        ]);

        // Redirect back to the application details page with a success message
        return redirect()->route('applications.show', $application)
            ->with('success', 'Document uploaded successfully.');
    }
}

