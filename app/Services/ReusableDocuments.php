<?php

namespace App\Services;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Documents an applicant may reuse on a new application instead of uploading again:
 * their own, from an earlier application, uploaded in the last MAX_AGE_DAYS days.
 */
class ReusableDocuments
{
    public const TYPES = ['id_document', 'payslip', 'bank_statement'];

    public const MAX_AGE_DAYS = 90;

    /**
     * The latest reusable document per type; types with none are left out.
     *
     * @return array<string, Document>
     */
    public function latestFor(User $user): array
    {
        $latest = [];

        foreach (self::TYPES as $type) {
            $document = $this->query($user, $type)->latest('uploaded_at')->latest('id')->first();

            if ($document !== null) {
                $latest[$type] = $document;
            }
        }

        return $latest;
    }

    /**
     * The document with this id, only if the user may still reuse it for this type.
     */
    public function find(User $user, string $type, mixed $id): ?Document
    {
        if (! is_scalar($id) || ! ctype_digit((string) $id)) {
            return null;
        }

        return $this->query($user, $type)->with('ocrResult')->find((int) $id);
    }

    protected function query(User $user, string $type): Builder
    {
        return Document::query()
            ->where('type', $type)
            ->where('uploaded_at', '>=', now()->subDays(self::MAX_AGE_DAYS))
            ->whereHas('LoanApplication', fn (Builder $query) => $query->where('user_id', $user->id));
    }
}
