<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Template management</p>
                <h1 class="mt-2 text-2xl font-semibold text-slate-900">Edit Field Mappings</h1>
                <p class="mt-2 text-sm leading-6 text-slate-600">
                    {{ $template['name'] }} · {{ $template['document_type']['label'] }} · {{ $template['scope'] }} · Version {{ $template['version'] }}
                </p>
            </div>

            <a href="{{ route('admin.template-management.templates.show', $template['model']) }}" class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
                Back to template
            </a>
        </div>
    </x-slot>

    <div class="space-y-6">
        @include('admin.academic-setup.partials.flash')
        @include('admin.template-management.partials.navigation')

        <section class="grid gap-4 xl:grid-cols-4">
            <article class="content-card xl:col-span-3">
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-lg font-semibold text-slate-900">Mapping status</h2>
                    <x-status-chip :tone="$template['mapping_status']['tone']">
                        {{ $template['mapping_status']['label'] }}
                    </x-status-chip>
                </div>
                <p class="mt-3 text-sm leading-6 text-slate-600">
                    {{ $template['mapping_summary']['required_valid'] }} of {{ $template['mapping_summary']['required_total'] }} required mappings are valid. Save the mappings here, then activate only after the required rows are complete and error-free.
                </p>
            </article>

            <article class="content-card">
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Activation state</p>
                <div class="mt-4">
                    <x-status-chip :tone="$template['status']['tone']">
                        {{ $template['status']['label'] }}
                    </x-status-chip>
                </div>
                <p class="mt-2 text-sm text-slate-500">
                    Active versions cannot keep broken or incomplete required mappings.
                </p>
            </article>
        </section>

        @if ($template['mapping_summary']['issues'] !== [])
            <section class="rounded-2xl border border-amber-200 bg-amber-50 px-6 py-5">
                <h2 class="text-lg font-semibold text-amber-900">Current blockers</h2>
                <ul class="mt-3 space-y-2 text-sm text-amber-800">
                    @foreach ($template['mapping_summary']['issues'] as $issue)
                        <li>{{ $issue }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="content-card overflow-hidden">
            <form method="POST" action="{{ route('admin.template-management.templates.mappings.update', $template['model']) }}">
                @csrf
                @method('PUT')

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                                <th class="px-4 py-3">Field</th>
                                <th class="px-4 py-3">Mapping</th>
                                <th class="px-4 py-3">Target / Config</th>
                                <th class="px-4 py-3">Default value</th>
                                <th class="px-4 py-3">Requirement</th>
                                <th class="px-4 py-3">Current status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white text-sm text-slate-700">
                            @foreach ($template['mapping_summary']['rows'] as $row)
                                @php
                                    $mappingKindField = 'mappings.'.$row['field_key'].'.mapping_kind';
                                    $sheetField = 'mappings.'.$row['field_key'].'.sheet_name';
                                    $targetField = 'mappings.'.$row['field_key'].'.target_cell';
                                    $mappingConfigJsonField = 'mappings.'.$row['field_key'].'.mapping_config_json';
                                    $defaultField = 'mappings.'.$row['field_key'].'.default_value';
                                @endphp
                                <tr>
                                    <td class="px-4 py-4 align-top">
                                        <p class="font-semibold text-slate-900">{{ $row['label'] }}</p>
                                        <p class="mt-1 max-w-md text-slate-500">{{ $row['description'] }}</p>
                                        <p class="mt-2 font-mono text-xs text-slate-400">{{ $row['field_key'] }}</p>
                                    </td>
                                    <td class="px-4 py-4 align-top">
                                        <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500" for="{{ $mappingKindField }}">Mapping kind</label>
                                        <select
                                            id="{{ $mappingKindField }}"
                                            name="{{ 'mappings['.$row['field_key'].'][mapping_kind]' }}"
                                            class="mt-2 block w-full rounded-xl border-slate-300 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:ring-slate-300"
                                        >
                                            @foreach ($row['allowed_mapping_kinds'] as $kind)
                                                <option value="{{ $kind['value'] }}" @selected(old($mappingKindField, $row['mapping_kind']) === $kind['value'])>
                                                    {{ $kind['label'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <x-input-error class="mt-2" :messages="$errors->get($mappingKindField)" />

                                        <label class="mt-4 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500" for="{{ $sheetField }}">Worksheet</label>
                                        <x-text-input
                                            :id="$sheetField"
                                            :name="'mappings['.$row['field_key'].'][sheet_name]'"
                                            type="text"
                                            class="mt-2 block w-full"
                                            :value="old($sheetField, $row['effective_sheet_name'])"
                                            placeholder="Worksheet name"
                                        />
                                        <x-input-error class="mt-2" :messages="$errors->get($sheetField)" />
                                        @if ($row['suggested_sheet_name'])
                                            <p class="mt-2 text-xs text-slate-500">
                                                Suggested worksheet: {{ $row['suggested_sheet_name'] }}. You can override it.
                                            </p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 align-top">
                                        <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500" for="{{ $targetField }}">Target</label>
                                        <x-text-input
                                            :id="$targetField"
                                            :name="'mappings['.$row['field_key'].'][target_cell]'"
                                            type="text"
                                            class="mt-2 block w-full"
                                            :value="old($targetField, $row['target_cell'])"
                                            placeholder="A1 or NAMED_RANGE"
                                        />
                                        <x-input-error class="mt-2" :messages="$errors->get($targetField)" />

                                        <label class="mt-4 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500" for="{{ $mappingConfigJsonField }}">Mapping config JSON</label>
                                        <textarea
                                            id="{{ $mappingConfigJsonField }}"
                                            name="{{ 'mappings['.$row['field_key'].'][mapping_config_json]' }}"
                                            rows="5"
                                            class="mt-2 block w-full rounded-xl border-slate-300 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:ring-slate-300"
                                            placeholder='{"anchor_cell":"A4","anchor_text":"SUBJECTS"}'
                                        >{{ old($mappingConfigJsonField, $row['mapping_config_json']) }}</textarea>
                                        <x-input-error class="mt-2" :messages="$errors->get($mappingConfigJsonField)" />
                                        <p class="mt-2 text-xs text-slate-500">Current target: {{ $row['target_summary'] }}</p>
                                    </td>
                                    <td class="px-4 py-4 align-top">
                                        <x-text-input
                                            :id="$defaultField"
                                            :name="'mappings['.$row['field_key'].'][default_value]'"
                                            type="text"
                                            class="block w-full"
                                            :value="old($defaultField, $row['default_value'])"
                                            placeholder="Optional"
                                        />
                                        <x-input-error class="mt-2" :messages="$errors->get($defaultField)" />
                                    </td>
                                    <td class="px-4 py-4 align-top">
                                        <x-status-chip :tone="$row['is_required'] ? 'amber' : 'slate'">
                                            {{ $row['is_required'] ? 'Required' : 'Optional' }}
                                        </x-status-chip>
                                    </td>
                                    <td class="px-4 py-4 align-top">
                                        <x-status-chip :tone="$row['is_valid'] ? 'emerald' : 'rose'">
                                            {{ $row['is_valid'] ? 'Valid' : 'Needs fix' }}
                                        </x-status-chip>
                                        @if ($row['error'])
                                            <p class="mt-2 max-w-xs text-sm text-rose-700">{{ $row['error'] }}</p>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-wrap items-center gap-3 border-t border-slate-200 px-4 py-4">
                    <x-primary-button>Save mappings</x-primary-button>
                    <a href="{{ route('admin.template-management.templates.show', $template['model']) }}" class="inline-flex items-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
                        Cancel
                    </a>
                </div>
            </form>
        </section>
    </div>
</x-app-layout>
