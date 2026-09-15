<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\LoanApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class LoanApplicationController extends Controller
{
    public function create()
    {
        return view('applications.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'amount_requested' => ['required', 'numeric', 'min:0.01'],
            'id_document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'payslip' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'bank_statement' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $application = LoanApplication::create([
            'user_id' => Auth::id(),
            'amount_requested' => $validated['amount_requested'],
            'status' => 'Pending',
        ]);

        foreach (['id_document', 'payslip', 'bank_statement'] as $documentType) {
            $file = $validated[$documentType];
            $path = $file->storeAs(
                'loan-applications/' . $application->id,
                $documentType . '-' . Str::uuid() . '-' . $file->getClientOriginalName(),
                'public'
            );

            Document::create([
                'application_id' => $application->id,
                'type' => $documentType,
                'file_path' => $path,
                'uploaded_at' => now(),
            ]);
        }

        return redirect()->route('applications.confirmation', $application)
            ->with('success', 'Application submitted successfully.');
    }

    public function confirmation(LoanApplication $application)
    {
        if ($application->user_id !== Auth::id()) {
            abort(403);
        }

        $application->load('documents');

        return view('applications.confirmation', compact('application'));
    }

    public function show(LoanApplication $application)
    {
        if ($application->user_id !== Auth::id()) {
            abort(403);
        }

        $application->load('documents', 'aiAssessment');

        return view('applications.show', compact('application'));
    }

    public function uploadDocument(Request $request, LoanApplication $application)
    {
        if ($application->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'type' => 'required|in:id_document,payslip,bank_statement',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $path = $request->file('file')->storeAs(
            'loan-applications/' . $application->id,
            $validated['type'] . '-' . Str::uuid() . '-' . $request->file('file')->getClientOriginalName(),
            'public'
        );

        Document::create([
            'application_id' => $application->id,
            'type' => $validated['type'],
            'file_path' => $path,
            'uploaded_at' => now(),
        ]);

        return redirect()->route('applications.show', $application)
            ->with('success', 'Document uploaded successfully.');
    }
}

