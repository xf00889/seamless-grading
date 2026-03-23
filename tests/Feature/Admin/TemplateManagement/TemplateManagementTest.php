<?php

namespace Tests\Feature\Admin\TemplateManagement;

use App\Enums\RoleName;
use App\Enums\TemplateAuditAction;
use App\Enums\TemplateDocumentType;
use App\Models\GradeLevel;
use App\Models\Template;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TemplateManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->seed([
            PermissionSeeder::class,
            RoleSeeder::class,
        ]);
    }

    public function test_admin_can_view_template_management_pages(): void
    {
        $admin = $this->createUserWithRole(RoleName::Admin->value);
        $gradeLevel = GradeLevel::factory()->create(['name' => 'Grade 7']);

        $template = Template::factory()->create([
            'code' => 'report-card',
            'name' => 'SF9 Grade 7',
            'document_type' => TemplateDocumentType::Sf9,
            'grade_level_id' => $gradeLevel->id,
            'scope_key' => $this->scopeKey($gradeLevel),
        ]);

        $this->syncCompleteFieldMaps($template);

        $this->actingAs($admin)
            ->get(route('admin.template-management'))
            ->assertOk()
            ->assertSeeText('Template Management');

        $this->get(route('admin.template-management.templates.index'))->assertOk();
        $this->get(route('admin.template-management.templates.create'))->assertOk();
        $this->get(route('admin.template-management.templates.show', $template))
            ->assertOk()
            ->assertSeeText('Edit mappings');
        $this->get(route('admin.template-management.templates.history', $template))
            ->assertOk()
            ->assertSeeText('Version History');
        $this->get(route('admin.template-management.templates.mappings.edit', $template))
            ->assertOk()
            ->assertSeeText('Edit Field Mappings');
    }

    public function test_non_admin_users_are_redirected_from_template_management_get_routes(): void
    {
        $template = Template::factory()->create();

        $routes = [
            route('admin.template-management'),
            route('admin.template-management.templates.index'),
            route('admin.template-management.templates.create'),
            route('admin.template-management.templates.show', $template),
            route('admin.template-management.templates.history', $template),
            route('admin.template-management.templates.mappings.edit', $template),
        ];

        foreach ([RoleName::Teacher, RoleName::Adviser, RoleName::Registrar] as $role) {
            $this->actingAs($this->createUserWithRole($role->value));

            foreach ($routes as $route) {
                $this->get($route)->assertRedirect(route('access.denied'));
            }
        }
    }

    public function test_non_admin_users_cannot_write_template_management_records(): void
    {
        $teacher = $this->createUserWithRole(RoleName::Teacher->value);
        $template = Template::factory()->create();

        $this->actingAs($teacher)
            ->post(route('admin.template-management.templates.store'), [
                'document_type' => TemplateDocumentType::Sf9->value,
                'grade_level_id' => null,
                'code' => 'blocked-template',
                'name' => 'Blocked Template',
                'description' => 'Restricted upload',
                'file' => $this->makeTemplateUpload('blocked.xlsx', 'xlsx'),
            ])
            ->assertForbidden();

        $this->put(route('admin.template-management.templates.mappings.update', $template), [
            'mappings' => [],
        ])->assertForbidden();

        $this->post(route('admin.template-management.templates.activate', $template))
            ->assertForbidden();

        $this->post(route('admin.template-management.templates.deactivate', $template))
            ->assertForbidden();
    }

    public function test_admin_can_upload_templates_with_safe_storage_and_scope_aware_versioning(): void
    {
        $admin = $this->createUserWithRole(RoleName::Admin->value);
        $gradeLevelA = GradeLevel::factory()->create(['name' => 'Grade 7']);
        $gradeLevelB = GradeLevel::factory()->create(['name' => 'Grade 8']);

        $this->actingAs($admin)
            ->post(route('admin.template-management.templates.store'), [
                'document_type' => TemplateDocumentType::Sf9->value,
                'grade_level_id' => $gradeLevelA->id,
                'code' => 'report-card',
                'name' => 'SF9 Grade 7',
                'description' => 'Initial report-card layout.',
                'file' => $this->makeTemplateUpload('Unsafe Original Name.xlsx', 'xlsx'),
            ])
            ->assertRedirect();

        $firstTemplate = Template::query()
            ->where('document_type', TemplateDocumentType::Sf9)
            ->where('grade_level_id', $gradeLevelA->id)
            ->where('code', 'report-card')
            ->firstOrFail();

        $this->assertSame(1, $firstTemplate->version);
        $this->assertSame($this->scopeKey($gradeLevelA), $firstTemplate->scope_key);
        $this->assertStringNotContainsString('Unsafe', $firstTemplate->file_path);
        Storage::disk('local')->assertExists($firstTemplate->file_path);
        $this->assertCount(count(config('templates.definitions.sf9')), $firstTemplate->fieldMaps);
        $this->assertDatabaseHas('template_audit_logs', [
            'template_id' => $firstTemplate->id,
            'acted_by' => $admin->id,
            'action' => TemplateAuditAction::Uploaded->value,
        ]);

        $this->post(route('admin.template-management.templates.store'), [
            'document_type' => TemplateDocumentType::Sf9->value,
            'grade_level_id' => $gradeLevelA->id,
            'code' => 'report-card',
            'name' => 'SF9 Grade 7 Revised',
            'description' => 'Second revision.',
            'file' => $this->makeTemplateUpload('second-upload.xlsx', 'xlsx'),
        ])->assertRedirect();

        $this->post(route('admin.template-management.templates.store'), [
            'document_type' => TemplateDocumentType::Sf9->value,
            'grade_level_id' => $gradeLevelB->id,
            'code' => 'report-card',
            'name' => 'SF9 Grade 8',
            'description' => 'Different scope.',
            'file' => $this->makeTemplateUpload('grade-8.xlsx', 'xlsx'),
        ])->assertRedirect();

        $this->assertDatabaseHas('templates', [
            'document_type' => TemplateDocumentType::Sf9->value,
            'grade_level_id' => $gradeLevelA->id,
            'code' => 'report-card',
            'version' => 2,
        ]);
        $this->assertDatabaseHas('templates', [
            'document_type' => TemplateDocumentType::Sf9->value,
            'grade_level_id' => $gradeLevelB->id,
            'code' => 'report-card',
            'version' => 1,
        ]);
    }

    public function test_mapping_validation_and_activation_blockers_are_enforced(): void
    {
        $admin = $this->createUserWithRole(RoleName::Admin->value);

        $this->actingAs($admin)
            ->post(route('admin.template-management.templates.store'), [
                'document_type' => TemplateDocumentType::GradingSheet->value,
                'grade_level_id' => null,
                'code' => 'grading-sheet',
                'name' => 'Quarterly Grading Sheet',
                'description' => 'Teacher-facing export layout.',
                'file' => $this->makeTemplateUpload('grading-sheet.xlsx', 'xlsx'),
            ])
            ->assertRedirect();

        $template = Template::query()
            ->where('document_type', TemplateDocumentType::GradingSheet)
            ->where('code', 'grading-sheet')
            ->firstOrFail();

        $showRoute = route('admin.template-management.templates.show', $template);
        $editRoute = route('admin.template-management.templates.mappings.edit', $template);

        $this->from($showRoute)
            ->post(route('admin.template-management.templates.activate', $template))
            ->assertRedirect($showRoute)
            ->assertSessionHasErrors('mappings.school_year_name.target_cell');

        $invalidMappings = $this->completeMappingsPayload(TemplateDocumentType::GradingSheet);
        $invalidMappings['school_year_name']['target_cell'] = '1A';

        $this->from($editRoute)
            ->put(route('admin.template-management.templates.mappings.update', $template), [
                'mappings' => $invalidMappings,
            ])
            ->assertRedirect($editRoute)
            ->assertSessionHasErrors('mappings.school_year_name.target_cell');

        $duplicateMappings = $this->completeMappingsPayload(TemplateDocumentType::GradingSheet);
        $duplicateMappings['school_year_name']['target_cell'] = 'A1';
        $duplicateMappings['grading_period_label']['target_cell'] = 'A1';

        $this->put(route('admin.template-management.templates.mappings.update', $template), [
            'mappings' => $duplicateMappings,
        ])->assertRedirect($showRoute);

        $this->from($showRoute)
            ->post(route('admin.template-management.templates.activate', $template))
            ->assertRedirect($showRoute)
            ->assertSessionHasErrors('mappings.school_year_name.target_cell');

        $this->assertDatabaseHas('templates', [
            'id' => $template->id,
            'is_active' => false,
        ]);

        $this->get($showRoute)
            ->assertOk()
            ->assertSeeText('Current blockers')
            ->assertSeeText('Broken');
    }

    public function test_admin_can_update_mappings_activate_and_deactivate_templates_with_scope_aware_single_active_rule_and_audit_logs(): void
    {
        $admin = $this->createUserWithRole(RoleName::Admin->value);
        $gradeLevelA = GradeLevel::factory()->create(['name' => 'Grade 7']);
        $gradeLevelB = GradeLevel::factory()->create(['name' => 'Grade 8']);

        $existingActive = Template::factory()->create([
            'code' => 'report-card',
            'document_type' => TemplateDocumentType::Sf9,
            'grade_level_id' => $gradeLevelA->id,
            'scope_key' => $this->scopeKey($gradeLevelA),
            'is_active' => true,
            'active_scope_key' => $this->activeScopeKey(TemplateDocumentType::Sf9, $gradeLevelA),
            'activated_at' => now()->subDay(),
        ]);
        $candidate = Template::factory()->create([
            'code' => 'report-card',
            'document_type' => TemplateDocumentType::Sf9,
            'grade_level_id' => $gradeLevelA->id,
            'scope_key' => $this->scopeKey($gradeLevelA),
            'version' => 2,
            'is_active' => false,
            'active_scope_key' => null,
        ]);
        $differentScope = Template::factory()->create([
            'code' => 'report-card',
            'document_type' => TemplateDocumentType::Sf9,
            'grade_level_id' => $gradeLevelB->id,
            'scope_key' => $this->scopeKey($gradeLevelB),
            'is_active' => true,
            'active_scope_key' => $this->activeScopeKey(TemplateDocumentType::Sf9, $gradeLevelB),
            'activated_at' => now()->subDay(),
        ]);

        $this->syncCompleteFieldMaps($existingActive);
        $this->syncCompleteFieldMaps($candidate);
        $this->syncCompleteFieldMaps($differentScope);

        $this->actingAs($admin)
            ->put(route('admin.template-management.templates.mappings.update', $candidate), [
                'mappings' => $this->completeMappingsPayload(TemplateDocumentType::Sf9),
            ])
            ->assertRedirect(route('admin.template-management.templates.show', $candidate));

        $this->assertDatabaseHas('template_audit_logs', [
            'template_id' => $candidate->id,
            'acted_by' => $admin->id,
            'action' => TemplateAuditAction::MappingsUpdated->value,
        ]);

        $this->post(route('admin.template-management.templates.activate', $candidate))
            ->assertRedirect(route('admin.template-management.templates.show', $candidate));

        $existingActive->refresh();
        $candidate->refresh();
        $differentScope->refresh();

        $this->assertFalse($existingActive->is_active);
        $this->assertTrue($candidate->is_active);
        $this->assertTrue($differentScope->is_active);
        $this->assertSame(
            $this->activeScopeKey(TemplateDocumentType::Sf9, $gradeLevelA),
            $candidate->active_scope_key,
        );

        $this->assertDatabaseHas('template_audit_logs', [
            'template_id' => $existingActive->id,
            'acted_by' => $admin->id,
            'action' => TemplateAuditAction::Deactivated->value,
        ]);
        $this->assertDatabaseHas('template_audit_logs', [
            'template_id' => $candidate->id,
            'acted_by' => $admin->id,
            'action' => TemplateAuditAction::Activated->value,
        ]);

        $this->post(route('admin.template-management.templates.deactivate', $candidate))
            ->assertRedirect(route('admin.template-management.templates.show', $candidate));

        $this->assertDatabaseHas('templates', [
            'id' => $candidate->id,
            'is_active' => false,
            'active_scope_key' => null,
        ]);
        $this->assertDatabaseHas('template_audit_logs', [
            'template_id' => $candidate->id,
            'acted_by' => $admin->id,
            'action' => TemplateAuditAction::Deactivated->value,
        ]);
    }

    public function test_template_index_filters_and_history_view_show_status_and_versions(): void
    {
        $admin = $this->createUserWithRole(RoleName::Admin->value);
        $gradeLevel = GradeLevel::factory()->create(['name' => 'Grade 7']);

        $versionOne = Template::factory()->create([
            'code' => 'report-card',
            'name' => 'SF9 Grade 7 v1',
            'document_type' => TemplateDocumentType::Sf9,
            'grade_level_id' => $gradeLevel->id,
            'scope_key' => $this->scopeKey($gradeLevel),
            'version' => 1,
            'is_active' => false,
        ]);
        $versionTwo = Template::factory()->create([
            'code' => 'report-card',
            'name' => 'SF9 Grade 7 v2',
            'document_type' => TemplateDocumentType::Sf9,
            'grade_level_id' => $gradeLevel->id,
            'scope_key' => $this->scopeKey($gradeLevel),
            'version' => 2,
            'is_active' => true,
            'active_scope_key' => $this->activeScopeKey(TemplateDocumentType::Sf9, $gradeLevel),
            'activated_at' => now(),
        ]);
        $otherTemplate = Template::factory()->create([
            'code' => 'grading-sheet',
            'name' => 'Quarterly Grading Sheet',
            'document_type' => TemplateDocumentType::GradingSheet,
            'grade_level_id' => null,
            'scope_key' => 'global',
        ]);

        $this->syncCompleteFieldMaps($versionOne);
        $this->syncCompleteFieldMaps($versionTwo);
        $this->syncCompleteFieldMaps($otherTemplate);

        $this->actingAs($admin)
            ->get(route('admin.template-management.templates.index', [
                'search' => 'SF9 Grade 7',
                'document_type' => TemplateDocumentType::Sf9->value,
                'grade_level_id' => $gradeLevel->id,
                'status' => 'active',
            ]))
            ->assertOk()
            ->assertSeeText('SF9 Grade 7 v2')
            ->assertSeeText('Complete')
            ->assertSeeText('Active')
            ->assertDontSeeText('Quarterly Grading Sheet');

        $this->get(route('admin.template-management.templates.history', $versionTwo))
            ->assertOk()
            ->assertSeeText('Version 1')
            ->assertSeeText('Version 2');
    }

    private function createUserWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeTemplateUpload(string $filename, string $extension = 'xlsx'): UploadedFile
    {
        return match ($extension) {
            'xlsx' => UploadedFile::fake()->createWithContent(
                $filename,
                $this->genericWorkbookBinary(pathinfo($filename, PATHINFO_FILENAME)),
            ),
            default => UploadedFile::fake()->create($filename, 128, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        };
    }

    private function syncCompleteFieldMaps(Template $template): void
    {
        $this->storeTemplateWorkbook($template);

        $definitions = collect(config('templates.definitions.'.$template->document_type->value))
            ->keyBy('field_key');

        foreach ($this->completeMappingsPayload($template->document_type) as $fieldKey => $mapping) {
            $template->fieldMaps()->updateOrCreate(
                ['field_key' => $fieldKey],
                [
                    'mapping_kind' => $definitions->get($fieldKey)['default_mapping_kind'] ?? 'fixed_cell',
                    'target_cell' => $mapping['target_cell'],
                    'sheet_name' => null,
                    'mapping_config' => null,
                    'default_value' => $mapping['default_value'],
                    'is_required' => (bool) ($definitions->get($fieldKey)['required'] ?? false),
                ],
            );
        }
    }

    private function completeMappingsPayload(TemplateDocumentType $documentType): array
    {
        return collect(config('templates.definitions.'.$documentType->value))
            ->values()
            ->mapWithKeys(fn (array $definition, int $index): array => [
                $definition['field_key'] => [
                    'target_cell' => chr(65 + ($index % 26)).($index + 1),
                    'default_value' => null,
                ],
            ])
            ->all();
    }

    private function storeTemplateWorkbook(Template $template): void
    {
        Storage::disk($template->file_disk)->put(
            $template->file_path,
            $this->genericWorkbookBinary($template->document_type->label()),
        );
    }

    private function genericWorkbookBinary(string $sheetTitle = 'Template'): string
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $spreadsheet->getActiveSheet()->setTitle(str($sheetTitle)->limit(31, '')->value());

        $tempPath = tempnam(sys_get_temp_dir(), 'template-test');
        $xlsxPath = $tempPath.'.xlsx';
        rename($tempPath, $xlsxPath);

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($xlsxPath);

        $contents = (string) file_get_contents($xlsxPath);
        @unlink($xlsxPath);

        return $contents;
    }

    private function scopeKey(?GradeLevel $gradeLevel): string
    {
        return $gradeLevel === null ? 'global' : 'grade-level:'.$gradeLevel->id;
    }

    private function activeScopeKey(TemplateDocumentType $documentType, ?GradeLevel $gradeLevel): string
    {
        return $documentType->value.':'.$this->scopeKey($gradeLevel);
    }
}
