<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Admin imports</p>
            <h1 class="mt-2 text-2xl font-semibold text-slate-900">Upload SF1 Batch</h1>
        </div>
    </x-slot>

    <div class="space-y-6">
        @include('admin.academic-setup.partials.flash')
        @include('admin.sf1-imports.partials.navigation')

        <section class="content-card">
            <form method="POST" action="{{ route('admin.sf1-imports.store') }}" enctype="multipart/form-data" class="space-y-6">
                @csrf

                <div class="grid gap-6 lg:grid-cols-2">
                    <div>
                        <x-input-label for="section_id" value="Section" />
                        <select id="section_id" name="section_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-400" required>
                            <option value="">Select a section</option>
                            @foreach ($sections as $section)
                                <option value="{{ $section->id }}" @selected((int) old('section_id') === $section->id)>
                                    {{ $section->schoolYear->name }} · {{ $section->gradeLevel->name }} · {{ $section->name }} · Adviser: {{ $section->adviser?->name ?? 'None' }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('section_id')" />
                    </div>

                    <div>
                        <x-input-label for="file" value="Workbook" />
                        <input id="file" name="file" type="file" accept=".xlsx,.xls,.csv" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:outline-none focus:ring focus:ring-slate-200" required />
                        <x-input-error class="mt-2" :messages="$errors->get('file')" />
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Expected columns</p>
                    <p class="mt-3 text-sm leading-6 text-slate-600">
                        Provide a header row with `lrn`, `last_name`, `first_name`, `middle_name`, `suffix`, `sex`, and `birth_date`. Aliases such as `Last Name`, `First Name`, `Gender`, and `Date of Birth` are also accepted.
                    </p>
                </div>

                <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4">
                    <p class="text-sm font-semibold text-amber-900">Preview is required before import</p>
                    <p class="mt-2 text-sm leading-6 text-amber-800">
                        Uploading a file only creates the batch and validation preview. No learners or section rosters are written until the preview is reviewed and the batch is confirmed.
                    </p>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('admin.sf1-imports.index') }}" class="inline-flex items-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
                        Cancel
                    </a>
                    <x-primary-button>Upload batch</x-primary-button>
                </div>
            </form>
        </section>
    </div>
</x-app-layout>
