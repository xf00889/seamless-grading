<?php

namespace App\Services\AdviserYearEnd;

use App\Enums\TemplateDocumentType;
use App\Models\GradingPeriod;
use App\Models\ReportCardRecord;
use App\Models\SectionRoster;
use App\Models\Template;
use Illuminate\Support\Collection;

class Sf10DraftReviewContextBuilder
{
    public function build(
        SectionRoster $sectionRoster,
        ?GradingPeriod $finalGradingPeriod,
        ?Template $template,
        string $sourceHash,
        array $exportBlockers,
    ): array {
        $history = $this->history($sectionRoster, $finalGradingPeriod);
        $latestRecord = $history->first();
        $latestFinalized = $history->firstWhere('is_finalized', true);
        $finalizationBlockers = $exportBlockers;

        if ($latestRecord === null) {
            $finalizationBlockers[] = 'Generate an SF10 draft export version before finalizing this learner record.';
        } elseif ((bool) $latestRecord['is_finalized']) {
            $finalizationBlockers[] = 'The latest SF10 export version is already finalized.';
        } elseif (
            $template !== null
            && (
                (int) $latestRecord['template_id'] !== $template->id
                || (int) $latestRecord['template_version'] !== $template->version
                || (string) ($latestRecord['source_hash'] ?? '') !== $sourceHash
            )
        ) {
            $finalizationBlockers[] = 'The latest SF10 draft no longer matches the current approved year-end data or active template. Generate a new draft version before finalizing.';
        }

        return [
            'history' => $history->all(),
            'draft_review' => $latestRecord !== null && ! $latestRecord['is_finalized'] ? $latestRecord : null,
            'finalization_status' => $this->finalizationStatus($history, $latestFinalized),
            'finalization_blockers' => array_values(array_unique($finalizationBlockers)),
            'finalize_ready' => $finalizationBlockers === [],
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function history(SectionRoster $sectionRoster, ?GradingPeriod $finalGradingPeriod): Collection
    {
        if ($finalGradingPeriod === null) {
            return collect();
        }

        return ReportCardRecord::query()
            ->with(['template:id,name,version', 'generatedBy:id,name', 'finalizedBy:id,name'])
            ->where('section_roster_id', $sectionRoster->id)
            ->where('grading_period_id', $finalGradingPeriod->id)
            ->where('document_type', TemplateDocumentType::Sf10)
            ->orderByDesc('record_version')
            ->get()
            ->map(fn (ReportCardRecord $reportCardRecord): array => [
                'model' => $reportCardRecord,
                'id' => $reportCardRecord->id,
                'record_version' => $reportCardRecord->record_version,
                'template_id' => $reportCardRecord->template_id,
                'template_name' => $reportCardRecord->template?->name ?? 'Unknown template',
                'template_version' => $reportCardRecord->template_version,
                'file_name' => $reportCardRecord->file_name,
                'generated_at' => $reportCardRecord->generated_at?->format('M d, Y g:i A'),
                'generated_by' => $reportCardRecord->generatedBy?->name ?? 'Unknown user',
                'is_finalized' => $reportCardRecord->is_finalized,
                'finalized_at' => $reportCardRecord->finalized_at?->format('M d, Y g:i A'),
                'finalized_by' => $reportCardRecord->finalizedBy?->name,
                'source_hash' => data_get($reportCardRecord->payload, 'source_hash'),
            ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $history
     * @param  array<string, mixed>|null  $latestFinalized
     */
    private function finalizationStatus(Collection $history, ?array $latestFinalized): array
    {
        if ($history->isEmpty()) {
            return [
                'label' => 'Not exported',
                'tone' => 'slate',
                'description' => 'No SF10 draft version has been generated yet for this learner.',
            ];
        }

        $latest = $history->first();

        if ((bool) $latest['is_finalized']) {
            return [
                'label' => 'Finalized',
                'tone' => 'emerald',
                'description' => 'Version '.$latest['record_version'].' is finalized and now eligible for the registrar final-records repository.',
            ];
        }

        if ($latestFinalized !== null) {
            return [
                'label' => 'Pending re-finalization',
                'tone' => 'amber',
                'description' => 'Version '.$latest['record_version'].' is newer than finalized version '.$latestFinalized['record_version'].'. Review and finalize the latest draft before registrar handoff.',
            ];
        }

        return [
            'label' => 'Draft review pending',
            'tone' => 'amber',
            'description' => 'The latest SF10 draft still needs explicit adviser finalization before it becomes an official registrar-visible record.',
        ];
    }
}
