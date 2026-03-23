<?php

namespace App\Actions\Admin\Sf1Imports;

use App\Enums\ImportBatchStatus;
use App\Models\ImportBatch;
use App\Models\Section;
use App\Models\User;
use App\Services\DepedWorkbook\UnsupportedDepedWorkbookException;
use App\Services\Sf1Import\Sf1WorkbookParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UploadSf1ImportAction
{
    public function __construct(
        private readonly Sf1WorkbookParser $parser,
        private readonly ValidateSf1ImportBatchAction $validateAction,
    ) {}

    public function handle(User $actor, Section $section, UploadedFile $file): ImportBatch
    {
        $safeFilename = $this->safeFilename($file);
        $storedPath = $file->storeAs('imports/sf1', $safeFilename, 'local');

        $importBatch = ImportBatch::query()->create([
            'section_id' => $section->id,
            'imported_by' => $actor->id,
            'status' => ImportBatchStatus::Uploaded,
            'source_file_name' => $safeFilename,
            'source_disk' => 'local',
            'source_path' => $storedPath,
            'total_rows' => 0,
            'valid_rows' => 0,
            'invalid_rows' => 0,
        ]);

        try {
            $parsedRows = $this->parser->parse($file);

            if ($parsedRows === []) {
                $importBatch->update(['status' => ImportBatchStatus::Failed]);

                throw ValidationException::withMessages([
                    'file' => 'The uploaded file does not contain any importable learner rows.',
                ]);
            }

            DB::transaction(function () use ($importBatch, $parsedRows): void {
                foreach ($parsedRows as $parsedRow) {
                    $importBatch->rows()->create([
                        'row_number' => $parsedRow['row_number'],
                        'payload' => $parsedRow['payload'],
                        'normalized_data' => $parsedRow['normalized_data'] ?? $parsedRow['payload'],
                        'errors' => null,
                    ]);
                }

                $this->validateAction->handle($importBatch);
            });
        } catch (UnsupportedDepedWorkbookException $exception) {
            $importBatch->update(['status' => ImportBatchStatus::Failed]);

            throw ValidationException::withMessages([
                'file' => $exception->getMessage(),
            ]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable) {
            $importBatch->update(['status' => ImportBatchStatus::Failed]);

            throw ValidationException::withMessages([
                'file' => 'The uploaded file could not be parsed. Please check the workbook and try again.',
            ]);
        }

        return $importBatch->fresh(['section.schoolYear', 'rows']);
    }

    private function safeFilename(UploadedFile $file): string
    {
        $baseName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = $file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'xlsx';

        return Str::slug($baseName !== '' ? $baseName : 'sf1-import')
            .'-'.Str::uuid().'.'.$extension;
    }
}
