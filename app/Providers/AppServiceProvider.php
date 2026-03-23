<?php

namespace App\Providers;

use App\Models\GradeLevel;
use App\Models\GradeSubmission;
use App\Models\GradingPeriod;
use App\Models\GradingSheetExport;
use App\Models\ImportBatch;
use App\Models\ReportCardRecord;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Subject;
use App\Models\TeacherLoad;
use App\Models\Template;
use App\Models\User;
use App\Policies\GradeLevelPolicy;
use App\Policies\GradeSubmissionPolicy;
use App\Policies\GradingPeriodPolicy;
use App\Policies\GradingSheetExportPolicy;
use App\Policies\ImportBatchPolicy;
use App\Policies\ReportCardRecordPolicy;
use App\Policies\SchoolYearPolicy;
use App\Policies\SectionPolicy;
use App\Policies\SubjectPolicy;
use App\Policies\TeacherLoadPolicy;
use App\Policies\TemplatePolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(SchoolYear::class, SchoolYearPolicy::class);
        Gate::policy(GradingPeriod::class, GradingPeriodPolicy::class);
        Gate::policy(GradeLevel::class, GradeLevelPolicy::class);
        Gate::policy(GradeSubmission::class, GradeSubmissionPolicy::class);
        Gate::policy(GradingSheetExport::class, GradingSheetExportPolicy::class);
        Gate::policy(ImportBatch::class, ImportBatchPolicy::class);
        Gate::policy(ReportCardRecord::class, ReportCardRecordPolicy::class);
        Gate::policy(Section::class, SectionPolicy::class);
        Gate::policy(Subject::class, SubjectPolicy::class);
        Gate::policy(TeacherLoad::class, TeacherLoadPolicy::class);
        Gate::policy(Template::class, TemplatePolicy::class);
    }
}
