<?php

namespace App\Actions\Admin\TemplateManagement;

use App\Enums\TemplateAuditAction;
use App\Enums\TemplateDocumentType;
use App\Enums\TemplateMappingKind;
use App\Models\Template;
use App\Models\User;
use App\Services\TemplateManagement\TemplateAuditLogger;
use App\Services\TemplateManagement\TemplateDefinitionRegistry;
use App\Services\TemplateManagement\TemplateScopeKeyFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CreateTemplateAction
{
    public function __construct(
        private readonly TemplateDefinitionRegistry $definitionRegistry,
        private readonly TemplateScopeKeyFactory $scopeKeyFactory,
        private readonly TemplateAuditLogger $auditLogger,
    ) {}

    public function handle(User $actor, array $validated): Template
    {
        $documentType = TemplateDocumentType::from($validated['document_type']);
        $gradeLevelId = isset($validated['grade_level_id']) ? (int) $validated['grade_level_id'] : null;
        $scopeKey = $this->scopeKeyFactory->scopeKey($gradeLevelId);
        $nextVersion = (int) Template::query()
            ->where('document_type', $documentType)
            ->where('scope_key', $scopeKey)
            ->where('code', $validated['code'])
            ->max('version') + 1;

        $disk = (string) config('templates.upload_disk', 'local');
        $file = $validated['file'];
        $storedPath = $file->storeAs(
            'templates/'.$documentType->value,
            $this->safeFilename($validated['code'], $nextVersion, $file),
            $disk,
        );

        try {
            return DB::transaction(function () use (
                $actor,
                $validated,
                $documentType,
                $gradeLevelId,
                $scopeKey,
                $disk,
                $storedPath,
                $nextVersion,
            ): Template {
                $template = Template::query()->create([
                    'code' => $validated['code'],
                    'name' => $validated['name'],
                    'description' => $validated['description'] ?? null,
                    'document_type' => $documentType,
                    'grade_level_id' => $gradeLevelId,
                    'scope_key' => $scopeKey,
                    'version' => $nextVersion,
                    'file_path' => $storedPath,
                    'file_disk' => $disk,
                    'is_active' => false,
                ]);

                foreach ($this->definitionRegistry->definitionsFor($documentType) as $definition) {
                    $template->fieldMaps()->create([
                        'field_key' => $definition['field_key'],
                        'mapping_kind' => $definition['default_mapping_kind'] ?? TemplateMappingKind::FixedCell->value,
                        'target_cell' => null,
                        'sheet_name' => null,
                        'mapping_config' => null,
                        'default_value' => null,
                        'is_required' => (bool) ($definition['required'] ?? false),
                    ]);
                }

                $this->auditLogger->log(
                    $template,
                    $actor,
                    TemplateAuditAction::Uploaded,
                    'Template version uploaded.',
                    ['stored_path' => $storedPath],
                );

                return $template->fresh(['gradeLevel', 'fieldMaps']);
            });
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($storedPath);

            throw $exception;
        }
    }

    private function safeFilename(string $code, int $version, UploadedFile $file): string
    {
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin');

        return Str::slug($code).'-v'.$version.'-'.Str::uuid().'.'.$extension;
    }
}
