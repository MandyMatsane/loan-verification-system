<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\LoanApplication;
use App\Services\MlPredictionService;
use App\Services\OcrService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class LoanApplicationController extends Controller
{
    protected OcrService $ocrService;

    protected MlPredictionService $mlPredictionService;

    public function __construct(OcrService $ocrService, MlPredictionService $mlPredictionService)
    {
        $this->ocrService = $ocrService;
        $this->mlPredictionService = $mlPredictionService;
    }

    public function create()
    {
        return view('applications.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'amount_requested' => ['required', 'numeric', 'min:0.01'],
            'no_of_dependents' => ['required', 'integer', 'min:0'],
            'education' => ['required', 'in:Graduate,Not Graduate'],
            'loan_term' => ['required', 'integer', 'min:1'],
            'cibil_score_band' => ['required', 'in:Poor,Fair,Good,Excellent'],
            'residential_assets_value' => ['required', 'numeric', 'min:0'],
            'commercial_assets_value' => ['required', 'numeric', 'min:0'],
            'luxury_assets_value' => ['required', 'numeric', 'min:0'],
            'bank_asset_value' => ['required', 'numeric', 'min:0'],
            'id_document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'payslip' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'bank_statement' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $application = LoanApplication::create([
            'user_id' => Auth::id(),
            'amount_requested' => $validated['amount_requested'],
            'status' => 'Pending',
            'no_of_dependents' => $validated['no_of_dependents'],
            'education' => $validated['education'],
            'loan_term' => $validated['loan_term'],
            'cibil_score_band' => $validated['cibil_score_band'],
            'residential_assets_value' => $validated['residential_assets_value'],
            'commercial_assets_value' => $validated['commercial_assets_value'],
            'luxury_assets_value' => $validated['luxury_assets_value'],
            'bank_asset_value' => $validated['bank_asset_value'],
        ]);

        foreach (['id_document', 'payslip', 'bank_statement'] as $documentType) {
            $file = $validated[$documentType];
            $path = $file->storeAs(
                'loan-applications/' . $application->id,
                $documentType . '-' . Str::uuid() . '-' . $file->getClientOriginalName(),
                'public'
            );

            $document = Document::create([
                'application_id' => $application->id,
                'type' => $documentType,
                'file_path' => $path,
                'uploaded_at' => now(),
            ]);

            $this->ocrService->process($document);
        }

        $this->mlPredictionService->predict($application);

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

        $document = Document::create([
            'application_id' => $application->id,
            'type' => $validated['type'],
            'file_path' => $path,
            'uploaded_at' => now(),
        ]);

        $this->ocrService->process($document);
        $this->mlPredictionService->predict($application);

        return redirect()->route('applications.show', $application)
            ->with('success', 'Document uploaded successfully.');
    }
}
