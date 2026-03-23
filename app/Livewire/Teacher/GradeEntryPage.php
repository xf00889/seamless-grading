<?php

namespace App\Livewire\Teacher;

use App\Actions\Teacher\GradeEntry\SaveGradeEntryDraftAction;
use App\Actions\Teacher\GradeEntry\SubmitGradeEntryAction;
use App\Models\GradingPeriod;
use App\Models\TeacherLoad;
use App\Models\User;
use App\Services\TeacherGradeEntry\GradeEntryPageDataBuilder;
use Livewire\Component;

class GradeEntryPage extends Component
{
    public array $form = ['grades' => []];

    public array $gradingPeriodSummary = [];

    public array $gradingRules = [];

    public array $loadSummary = [];

    public array $rows = [];

    public array $workflow = [];

    public ?string $feedbackMessage = null;

    public string $feedbackTone = 'emerald';

    public int $gradingPeriodId;

    public int $teacherLoadId;

    public function mount(TeacherLoad $teacherLoad, GradingPeriod $gradingPeriod): void
    {
        $this->teacherLoadId = $teacherLoad->id;
        $this->gradingPeriodId = $gradingPeriod->id;

        $this->refreshState();
    }

    public function updated(string $property): void
    {
        if (! str_starts_with($property, 'form.grades.') || ! str_ends_with($property, '.grade')) {
            return;
        }

        $this->validateOnly($property, $this->validationRules(), $this->validationMessages());
    }

    public function saveDraft(): void
    {
        $this->resetErrorBag();
        $this->feedbackMessage = null;

        app(SaveGradeEntryDraftAction::class)->handle(
            $this->authenticatedUser(),
            $this->teacherLoad(),
            $this->gradingPeriod(),
            $this->form['grades'],
        );

        $this->feedbackMessage = $this->workflow['status']['value'] === 'returned'
            ? 'Corrections saved. Adviser remarks remain attached until you resubmit.'
            : 'Draft saved successfully.';
        $this->feedbackTone = 'emerald';

        $this->refreshState();
    }

    public function submitGrades(): void
    {
        $this->resetErrorBag();
        $this->feedbackMessage = null;

        app(SubmitGradeEntryAction::class)->handle(
            $this->authenticatedUser(),
            $this->teacherLoad(),
            $this->gradingPeriod(),
            $this->form['grades'],
        );

        $this->feedbackMessage = 'Grades submitted successfully.';
        $this->feedbackTone = 'emerald';

        $this->refreshState();
    }

    public function render()
    {
        return view('livewire.teacher.grade-entry-page');
    }

    protected function validationRules(): array
    {
        return [
            'form.grades.*.grade' => [
                'nullable',
                'numeric',
                'decimal:0,'.$this->gradingRules['decimal_places'],
                'between:'.$this->gradingRules['minimum'].','.$this->gradingRules['maximum'],
            ],
        ];
    }

    protected function validationMessages(): array
    {
        return [
            'form.grades.*.grade.decimal' => 'Grades may contain up to '.$this->gradingRules['decimal_places'].' decimal places only.',
            'form.grades.*.grade.between' => 'Grades must be between '.$this->gradingRules['minimum'].' and '.$this->gradingRules['maximum'].'.',
            'form.grades.*.grade.numeric' => 'Grades must be numeric values.',
        ];
    }

    private function authenticatedUser(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    private function gradingPeriod(): GradingPeriod
    {
        return GradingPeriod::query()->findOrFail($this->gradingPeriodId);
    }

    private function refreshState(): void
    {
        $state = app(GradeEntryPageDataBuilder::class)->build(
            $this->teacherLoad(),
            $this->gradingPeriod(),
        );

        $this->loadSummary = $state['load'];
        $this->gradingPeriodSummary = $state['grading_period'];
        $this->workflow = $state['workflow'];
        $this->gradingRules = $state['grading_rules'];
        $this->rows = $state['rows'];
        $this->form['grades'] = collect($this->rows)
            ->mapWithKeys(fn (array $row): array => [
                $row['section_roster_id'] => ['grade' => $row['grade']],
            ])
            ->all();
    }

    private function teacherLoad(): TeacherLoad
    {
        return TeacherLoad::query()->findOrFail($this->teacherLoadId);
    }
}
