<?php

namespace App\Services;

use App\Models\Document;
use App\Models\OcrResult;
use Illuminate\Support\Facades\Log;
use Throwable;
use thiagoalessio\TesseractOCR\TesseractOCR;

class OcrService
{
    /**
     * Tesseract's OpenMP threads fight over a small shared CPU (Render): one thread was 3 to 10 times faster
     * there and gave the same text.
     */
    public const THREAD_LIMIT = 1;

    /** Larger images are read from a copy scaled down to this longest side; smaller ones are never enlarged. */
    public const MAX_SIDE = 1600;

    /** Images above this many pixels are read as they are, so decoding them cannot exhaust PHP's memory. */
    public const MAX_PIXELS_TO_SHRINK = 25_000_000;

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
        $copy = $this->shrunkCopy($fullPath, $document->id);

        try {
            $extractedText = $this->readText($copy ?? $fullPath);
        } catch (Throwable $e) {
            if ($copy === null) {
                return $this->failed($document, $e);
            }

            Log::warning('OCR of the scaled-down copy failed, reading the original.', [
                'document_id' => $document->id,
                'message' => $e->getMessage(),
            ]);

            try {
                $extractedText = $this->readText($fullPath);
            } catch (Throwable $e) {
                return $this->failed($document, $e);
            }
        } finally {
            if ($copy !== null) {
                @unlink($copy);
            }
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

    /**
     * A temporary copy of the image with its longest side at most MAX_SIDE, or null to read the original:
     * when it is already small enough, or anything goes wrong. The caller deletes the copy.
     */
    public function shrunkCopy(string $path, ?int $documentId = null): ?string
    {
        $startedAt = microtime(true);
        $copy = null;

        try {
            $size = @getimagesize($path);

            if ($size === false || ! in_array($size[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
                return null;
            }

            [$width, $height, $type] = $size;
            $longest = max($width, $height);

            if ($longest <= self::MAX_SIDE || $width * $height > self::MAX_PIXELS_TO_SHRINK) {
                Log::info(sprintf('TIMING ocr image %dx%d read as it is', $width, $height), ['document_id' => $documentId]);

                return null;
            }

            $scale = self::MAX_SIDE / $longest;
            $newWidth = max(1, (int) round($width * $scale));
            $newHeight = max(1, (int) round($height * $scale));

            $source = $type === IMAGETYPE_PNG ? @imagecreatefrompng($path) : @imagecreatefromjpeg($path);

            if ($source === false) {
                return null;
            }

            // transparent parts of a PNG become white, as they look on screen, not black
            $resized = imagecreatetruecolor($newWidth, $newHeight);
            imagefill($resized, 0, 0, imagecolorallocate($resized, 255, 255, 255));
            imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            $copy = tempnam(sys_get_temp_dir(), 'ocr-copy-');

            // PNG keeps the copy lossless; low compression keeps the write quick
            if ($copy === false || ! imagepng($resized, $copy, 1)) {
                if ($copy !== false) {
                    @unlink($copy);
                }

                return null;
            }

            Log::info(sprintf(
                'TIMING ocr image %dx%d scaled to %dx%d took %.2fs',
                $width,
                $height,
                $newWidth,
                $newHeight,
                microtime(true) - $startedAt
            ), ['document_id' => $documentId]);

            return $copy;
        } catch (Throwable $e) {
            if (is_string($copy)) {
                @unlink($copy);
            }

            Log::warning('Could not scale the image down for OCR, reading the original.', [
                'document_id' => $documentId,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function readText(string $path): string
    {
        // the Tesseract process inherits this; set here too in case the server environment does not have it
        putenv('OMP_THREAD_LIMIT=' . self::THREAD_LIMIT);

        $command = new TesseractCommand();
        $command->executable = self::tesseractExecutable();

        // one Tesseract run per call: no version check, no second pass
        return (new TesseractOCR($path, $command))->run();
    }

    /**
     * The full path of the tesseract program on the PATH, so the wrapper finds it with a file check instead of
     * starting a shell for every document. "tesseract" when it is not found: the wrapper then reports it as before.
     */
    public static function tesseractExecutable(): string
    {
        $names = PHP_OS_FAMILY === 'Windows' ? ['tesseract.exe', 'tesseract'] : ['tesseract'];

        foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $directory) {
            foreach ($names as $name) {
                $candidate = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . $name;

                if ($directory !== '' && is_file($candidate) && is_executable($candidate)) {
                    return $candidate;
                }
            }
        }

        return 'tesseract';
    }

    protected function failed(Document $document, Throwable $e): ?OcrResult
    {
        Log::error('OCR processing failed for document upload.', [
            'document_id' => $document->id,
            'file_path' => $document->file_path,
            'message' => $e->getMessage(),
        ]);

        return null;
    }
}
