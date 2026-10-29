<?php

namespace App\Http\Controllers;

use App\Jobs\ReadDocumentText;
use App\Models\Document;
use App\Models\LoanApplication;
use App\Models\OcrResult;
use App\Services\ApplicationAssessor;
use App\Services\OcrService;
use App\Services\RepaymentCalculator;
use App\Services\ReusableDocuments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoanApplicationController extends Controller
{
    /**
     * Document types whose text is read after the applicant has their response. Only for documents no check uses:
     * every document is checked now, so the list is empty, but the mechanism is kept.
     */
    public const READ_AFTER_RESPONSE = [];

    protected OcrService $ocrService;

    protected ApplicationAssessor $assessor;

    protected ReusableDocuments $reusableDocuments;

    protected RepaymentCalculator $repaymentCalculator;

    public function __construct(OcrService $ocrService, ApplicationAssessor $assessor, ReusableDocuments $reusableDocuments, RepaymentCalculator $repaymentCalculator)
    {
        $this->ocrService = $ocrService;
        $this->assessor = $assessor;
        $this->reusableDocuments = $reusableDocuments;
        $this->repaymentCalculator = $repaymentCalculator;
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

        $maxAmount = (int) config('loan.max_amount');
        $minTerm = (int) config('loan.min_term');
        $maxTerm = (int) config('loan.max_term');
        $termMessage = "Choose a term between {$minTerm} and {$maxTerm} months";

        $validated = $request->validate([
            'amount_requested' => ['required', 'numeric', 'min:1', 'max:' . $maxAmount],
            'no_of_dependents' => ['required', 'integer', 'min:0'],
            'education' => ['required', 'in:Graduate,Not Graduate'],
            'loan_term' => ['required', 'integer', 'min:' . $minTerm, 'max:' . $maxTerm],
            'cibil_score_band' => ['required', 'in:Poor,Fair,Good,Excellent'],
            'residential_assets_value' => ['required', 'numeric', 'min:0'],
            'commercial_assets_value' => ['required', 'numeric', 'min:0'],
            'luxury_assets_value' => ['required', 'numeric', 'min:0'],
            'bank_asset_value' => ['required', 'numeric', 'min:0'],
            // each slot takes a new upload, or "{type}_reuse" with the id of an earlier document
            'id_document' => ['required_without:id_document_reuse', 'nullable', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
            'payslip' => ['required_without:payslip_reuse', 'nullable', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
            'bank_statement' => ['required_without:bank_statement_reuse', 'nullable', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
        ], [
            'amount_requested.min' => 'The least you can apply for is R1',
            'amount_requested.max' => 'The most you can apply for is R' . number_format($maxAmount),
            'loan_term.integer' => $termMessage,
            'loan_term.min' => $termMessage,
            'loan_term.max' => $termMessage,
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

        // worked out here from the validated amount and term, never taken from the form
        $estimate = $this->repaymentCalculator->estimate((float) $validated['amount_requested'], (int) $validated['loan_term']);

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
            'interest_rate' => $estimate['rate'],
            'monthly_repayment' => $estimate['monthly_repayment'],
            'total_repayable' => $estimate['total_repayable'],
            // the checks use the income as it was when they applied
            'stated_monthly_income' => $request->user()->monthly_income,
        ]);

        // hold the lock while OCR runs so a page visit cannot retry this application half-way through
        $lock = $this->assessor->lock($application, 600);
        $locked = $lock->get();

        try {
            $readLater = $this->processUploads($application, $validated, $reused);
            $this->assessor->assess($application);
        } finally {
            if ($locked) {
                $lock->release();
            }
        }

        Log::info(sprintf('TIMING submission total took %.2fs', microtime(true) - $submissionStartedAt), [
            'application_id' => $application->id,
            'read_after_response' => count($readLater),
        ]);

        $response = redirect()->route('applications.confirmation', $application)
            ->with('success', 'Application submitted successfully.');

        if ($readLater !== []) {
            foreach ($readLater as $document) {
                ReadDocumentText::dispatchAfterResponse($document->id);
            }

            $this->finishResponseFirst($response);
        }

        return $response;
    }

    /**
     * Apache's PHP module keeps the connection open while work runs after the response. A known length and
     * "Connection: close" let the browser treat the response as complete and follow the redirect straight away.
     */
    protected function finishResponseFirst(RedirectResponse $response): void
    {
        $response->headers->set('Content-Length', (string) strlen((string) $response->getContent()));
        $response->headers->set('Connection', 'close');

        // keep going if the browser goes away before the work is done
        ignore_user_abort(true);
    }

    /**
     * @param  array<string, Document>  $reused  checked earlier documents, by type
     * @return list<Document>  new uploads whose text is read after the response
     */
    protected function processUploads(LoanApplication $application, array $validated, array $reused = []): array
    {
        $readLater = [];

        foreach (ReusableDocuments::TYPES as $documentType) {
            if (isset($reused[$documentType])) {
                $this->reuseDocument($application, $reused[$documentType]);

                continue;
            }

            $file = $validated[$documentType];
            $contentHash = hash_file(Document::HASH_ALGORITHM, $file->getRealPath()) ?: null;
            $path = $file->storeAs(
                'loan-applications/' . $application->id,
                $documentType . '-' . Str::uuid() . '-' . $file->getClientOriginalName(),
                Document::PRIVATE_DISK
            );

            $document = Document::create([
                'application_id' => $application->id,
                'type' => $documentType,
                'file_path' => $path,
                'disk' => Document::PRIVATE_DISK,
                'content_hash' => $contentHash,
                'uploaded_at' => now(),
            ]);

            if (in_array($documentType, self::READ_AFTER_RESPONSE, true)) {
                $readLater[] = $document;

                continue;
            }

            $ocrStartedAt = microtime(true);
            $this->ocrService->process($document);

            Log::info(sprintf(
                'TIMING ocr %s (%.1f KB) took %.2fs',
                $documentType,
                $file->getSize() / 1024,
                microtime(true) - $ocrStartedAt
            ), ['application_id' => $application->id]);
        }

        return $readLater;
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
            'disk' => $source->disk ?: Document::LEGACY_DISK,
            'uploaded_at' => $source->uploaded_at,
            'reused_from_document_id' => $source->id,
            'content_hash' => $source->content_hash,
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

        $contentHash = hash_file(Document::HASH_ALGORITHM, $request->file('file')->getRealPath()) ?: null;
        $path = $request->file('file')->storeAs(
            'loan-applications/' . $application->id,
            $validated['type'] . '-' . Str::uuid() . '-' . $request->file('file')->getClientOriginalName(),
            Document::PRIVATE_DISK
        );

        $document = Document::create([
            'application_id' => $application->id,
            'type' => $validated['type'],
            'file_path' => $path,
            'disk' => Document::PRIVATE_DISK,
            'content_hash' => $contentHash,
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
     * Applicants need a 13-digit ID number, a date of birth and a monthly income above 0 before they apply:
     * the document check compares the first two with the ID document and the income with the payslip,
     * and the affordability check uses the income. Admins are not affected.
     */
    protected function redirectIfProfileIncomplete(): ?RedirectResponse
    {
        $user = Auth::user();

        if ($user->role === 'admin') {
            return null;
        }

        if (preg_match('/^\d{13}$/', (string) $user->id_number) && filled($user->date_of_birth) && (float) $user->monthly_income > 0) {
            return null;
        }

        return redirect()->route('profile.edit')
            ->with('warning', 'Add your ID number, date of birth and monthly income before you apply.');
    }
}
