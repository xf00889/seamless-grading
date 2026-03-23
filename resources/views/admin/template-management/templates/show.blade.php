<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Template management</p>
                <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ $template['name'] }}</h1>
                <p class="mt-2 text-sm leading-6 text-slate-600">
                    {{ $template['document_type']['label'] }} · {{ $template['scope'] }} · Version {{ $template['version'] }}
                </p>
            </div>

            <div class="flex flex-wrap gap-3">
                @can('updateMappings', $template['model'])
                    <a href="{{ route('admin.template-management.templates.mappings.edit', $template['model']) }}" class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
                        Edit mappings
                    </a>
                @endcan

                @can('history', $template['model'])
                    <a href="{{ route('admin.template-management.templates.history', $template['model']) }}" class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
                        Version history
                    </a>
                @endcan

                @if ($template['is_active'])
                    @can('deactivate', $template['model'])
                        <form method="POST" action="{{ route('admin.template-management.templates.deactivate', $template['model']) }}" data-confirm-message="Deactivate this template version?">
                            @csrf
                            <button type="submit" class="inline-flex items-center justify-center rounded-xl border border-amber-300 px-4 py-3 text-sm font-semibold text-amber-700 transition hover:border-amber-400 hover:text-amber-800">
                                Deactivate
                            </button>
                        </form>
                    @endcan
                @else
                    @can('activate', $template['model'])
                        <form method="POST" action="{{ route('admin.template-management.templates.activate', $template['model']) }}" data-confirm-message="Activate this template version for its type and scope?">
                            @csrf
                            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                                Activate version
                            </button>
                        </form>
                    @endcan
                @endif
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        @include('admin.academic-setup.partials.flash')
        @include('admin.template-management.partials.navigation')

        <section class="grid gap-4 xl:grid-cols-4">
            <article class="content-card">
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Type</p>
                <div class="mt-4">
                    <x-status-chip :tone="$template['document_type']['tone']">
                        {{ $template['document_type']['label'] }}
                    </x-status-chip>
                </div>
            </article>

            <article class="content-card">
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Scope</p>
                <p class="mt-4 text-xl font-semibold text-slate-900">{{ $template['scope'] }}</p>
                <p class="mt-2 text-sm text-slate-500">{{ $template['scope_key'] }}</p>
            </article>

            <article class="content-card">
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Version</p>
                <p class="mt-4 text-xl font-semibold text-slate-900">v{{ $template['version'] }}</p>
                <p class="mt-2 text-sm text-slate-500">{{ $template['created_at'] }}</p>
            </article>

            <article class="content-card">
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Activation</p>
                <div class="mt-4">
                    <x-status-chip :tone="$template['status']['tone']">
                        {{ $template['status']['label'] }}
                    </x-status-chip>
                </div>
                <p class="mt-2 text-sm text-slate-500">
                    {{ $template['activated_at'] ?? 'Not active yet' }}
                </p>
            </article>
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1.3fr)_minmax(0,0.7fr)]">
            <article class="content-card space-y-4">
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-lg font-semibold text-slate-900">Mapping completeness</h2>
                    <x-status-chip :tone="$template['mapping_status']['tone']">
                        {{ $template['mapping_status']['label'] }}
                    </x-status-chip>
                </div>

                <p class="text-sm leading-6 text-slate-600">
                    {{ $template['mapping_summary']['required_valid'] }} of {{ $template['mapping_summary']['required_total'] }} required mappings are valid. Activation is blocked until every required field for this workbook structure is complete and valid.
                </p>

                @if ($template['mapping_summary']['issues'] !== [])
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4">
                        <p class="text-sm font-semibold text-amber-900">Current blockers</p>
                        <ul class="mt-3 space-y-2 text-sm text-amber-800">
                            @foreach ($template['mapping_summary']['issues'] as $issue)
                                <li>{{ $issue }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                                <th class="px-4 py-3">Field</th>
                                <th class="px-4 py-3">Mapping</th>
                                <th class="px-4 py-3">Requirement</th>
                                <th class="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white text-sm text-slate-700">
                            @foreach ($template['mapping_summary']['rows'] as $row)
                                <tr>
                                    <td class="px-4 py-4 align-top">
                                        <p class="font-semibold text-slate-900">{{ $row['label'] }}</p>
                                        <p class="mt-1 max-w-md text-slate-500">{{ $row['description'] }}</p>
                                    </td>
                                    <td class="px-4 py-4 align-top">
                                        <p class="font-semibold text-slate-900">{{ $row['mapping_kind_label'] }}</p>
                                        <p class="mt-2 font-mono text-slate-700">{{ $row['target_summary'] }}</p>
                                        @if ($row['effective_sheet_name'])
                                            <p class="mt-2 text-slate-500">
                                                Sheet: {{ $row['effective_sheet_name'] }}
                                                @if ($row['uses_suggested_sheet'])
                                                    <span class="text-slate-400">(suggested)</span>
                                                @endif
                                            </p>
                                        @endif
                                        @if ($row['default_value'])
                                            <p class="mt-2 text-slate-500">Default: {{ $row['default_value'] }}</p>
                                        @endif
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
                                            <p class="mt-2 max-w-sm text-sm text-rose-700">{{ $row['error'] }}</p>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </article>

            <aside class="space-y-6">
                <section class="content-card">
                    <h2 class="text-lg font-semibold text-slate-900">Workbook inspection</h2>
                    <dl class="mt-6 space-y-4 text-sm text-slate-700">
                        <div>
                            <dt class="font-semibold uppercase tracking-[0.18em] text-slate-500">Inspection mode</dt>
                            <dd class="mt-2">{{ $template['workbook_inspection']['workbook_label'] }}</dd>
                        </div>
                        <div>
                            <dt class="font-semibold uppercase tracking-[0.18em] text-slate-500">Detected workbook type</dt>
                            <dd class="mt-2">{{ $template['workbook_inspection']['detected_document_label'] ?? 'Generic workbook' }}</dd>
                        </div>
                        <div>
                            <dt class="font-semibold uppercase tracking-[0.18em] text-slate-500">Sheets</dt>
                            <dd class="mt-2">{{ implode(', ', $template['workbook_inspection']['sheet_names']) ?: 'No sheets detected' }}</dd>
                        </div>
                        <div>
                            <dt class="font-semibold uppercase tracking-[0.18em] text-slate-500">Positional template</dt>
                            <dd class="mt-2">{{ $template['workbook_inspection']['is_positional_template'] ? 'Yes' : 'No' }}</dd>
                        </div>
                    </dl>
                    @if ($template['workbook_inspection']['issues'] !== [])
                        <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4">
                            <p class="text-sm font-semibold text-amber-900">Inspection blockers</p>
                            <ul class="mt-3 space-y-2 text-sm text-amber-800">
                                @foreach ($template['workbook_inspection']['issues'] as $issue)
                                    <li>{{ $issue }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </section>

                <section class="content-card">
                    <h2 class="text-lg font-semibold text-slate-900">Stored file</h2>
                    <dl class="mt-6 space-y-4 text-sm text-slate-700">
                        <div>
                            <dt class="font-semibold uppercase tracking-[0.18em] text-slate-500">Disk</dt>
                            <dd class="mt-2">{{ $template['file_disk'] }}</dd>
                        </div>
                        <div>
                            <dt class="font-semibold uppercase tracking-[0.18em] text-slate-500">Storage path</dt>
                            <dd class="mt-2 break-all font-mono text-xs text-slate-600">{{ $template['file_path'] }}</dd>
                        </div>
                        @if ($template['deactivated_at'])
                            <div>
                                <dt class="font-semibold uppercase tracking-[0.18em] text-slate-500">Last deactivated</dt>
                                <dd class="mt-2">{{ $template['deactivated_at'] }}</dd>
                            </div>
                        @endif
                    </dl>
                </section>

                <section class="content-card">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-lg font-semibold text-slate-900">Family history</h2>
                        <span class="text-sm text-slate-500">{{ count($history) }} version{{ count($history) === 1 ? '' : 's' }}</span>
                    </div>
                    <div class="mt-6 space-y-3">
                        @foreach ($history as $version)
                            <a href="{{ route('admin.template-management.templates.show', $version['model']) }}" class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 text-sm transition hover:border-slate-300">
                                <div>
                                    <p class="font-semibold text-slate-900">Version {{ $version['version'] }}</p>
                                    <p class="mt-1 text-slate-500">{{ $version['created_at'] }}</p>
                                </div>
                                <x-status-chip :tone="$version['status']['tone']">
                                    {{ $version['status']['label'] }}
                                </x-status-chip>
                            </a>
                        @endforeach
                    </div>
                </section>

                <section class="content-card">
                    <h2 class="text-lg font-semibold text-slate-900">Audit trail</h2>
                    <div class="mt-6 space-y-4">
                        @forelse ($template['audit_logs'] as $auditLog)
                            <div class="rounded-2xl border border-slate-200 px-4 py-4">
                                <div class="flex items-start justify-between gap-4">
                                    <p class="font-semibold text-slate-900">{{ $auditLog['action'] }}</p>
                                    <p class="text-xs uppercase tracking-[0.18em] text-slate-500">{{ $auditLog['created_at'] }}</p>
                                </div>
                                <p class="mt-2 text-sm text-slate-600">{{ $auditLog['acted_by'] }}</p>
                                @if ($auditLog['remarks'])
                                    <p class="mt-3 text-sm leading-6 text-slate-600">{{ $auditLog['remarks'] }}</p>
                                @endif
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">No template audit entries have been recorded yet.</p>
                        @endforelse
                    </div>
                </section>
            </aside>
        </section>
    </div>
</x-app-layout>
