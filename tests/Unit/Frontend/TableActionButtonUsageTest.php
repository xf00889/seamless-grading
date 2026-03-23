<?php

namespace Tests\Unit\Frontend;

use PHPUnit\Framework\TestCase;

class TableActionButtonUsageTest extends TestCase
{
    public function test_row_action_views_use_the_shared_table_action_button_component(): void
    {
        $basePath = dirname(__DIR__, 3);
        $views = [
            'resources/views/admin/academic-setup/grade-levels/index.blade.php',
            'resources/views/admin/academic-setup/grading-periods/index.blade.php',
            'resources/views/admin/academic-setup/school-years/index.blade.php',
            'resources/views/admin/academic-setup/sections/index.blade.php',
            'resources/views/admin/academic-setup/subjects/index.blade.php',
            'resources/views/admin/sf1-imports/batches/index.blade.php',
            'resources/views/admin/sf1-imports/batches/show.blade.php',
            'resources/views/admin/template-management/templates/history.blade.php',
            'resources/views/admin/template-management/templates/index.blade.php',
            'resources/views/admin/user-management/teacher-loads/index.blade.php',
            'resources/views/admin/user-management/users/index.blade.php',
            'resources/views/adviser/dashboard.blade.php',
            'resources/views/adviser/sections/index.blade.php',
            'resources/views/adviser/sections/tracker.blade.php',
            'resources/views/adviser/sf10/show.blade.php',
            'resources/views/adviser/sf9/show.blade.php',
            'resources/views/registrar/records/index.blade.php',
            'resources/views/registrar/records/learner.blade.php',
            'resources/views/registrar/records/show.blade.php',
            'resources/views/teacher/grading-sheet/show.blade.php',
            'resources/views/teacher/loads/index.blade.php',
            'resources/views/teacher/returned-submissions/index.blade.php',
        ];

        foreach ($views as $view) {
            $contents = file_get_contents($basePath.'/'.$view);

            $this->assertIsString($contents, $view);
            $this->assertStringContainsString('x-table-action-button', $contents, $view);
            $this->assertStringContainsString('icon=', $contents, $view);
        }
    }
}
