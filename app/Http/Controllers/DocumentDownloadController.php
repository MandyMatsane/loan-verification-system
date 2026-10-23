<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DocumentDownloadController extends Controller
{
    /**
     * Stream an uploaded document to an admin or to the applicant who owns it.
     */
    public function __invoke(Request $request, Document $document): Response
    {
        $user = $request->user();
        $ownerId = $document->LoanApplication?->user_id;

        if ($user->role !== 'admin' && ($ownerId === null || (int) $ownerId !== (int) $user->id)) {
            abort(403);
        }

        if (! $document->fileExists()) {
            return response('File no longer available', 404)->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        return $document->storage()->download($document->file_path, $document->displayName());
    }
}
