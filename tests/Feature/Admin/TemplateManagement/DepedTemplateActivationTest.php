<?php

namespace Tests\Feature\Admin\TemplateManagement;

use App\Enums\RoleName;
use App\Enums\TemplateDocumentType;
use App\Models\GradeLevel;
use App\Models\Template;
use App\Models\User;
use App\Services\TemplateManagement\TemplateReadService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DepedTemplateActivationTest extends TestCase
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

    public function test_card_fixture_template_can_activate_with_card_specific_structured_mappings(): void
    {
        $admin = $this->createUserWithRole(RoleName::Admin->value);
        $gradeLevel = GradeLevel::factory()->create(['name' => 'Grade 11']);

        $this->actingAs($admin)
            ->post(route('admin.template-management.templates.store'), [
                'document_type' => TemplateDocumentType::Sf9->value,
                'grade_level_id' => $gradeLevel->id,
                'code' => 'deped-card-template',
                'name' => 'DepEd CARD Template',
                'description' => 'Real DepEd report card workbook.',
                'file' => $this->fixtureUpload('tests/Fixtures/DepEd/CARD 1.xlsx', 'CARD 1.xlsx'),
            ])
            ->assertRedirect();

        $template = Template::query()->where('code', 'deped-card-template')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.template-management.templates.show', $template))
            ->assertOk()
            ->assertSeeText('DepEd CARD template')
            ->assertSeeText('CARD BLANK FRONT')
            ->assertSeeText('CARD BLANK INSIDE');

        $this->actingAs($admin)
            ->put(route('admin.template-management.templates.mappings.update', $template), [
                'mappings' => $this->cardMappingsPayload(),
            ])
            ->assertRedirect(route('admin.template-management.templates.show', $template));

        $this->actingAs($admin)
            ->post(route('admin.template-management.templates.activate', $template))
            ->assertRedirect(route('admin.template-management.templates.show', $template));

        $template->refresh();

        $this->assertTrue($template->is_active);

        $this->actingAs($admin)
            ->get(route('admin.template-management.templates.show', $template))
            ->assertOk()
            ->assertSeeText('Complete')
            ->assertSeeText('DepEd CARD template');
    }

    public function test_card_fixture_activation_uses_sheet_suggestions_when_sheet_names_are_left_blank(): void
    {
        $admin = $this->createUserWithRole(RoleName::Admin->value);
        $gradeLevel = GradeLevel::factory()->create(['name' => 'Grade 11']);

        $this->actingAs($admin)
            ->post(route('admin.template-management.templates.store'), [
                'document_type' => TemplateDocumentType::Sf9->value,
                'grade_level_id' => $gradeLevel->id,
                'code' => 'deped-card-template-suggested-sheets',
                'name' => 'DepEd CARD Template Suggested Sheets',
                'description' => 'Real DepEd report card workbook.',
                'file' => $this->fixtureUpload('tests/Fixtures/DepEd/CARD 1.xlsx', 'CARD 1.xlsx'),
            ])
            ->assertRedirect();

        $template = Template::query()->where('code', 'deped-card-template-suggested-sheets')->firstOrFail();
        $mappings = collect($this->cardMappingsPayload())
            ->map(function (array $mapping): array {
                $mapping['sheet_name'] = null;

                return $mapping;
            })
            ->all();

        $this->actingAs($admin)
            ->put(route('admin.template-management.templates.mappings.update', $template), [
                'mappings' => $mappings,
            ])
            ->assertRedirect(route('admin.template-management.templates.show', $template));

        $this->from(route('admin.template-management.templates.show', $template))
            ->actingAs($admin)
            ->post(route('admin.template-management.templates.activate', $template))
            ->assertRedirect(route('admin.template-management.templates.show', $template))
            ->assertSessionHasNoErrors();

        $template->refresh();

        $this->assertTrue($template->is_active);
    }

    public function test_card_sheet_suggestions_prefill_the_editor_and_manual_sheet_values_can_still_be_saved(): void
    {
        $admin = $this->createUserWithRole(RoleName::Admin->value);
        $gradeLevel = GradeLevel::factory()->create(['name' => 'Grade 11']);

        $this->actingAs($admin)
            ->post(route('admin.template-management.templates.store'), [
                'document_type' => TemplateDocumentType::Sf9->value,
                'grade_level_id' => $gradeLevel->id,
                'code' => 'deped-card-template-editor-suggestions',
                'name' => 'DepEd CARD Template Editor Suggestions',
                'description' => 'Real DepEd report card workbook.',
                'file' => $this->fixtureUpload('tests/Fixtures/DepEd/CARD 1.xlsx', 'CARD 1.xlsx'),
            ])
            ->assertRedirect();

        $template = Template::query()->where('code', 'deped-card-template-editor-suggestions')->firstOrFail();
        $presentedTemplate = app(TemplateReadService::class)->presentTemplate($template);
        $rows = collect($presentedTemplate['mapping_summary']['rows'])->keyBy('field_key');

        $this->assertNull($rows['school_name']['sheet_name']);
        $this->assertSame('CARD BLANK FRONT', $rows['school_name']['suggested_sheet_name']);
        $this->assertSame('CARD BLANK FRONT', $rows['school_name']['effective_sheet_name']);
        $this->assertTrue($rows['school_name']['uses_suggested_sheet']);
        $this->assertSame('CARD BLANK INSIDE', $rows['subject_name_column']['suggested_sheet_name']);

        $this->actingAs($admin)
            ->get(route('admin.template-management.templates.mappings.edit', $template))
            ->assertOk()
            ->assertSeeText('Suggested worksheet: CARD BLANK FRONT')
            ->assertSeeText('Suggested worksheet: CARD BLANK INSIDE');

        $this->actingAs($admin)
            ->put(route('admin.template-management.templates.mappings.update', $template), [
                'mappings' => [
                    'school_name' => $this->mappingRow('fixed_cell', 'CARD BLANK FRONT', 'V6'),
                    'subject_name_column' => $this->mappingRow(
                        'subject_table_block',
                        'CARD BLANK INSIDE',
                        'A7',
                        ['anchor_cell' => 'A4', 'anchor_text' => 'SUBJECTS'],
                    ),
                ],
            ])
            ->assertRedirect(route('admin.template-management.templates.show', $template));

        $template->refresh();
        $presentedTemplate = app(TemplateReadService::class)->presentTemplate($template);
        $rows = collect($presentedTemplate['mapping_summary']['rows'])->keyBy('field_key');

        $this->assertSame('CARD BLANK FRONT', $template->fieldMaps()->where('field_key', 'school_name')->value('sheet_name'));
        $this->assertSame('CARD BLANK INSIDE', $template->fieldMaps()->where('field_key', 'subject_name_column')->value('sheet_name'));
        $this->assertFalse($rows['school_name']['uses_suggested_sheet']);
        $this->assertFalse($rows['subject_name_column']['uses_suggested_sheet']);
    }

    public function test_card_fixture_activation_blocks_incomplete_split_name_groups_with_type_aware_blockers(): void
    {
        $admin = $this->createUserWithRole(RoleName::Admin->value);
        $gradeLevel = GradeLevel::factory()->create(['name' => 'Grade 11']);

        $this->actingAs($admin)
            ->post(route('admin.template-management.templates.store'), [
                'document_type' => TemplateDocumentType::Sf9->value,
                'grade_level_id' => $gradeLevel->id,
                'code' => 'deped-card-template-blocked',
                'name' => 'Blocked DepEd CARD Template',
                'description' => 'Real DepEd report card workbook.',
                'file' => $this->fixtureUpload('tests/Fixtures/DepEd/CARD 1.xlsx', 'CARD 1.xlsx'),
            ])
            ->assertRedirect();

        $template = Template::query()->where('code', 'deped-card-template-blocked')->firstOrFail();
        $mappings = $this->cardMappingsPayload();
        $mappings['learner_name']['mapping_config_json'] = json_encode([
            'parts' => [
                'last_name' => 'W12',
            ],
        ], JSON_UNESCAPED_SLASHES);

        $this->actingAs($admin)
            ->put(route('admin.template-management.templates.mappings.update', $template), [
                'mappings' => $mappings,
            ])
            ->assertRedirect(route('admin.template-management.templates.show', $template));

        $this->from(route('admin.template-management.templates.show', $template))
            ->actingAs($admin)
            ->post(route('admin.template-management.templates.activate', $template))
            ->assertRedirect(route('admin.template-management.templates.show', $template))
            ->assertSessionHasErrors('mappings.learner_name.mapping_config_json');

        $this->actingAs($admin)
            ->get(route('admin.template-management.templates.show', $template))
            ->assertOk()
            ->assertSeeText('split field group is incomplete');
    }

    public function test_sf10_fixture_template_can_activate_with_multi_sheet_structured_mappings(): void
    {
        $admin = $this->createUserWithRole(RoleName::Admin->value);

        $this->actingAs($admin)
            ->post(route('admin.template-management.templates.store'), [
                'document_type' => TemplateDocumentType::Sf10->value,
                'grade_level_id' => null,
                'code' => 'deped-sf10-template',
                'name' => 'DepEd SF10 Template',
                'description' => 'Real DepEd permanent record workbook.',
                'file' => $this->fixtureUpload(
                    'tests/Fixtures/DepEd/School-Form-10-ES-Learners-Academic Permanent-Record_26March2025.xlsx',
                    'School-Form-10-ES-Learners-Academic Permanent-Record_26March2025.xlsx',
                ),
            ])
            ->assertRedirect();

        $template = Template::query()->where('code', 'deped-sf10-template')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.template-management.templates.show', $template))
            ->assertOk()
            ->assertSeeText('DepEd SF10-ES template')
            ->assertSeeText('Front')
            ->assertSeeText('Back')
            ->assertSeeText('Sir Wedz Helper Table');

        $this->actingAs($admin)
            ->put(route('admin.template-management.templates.mappings.update', $template), [
                'mappings' => $this->sf10MappingsPayload(),
            ])
            ->assertRedirect(route('admin.template-management.templates.show', $template));

        $this->actingAs($admin)
            ->post(route('admin.template-management.templates.activate', $template))
            ->assertRedirect(route('admin.template-management.templates.show', $template));

        $template->refresh();

        $this->assertTrue($template->is_active);
    }

    public function test_store_route_rejects_real_mismatched_deped_template_types(): void
    {
        $admin = $this->createUserWithRole(RoleName::Admin->value);
        $gradeLevel = GradeLevel::factory()->create(['name' => 'Grade 11']);

        $this->actingAs($admin)
            ->from(route('admin.template-management.templates.create'))
            ->post(route('admin.template-management.templates.store'), [
                'document_type' => TemplateDocumentType::Sf9->value,
                'grade_level_id' => $gradeLevel->id,
                'code' => 'wrong-template',
                'name' => 'Wrong Template',
                'description' => 'Mismatched template upload.',
                'file' => $this->fixtureUpload(
                    'tests/Fixtures/DepEd/School-Form-10-ES-Learners-Academic Permanent-Record_26March2025.xlsx',
                    'School-Form-10-ES-Learners-Academic Permanent-Record_26March2025.xlsx',
                ),
            ])
            ->assertRedirect(route('admin.template-management.templates.create'))
            ->assertSessionHasErrors('file');
    }

    private function createUserWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function fixtureUpload(string $relativePath, string $uploadedName): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $uploadedName,
            (string) file_get_contents(base_path($relativePath)),
        );
    }

    private function baseMappingsPayload(TemplateDocumentType $documentType): array
    {
        return collect(config('templates.definitions.'.$documentType->value))
            ->mapWithKeys(fn (array $definition): array => [
                $definition['field_key'] => [
                    'mapping_kind' => $definition['default_mapping_kind'] ?? 'fixed_cell',
                    'sheet_name' => null,
                    'target_cell' => null,
                    'mapping_config_json' => null,
                    'default_value' => null,
                ],
            ])
            ->all();
    }

    private function cardMappingsPayload(): array
    {
        $payload = $this->baseMappingsPayload(TemplateDocumentType::Sf9);

        return array_replace($payload, [
            'school_name' => $this->mappingRow('fixed_cell', 'CARD BLANK FRONT', 'V6'),
            'school_year_name' => $this->mappingRow('fixed_cell', 'CARD BLANK FRONT', 'Y16'),
            'grade_level_name' => $this->mappingRow('fixed_cell', 'CARD BLANK FRONT', 'W15'),
            'section_name' => $this->mappingRow('fixed_cell', 'CARD BLANK FRONT', 'AJ15'),
            'learner_name' => $this->mappingRow(
                'split_field_group',
                'CARD BLANK FRONT',
                null,
                [
                    'parts' => [
                        'last_name' => 'W12',
                        'first_name' => 'AD12',
                        'middle_name' => 'AK12',
                        'extension_name' => 'AP12',
                    ],
                ],
            ),
            'learner_lrn' => $this->mappingRow('fixed_cell', 'CARD BLANK FRONT', 'AJ14'),
            'adviser_name' => $this->mappingRow('fixed_cell', 'CARD BLANK FRONT', 'U26'),
            'subject_name_column' => $this->mappingRow(
                'subject_table_block',
                'CARD BLANK INSIDE',
                'A7',
                ['anchor_cell' => 'A4', 'anchor_text' => 'SUBJECTS'],
            ),
            'subject_grade_column' => $this->mappingRow(
                'subject_table_block',
                'CARD BLANK INSIDE',
                'L7',
                ['anchor_cell' => 'L4', 'anchor_text' => 'Quarter'],
            ),
            'general_average' => $this->mappingRow(
                'sheet_anchor_based',
                'CARD BLANK INSIDE',
                'P17',
                ['anchor_cell' => 'A17', 'anchor_text' => 'GENERAL AVERAGE FOR THE SEMESTER'],
            ),
        ]);
    }

    private function sf10MappingsPayload(): array
    {
        $payload = $this->baseMappingsPayload(TemplateDocumentType::Sf10);

        return array_replace($payload, [
            'school_name' => $this->mappingRow(
                'repeating_row_block',
                'Front',
                'D23',
                ['anchor_cell' => 'B21', 'anchor_text' => 'SCHOLASTIC RECORD'],
            ),
            'learner_name' => $this->mappingRow(
                'split_field_group',
                'Front',
                null,
                [
                    'parts' => [
                        'last_name' => 'E9',
                        'first_name' => 'R9',
                    ],
                ],
            ),
            'learner_lrn' => $this->mappingRow('fixed_cell', 'Front', 'J10'),
            'grade_level_name' => $this->mappingRow(
                'repeating_row_block',
                'Front',
                'D25',
                ['anchor_cell' => 'B21', 'anchor_text' => 'SCHOLASTIC RECORD'],
            ),
            'school_year_name' => $this->mappingRow(
                'repeating_row_block',
                'Back',
                'O5',
                ['anchor_cell' => 'B2', 'anchor_text' => 'SCHOLASTIC RECORD'],
            ),
            'section_name' => $this->mappingRow(
                'repeating_row_block',
                'Front',
                'J25',
                ['anchor_cell' => 'B21', 'anchor_text' => 'SCHOLASTIC RECORD'],
            ),
            'adviser_name' => $this->mappingRow(
                'repeating_row_block',
                'Back',
                'D6',
                ['anchor_cell' => 'B2', 'anchor_text' => 'SCHOLASTIC RECORD'],
            ),
            'subject_name_column' => $this->mappingRow(
                'repeating_row_block',
                'Front',
                'B30',
                ['anchor_cell' => 'B28', 'anchor_text' => 'LEARNING AREAS'],
            ),
            'final_rating_column' => $this->mappingRow(
                'repeating_row_block',
                'Back',
                'L10',
                ['anchor_cell' => 'B8', 'anchor_text' => 'LEARNING AREAS'],
            ),
            'action_taken_column' => $this->mappingRow(
                'repeating_row_block',
                'Back',
                'O10',
                ['anchor_cell' => 'B8', 'anchor_text' => 'LEARNING AREAS'],
            ),
        ]);
    }

    private function mappingRow(
        string $mappingKind,
        ?string $sheetName,
        ?string $targetCell,
        ?array $mappingConfig = null,
    ): array {
        return [
            'mapping_kind' => $mappingKind,
            'sheet_name' => $sheetName,
            'target_cell' => $targetCell,
            'mapping_config_json' => $mappingConfig === null ? null : json_encode($mappingConfig, JSON_UNESCAPED_SLASHES),
            'default_value' => null,
        ];
    }
}
