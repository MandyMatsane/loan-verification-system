<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\LoanApplication;
use App\Models\OcrResult;
use App\Services\ApplicationAssessor;
use App\Services\OcrService;
use App\Services\ReusableDocuments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoanApplicationController extends Controller
{
    protected OcrService $ocrService;

    protected ApplicationAssessor $assessor;

    protected ReusableDocuments $reusableDocuments;

    public function __construct(OcrService $ocrService, ApplicationAssessor $assessor, ReusableDocuments $reusableDocuments)
    {
        $this->ocrService = $ocrService;
        $this->assessor = $assessor;
        $this->reusableDocuments = $reusableDocuments;
    }

    public function create()
    {
        if ($redirect = $this->redirectIfProfileIncomplete()) {
            return $redirect;
        }

        $reusable = $this->reusableDocuments->latestFor(Auth::user());

        return view('applications.create', compact('reusable'));
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
            // each slot takes a new upload, or "{type}_reuse" with the id of an earlier document
            'id_document' => ['required_without:id_document_reuse', 'nullable', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
            'payslip' => ['required_without:payslip_reuse', 'nullable', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
            'bank_statement' => ['required_without:bank_statement_reuse', 'nullable', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
        ]);

        // never trust the id from the form: it must still be this user's own, recent document of that type
        $reused = [];
        $reuseErrors = [];
        foreach (ReusableDocuments::TYPES as $documentType) {
            $reuseId = $request->input($documentType . '_reuse');

            if (blank($reuseId)) {
                continue;
            }

            $document = $this->reusableDocuments->find($request->user(), $documentType, $reuseId);

            if ($document === null) {
                $reuseErrors[$documentType] = 'That earlier document can no longer be reused. Upload a new file.';

                continue;
            }

            $reused[$documentType] = $document;
        }

        if ($reuseErrors !== []) {
            throw ValidationException::withMessages($reuseErrors);
        }

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
            $this->processUploads($application, $validated, $reused);
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

    /**
     * @param  array<string, Document>  $reused  checked earlier documents, by type
     */
    protected function processUploads(LoanApplication $application, array $validated, array $reused = []): void
    {
        foreach (ReusableDocuments::TYPES as $documentType) {
            if (isset($reused[$documentType])) {
                $this->reuseDocument($application, $reused[$documentType]);

                continue;
            }

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

    /**
     * Point a new document at the earlier stored file and copy its OCR text; OCR is not run again.
     * It keeps the original upload date, so the 90-day limit counts from the real upload.
     */
    protected function reuseDocument(LoanApplication $application, Document $source): void
    {
        $document = Document::create([
            'application_id' => $application->id,
            'type' => $source->type,
            'file_path' => $source->file_path,
            'uploaded_at' => $source->uploaded_at,
            'reused_from_document_id' => $source->id,
        ]);

        if ($source->ocrResult !== null) {
            OcrResult::create([
                'document_id' => $document->id,
                'extracted_text' => $source->ocrResult->extracted_text,
                'extracted_fields' => $source->ocrResult->extracted_fields,
                'confidence_score' => $source->ocrResult->confidence_score,
            ]);
        }

        Log::info('Reused an earlier document', [
            'application_id' => $application->id,
            'document_id' => $document->id,
            'reused_from_document_id' => $source->id,
        ]);
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
