<?php

namespace App\Services\RegistrarRecords;

use App\Enums\TemplateDocumentType;
use App\Models\Learner;
use App\Models\ReportCardRecord;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FinalRecordScopeResolver
{
    public function query(): Builder
    {
        return ReportCardRecord::query()
            ->where('is_finalized', true)
            ->whereIn('document_type', [
                TemplateDocumentType::Sf9,
                TemplateDocumentType::Sf10,
            ])
            ->whereHas('sectionRoster', fn (Builder $query) => $query->where('is_official', true));
    }

    public function assertRecordIsVisible(ReportCardRecord $reportCardRecord): void
    {
        $isVisible = $this->query()
            ->whereKey($reportCardRecord->getKey())
            ->exists();

        if (! $isVisible) {
            throw new NotFoundHttpException;
        }
    }

    public function assertLearnerHasVisibleRecords(Learner $learner): void
    {
        $hasVisibleRecords = $this->query()
            ->where('learner_id', $learner->id)
            ->exists();

        if (! $hasVisibleRecords) {
            throw new NotFoundHttpException;
        }
    }
}
