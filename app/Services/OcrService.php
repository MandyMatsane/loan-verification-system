<?php

namespace App\Services;

use App\Models\Document;
use App\Models\OcrResult;
use Illuminate\Support\Facades\Log;
use Throwable;
use thiagoalessio\TesseractOCR\TesseractOCR;

class OcrService
{
    public function process(Document $document): ?OcrResult
    {
        $filePath = $document->file_path;

        if (! $document->fileExists()) {
            Log::warning('OCR skipped because the stored file is missing.', [
                'document_id' => $document->id,
                'file_path' => $filePath,
            ]);

            return null;
        }

        $fullPath = $document->storage()->path($filePath);

        try {
            $extractedText = (new TesseractOCR($fullPath))->run();
        } catch (Throwable $e) {
            Log::error('OCR processing failed for document upload.', [
                'document_id' => $document->id,
                'file_path' => $filePath,
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        return OcrResult::updateOrCreate(
            ['document_id' => $document->id],
            [
                'extracted_text' => $extractedText,
                'extracted_fields' => null,
                'confidence_score' => null,
            ]
        );
    }
}
