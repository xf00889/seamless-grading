<?php

namespace Tests\Feature\Admin\TemplateManagement;

use App\Enums\RoleName;
use App\Enums\TemplateDocumentType;
use App\Enums\TemplateMappingKind;
use App\Models\Template;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class TemplateFieldMapSchemaAlignmentTest extends TestCase
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

    public function test_template_field_maps_schema_supports_the_structured_mapping_model(): void
    {
        $this->assertTrue(Schema::hasColumns('template_field_maps', [
            'field_key',
            'mapping_kind',
            'target_cell',
            'sheet_name',
            'mapping_config',
            'default_value',
            'is_required',
        ]));
    }

    public function test_template_upload_creates_field_maps_with_structured_mapping_columns(): void
    {
        $admin = $this->createUserWithRole(RoleName::Admin->value);

        $this->actingAs($admin)
            ->post(route('admin.template-management.templates.store'), [
                'document_type' => TemplateDocumentType::GradingSheet->value,
                'grade_level_id' => null,
                'code' => 'schema-aligned-template',
                'name' => 'Schema Aligned Template',
                'description' => 'Template upload should seed structured mapping columns.',
                'file' => $this->makeWorkbookUpload('schema-aligned-template.xlsx'),
            ])
            ->assertRedirect();

        $template = Template::query()->where('code', 'schema-aligned-template')->firstOrFail();
        $firstFieldMap = $template->fieldMaps()->orderBy('id')->firstOrFail();

        $this->assertSame(TemplateMappingKind::FixedCell, $firstFieldMap->mapping_kind);
        $this->assertNull($firstFieldMap->target_cell);
        $this->assertNull($firstFieldMap->sheet_name);
        $this->assertNull($firstFieldMap->mapping_config);
    }

    private function createUserWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeWorkbookUpload(string $filename): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($filename, $this->genericWorkbookBinary());
    }

    private function genericWorkbookBinary(): string
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->setTitle('Template');

        $tempPath = tempnam(sys_get_temp_dir(), 'template-field-map-schema');
        $xlsxPath = $tempPath.'.xlsx';
        rename($tempPath, $xlsxPath);

        $writer = new Xlsx($spreadsheet);
        $writer->save($xlsxPath);

        $contents = (string) file_get_contents($xlsxPath);
        @unlink($xlsxPath);

        return $contents;
    }
}
