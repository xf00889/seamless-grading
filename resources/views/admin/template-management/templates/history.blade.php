<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Template management</p>
                <h1 class="mt-2 text-2xl font-semibold text-slate-900">Version History</h1>
                <p class="mt-2 text-sm leading-6 text-slate-600">
                    {{ $template['name'] }} · {{ $template['document_type']['label'] }} · {{ $template['scope'] }}
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

        <section class="content-card">
            <p class="text-sm leading-6 text-slate-600">
                Only one template version can be active for the same template type and grade-level scope at a time. Use this history to compare completeness and activation state before switching versions.
            </p>
        </section>

        <section class="content-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                            <th class="px-4 py-3">Version</th>
                            <th class="px-4 py-3">Created</th>
                            <th class="px-4 py-3">Mapping status</th>
                            <th class="px-4 py-3">Activation</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white text-sm text-slate-700">
                        @foreach ($history as $version)
                            <tr>
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-slate-900">Version {{ $version['version'] }}</p>
                                    <p class="mt-1 text-slate-500">{{ $version['code'] }}</p>
                                </td>
                                <td class="px-4 py-4">{{ $version['created_at'] }}</td>
                                <td class="px-4 py-4">
                                    <x-status-chip :tone="$version['mapping_status']['tone']">
                                        {{ $version['mapping_status']['label'] }}
                                    </x-status-chip>
                                    <p class="mt-2 text-slate-500">
                                        {{ $version['mapping_summary']['required_valid'] }} / {{ $version['mapping_summary']['required_total'] }} required mappings valid
                                    </p>
                                </td>
                                <td class="px-4 py-4">
                                    <x-status-chip :tone="$version['status']['tone']">
                                        {{ $version['status']['label'] }}
                                    </x-status-chip>
                                    @if ($version['activated_at'])
                                        <p class="mt-2 text-slate-500">{{ $version['activated_at'] }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <div class="table-row-actions ml-auto w-fit">
                                        <x-table-action-button
                                            :href="route('admin.template-management.templates.show', $version['model'])"
                                            icon="eye"
                                            title="Open template version"
                                            aria-label="Open template version"
                                        >
                                            Open
                                        </x-table-action-button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
