<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Admin tools</p>
            <h1 class="mt-2 text-2xl font-semibold text-slate-900">Upload Template Version</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                Upload a new versioned template file. The template version number is assigned automatically based on the selected type, scope, and code.
            </p>
        </div>
    </x-slot>

    <div class="space-y-6">
        @include('admin.academic-setup.partials.flash')
        @include('admin.template-management.partials.navigation')

        <section class="content-card">
            <form method="POST" action="{{ route('admin.template-management.templates.store') }}" enctype="multipart/form-data" class="space-y-6">
                @csrf

                <div class="grid gap-6 lg:grid-cols-2">
                    <div>
                        <x-input-label for="document_type" value="Template type" />
                        <select id="document_type" name="document_type" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-400" required>
                            <option value="">Select template type</option>
                            @foreach ($documentTypes as $documentType)
                                <option value="{{ $documentType->value }}" @selected(old('document_type') === $documentType->value)>
                                    {{ $documentType->label() }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('document_type')" />
                    </div>

                    <div>
                        <x-input-label for="grade_level_id" value="Grade level scope" />
                        <select id="grade_level_id" name="grade_level_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-400">
                            <option value="">All grade levels</option>
                            @foreach ($gradeLevels as $gradeLevel)
                                <option value="{{ $gradeLevel->id }}" @selected((string) old('grade_level_id') === (string) $gradeLevel->id)>
                                    {{ $gradeLevel->name }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('grade_level_id')" />
                    </div>

                    <div>
                        <x-input-label for="code" value="Template code" />
                        <x-text-input id="code" name="code" type="text" class="mt-1 block w-full" :value="old('code')" placeholder="sf9-grade7" required />
                        <p class="mt-2 text-sm text-slate-500">
                            Use a stable lowercase slug. Reusing the same code for the same type and scope creates the next version in that template family.
                        </p>
                        <x-input-error class="mt-2" :messages="$errors->get('code')" />
                    </div>

                    <div>
                        <x-input-label for="name" value="Template name" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" placeholder="SF9 Grade 7 Layout" required />
                        <x-input-error class="mt-2" :messages="$errors->get('name')" />
                    </div>
                </div>

                <div>
                    <x-input-label for="description" value="Description" />
                    <textarea id="description" name="description" rows="4" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-400" placeholder="Describe when this template version should be used.">{{ old('description') }}</textarea>
                    <x-input-error class="mt-2" :messages="$errors->get('description')" />
                </div>

                <div>
                    <x-input-label for="file" value="Template file" />
                    <input id="file" name="file" type="file" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-400" required />
                    <div class="mt-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <p class="text-sm font-semibold text-slate-900">Accepted formats</p>
                        <ul class="mt-3 space-y-2 text-sm text-slate-600">
                            @foreach ($uploadRules as $uploadRule)
                                <li>{{ $uploadRule['label'] }}: {{ implode(', ', $uploadRule['extensions']) }}</li>
                            @endforeach
                        </ul>
                        <p class="mt-3 text-sm text-slate-500">
                            Files are stored through Laravel storage using sanitized generated names instead of the original client filename.
                        </p>
                    </div>
                    <x-input-error class="mt-2" :messages="$errors->get('file')" />
                </div>

                <div class="flex flex-wrap gap-3">
                    <x-primary-button>Upload template</x-primary-button>
                    <a href="{{ route('admin.template-management.templates.index') }}" class="inline-flex items-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
                        Cancel
                    </a>
                </div>
            </form>
        </section>
    </div>
</x-app-layout>
