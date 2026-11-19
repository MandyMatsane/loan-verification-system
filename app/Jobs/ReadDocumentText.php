<?php

namespace App\Jobs;

use App\Models\Document;
use App\Models\OcrResult;
use App\Services\OcrService;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reads a document's text once the response has been sent, for documents no check uses (the bank statement).
 * Not queued: it runs in the same PHP process after the response, so no queue worker is needed.
 * If it never runs, the document simply has no text and the admin page says so.
 */
class ReadDocumentText
{
    use Dispatchable;

    public function __construct(public int $documentId)
    {
    }

    public function handle(OcrService $ocrService): void
    {
        try {
            $document = Document::with('ocrResult')->find($this->documentId);

            if ($document === null || $document->ocrResult !== null) {
                return;
            }

            $startedAt = microtime(true);
            $result = $ocrService->process($document);

            // an empty result tells the admin page it was read, rather than "not read yet" forever
            if ($result === null && $document->fileExists()) {
                OcrResult::create(['document_id' => $document->id, 'extracted_text' => '']);
            }

            Log::info(sprintf(
                'TIMING ocr %s (%.1f KB) after the response took %.2fs',
                $document->type,
                $document->fileExists() ? $document->storage()->size($document->file_path) / 1024 : 0,
                microtime(true) - $startedAt
            ), ['application_id' => $document->application_id, 'document_id' => $document->id]);
        } catch (Throwable $e) {
            Log::error('Reading document text after the response failed.', [
                'document_id' => $this->documentId,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
