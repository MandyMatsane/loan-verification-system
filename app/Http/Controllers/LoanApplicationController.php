<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\LoanApplication;
use App\Services\ApplicationAssessor;
use App\Services\OcrService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LoanApplicationController extends Controller
{
    protected OcrService $ocrService;

    protected ApplicationAssessor $assessor;

    public function __construct(OcrService $ocrService, ApplicationAssessor $assessor)
    {
        $this->ocrService = $ocrService;
        $this->assessor = $assessor;
    }

    public function create()
    {
        if ($redirect = $this->redirectIfProfileIncomplete()) {
            return $redirect;
        }

        return view('applications.create');
    }

    public function store(Request $request)
    {
        if ($redirect = $this->redirectIfProfileIncomplete()) {
            return $redirect;
        }

        $submissionStartedAt = microtime(true);

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
            'id_document' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
            'payslip' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
            'bank_statement' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
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

        // hold the lock while OCR runs so a page visit cannot retry this application half-way through
        $lock = $this->assessor->lock($application, 600);
        $locked = $lock->get();

        try {
            $this->processUploads($application, $validated);
            $this->assessor->assess($application);
        } finally {
            if ($locked) {
                $lock->release();
            }
        }

        Log::info(sprintf('TIMING submission total took %.2fs', microtime(true) - $submissionStartedAt), [
            'application_id' => $application->id,
        ]);

        return redirect()->route('applications.confirmation', $application)
            ->with('success', 'Application submitted successfully.');
    }

    protected function processUploads(LoanApplication $application, array $validated): void
    {
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

            $ocrStartedAt = microtime(true);
            $this->ocrService->process($document);

            Log::info(sprintf(
                'TIMING ocr %s (%.1f KB) took %.2fs',
                $documentType,
                $file->getSize() / 1024,
                microtime(true) - $ocrStartedAt
            ), ['application_id' => $application->id]);
        }
    }

    public function confirmation(LoanApplication $application)
    {
        if ($application->user_id !== Auth::id()) {
            abort(403);
        }

        // retry one of this applicant's own stuck applications, this one first
        if ($this->assessor->retryDue(Auth::id(), $application->id) !== null) {
            $application->refresh();
        }

        $application->load('documents', 'aiAssessment');

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
            'file' => 'required|file|mimes:jpg,jpeg,png|max:5120',
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

        $lock = $this->assessor->lock($application, 600);
        $locked = $lock->get();

        try {
            $this->ocrService->process($document);
            $this->assessor->assess($application);
        } finally {
            if ($locked) {
                $lock->release();
            }
        }

        return redirect()->route('applications.show', $application)
            ->with('success', 'Document uploaded successfully.');
    }

    /**
     * Applicants need a 13-digit ID number and a date of birth before they apply:
     * the document check compares both with the ID document. Admins are not affected.
     */
    protected function redirectIfProfileIncomplete(): ?RedirectResponse
    {
        $user = Auth::user();

        if ($user->role === 'admin') {
            return null;
        }

        if (preg_match('/^\d{13}$/', (string) $user->id_number) && filled($user->date_of_birth)) {
            return null;
        }

        return redirect()->route('profile.edit')
            ->with('warning', 'Add your ID number and date of birth before you apply.');
    }
}
