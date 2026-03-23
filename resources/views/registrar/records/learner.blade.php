<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Registrar records</p>
                <h1 class="mt-2 text-2xl font-semibold text-slate-900">Learner Record History</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                    Finalized official SF9 and SF10 history for {{ $learner['name'] }} only. Unfinalized versions are not exposed in this workspace.
                </p>
            </div>

            <a href="{{ route('registrar.records.index') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
                Back to repository
            </a>
        </div>
    </x-slot>

    <div class="space-y-6">
        <section class="grid gap-4 md:grid-cols-4">
            <div class="content-card md:col-span-2">
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Learner</p>
                <p class="mt-4 text-2xl font-semibold text-slate-900">{{ $learner['name'] }}</p>
                <p class="mt-2 text-sm text-slate-600">LRN: {{ $learner['lrn'] }}</p>
            </div>
            <div class="content-card">
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Finalized Records</p>
                <p class="mt-4 text-3xl font-semibold text-slate-900">{{ $totals['records'] }}</p>
                <p class="mt-2 text-sm text-slate-600">Official finalized records available for verification.</p>
            </div>
            <div class="content-card">
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Document Mix</p>
                <p class="mt-4 text-3xl font-semibold text-slate-900">{{ $totals['sf9'] }} / {{ $totals['sf10'] }}</p>
                <p class="mt-2 text-sm text-slate-600">SF9 / SF10 finalized record count.</p>
            </div>
        </section>

        @foreach ($groups as $group)
            <section class="content-card overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-200 px-4 py-4">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">{{ $group['document_type']['label'] }}</h2>
                        <p class="mt-1 text-sm text-slate-500">Official finalized history for this learner and document type.</p>
                    </div>

                    <x-status-chip :tone="$group['document_type']['tone']">
                        {{ count($group['records']) }} record{{ count($group['records']) === 1 ? '' : 's' }}
                    </x-status-chip>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                                <th class="px-4 py-3">School context</th>
                                <th class="px-4 py-3">Record</th>
                                <th class="px-4 py-3">Version</th>
                                <th class="px-4 py-3">Finalization</th>
                                <th class="px-4 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white text-sm text-slate-700">
                            @foreach ($group['records'] as $record)
                                <tr>
                                    <td class="px-4 py-4 align-top">
                                        <p class="font-semibold text-slate-900">{{ $record['school_year_name'] }}</p>
                                        <p class="mt-1 text-slate-500">{{ $record['grade_level_name'] }} · {{ $record['section_name'] }}</p>
                                    </td>
                                    <td class="px-4 py-4 align-top">
                                        <p class="font-semibold text-slate-900">{{ $record['period_label'] }}</p>
                                        <p class="mt-1 text-slate-500">{{ $record['template_name'] }}</p>
                                    </td>
                                    <td class="px-4 py-4 align-top">
                                        <p class="font-semibold text-slate-900">Version {{ $record['record_version'] }}</p>
                                        <p class="mt-1 text-slate-500">Template v{{ $record['template_version'] }}</p>
                                    </td>
                                    <td class="px-4 py-4 align-top">
                                        <p class="font-semibold text-slate-900">{{ $record['finalized_at'] ?? 'Not finalized' }}</p>
                                        <p class="mt-1 text-slate-500">Finalized by {{ $record['finalized_by'] }}</p>
                                        <p class="mt-2 text-slate-500">Generated {{ $record['generated_at'] ?? 'Unknown' }}</p>
                                    </td>
                                    <td class="px-4 py-4 align-top text-right">
                                        <div class="table-row-actions ml-auto w-fit">
                                            <x-table-action-button
                                                :href="route('registrar.records.show', ['report_card_record' => $record['id']])"
                                                icon="eye"
                                                title="Verify learner record"
                                                aria-label="Verify learner record"
                                            >
                                                Verify
                                            </x-table-action-button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach
    </div>
</x-app-layout>
