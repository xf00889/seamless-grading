<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Admin tools</p>
                <h1 class="mt-2 text-2xl font-semibold text-slate-900">Templates</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                    Review template versions, mapping completeness, activation state, and grade-level scope before downstream export workflows rely on them.
                </p>
            </div>

            @can('create', \App\Models\Template::class)
                <a href="{{ route('admin.template-management.templates.create') }}" class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                    Upload template version
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="space-y-6">
        @include('admin.academic-setup.partials.flash')
        @include('admin.template-management.partials.navigation')

        <section class="content-card">
            <form method="GET" action="{{ route('admin.template-management.templates.index') }}" class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_220px_220px_180px_auto]">
                <div>
                    <x-input-label for="search" value="Search" />
                    <x-text-input id="search" name="search" type="text" class="mt-1 block w-full" :value="$filters['search']" placeholder="Name, code, or description" />
                </div>

                <div>
                    <x-input-label for="document_type" value="Template type" />
                    <select id="document_type" name="document_type" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-400">
                        <option value="">All types</option>
                        @foreach ($documentTypes as $documentType)
                            <option value="{{ $documentType->value }}" @selected($filters['document_type'] === $documentType->value)>
                                {{ $documentType->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-input-label for="grade_level_id" value="Grade level scope" />
                    <select id="grade_level_id" name="grade_level_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-400">
                        <option value="">All scopes</option>
                        @foreach ($gradeLevels as $gradeLevel)
                            <option value="{{ $gradeLevel->id }}" @selected($filters['grade_level_id'] === $gradeLevel->id)>
                                {{ $gradeLevel->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-input-label for="status" value="Activation" />
                    <select id="status" name="status" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-400">
                        <option value="">All</option>
                        <option value="active" @selected($filters['status'] === 'active')>Active</option>
                        <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
                    </select>
                </div>

                <div class="flex items-end gap-3">
                    <x-primary-button>Filter</x-primary-button>
                    <a href="{{ route('admin.template-management.templates.index') }}" class="inline-flex items-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
                        Reset
                    </a>
                </div>
            </form>
        </section>

        <section class="content-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                            <th class="px-4 py-3">Template</th>
                            <th class="px-4 py-3">Type &amp; Scope</th>
                            <th class="px-4 py-3">Version</th>
                            <th class="px-4 py-3">Mapping status</th>
                            <th class="px-4 py-3">Activation</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white text-sm text-slate-700">
                        @forelse ($templates as $template)
                            <tr>
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-slate-900">{{ $template['name'] }}</p>
                                    <p class="mt-1 text-slate-500">{{ $template['code'] }}</p>
                                    <p class="mt-2 max-w-md text-slate-500">{{ $template['description'] ?: 'No description provided.' }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex flex-wrap gap-2">
                                        <x-status-chip :tone="$template['document_type']['tone']">
                                            {{ $template['document_type']['label'] }}
                                        </x-status-chip>
                                    </div>
                                    <p class="mt-2 text-slate-500">{{ $template['scope'] }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-slate-900">v{{ $template['version'] }}</p>
                                    <p class="mt-1 text-slate-500">{{ $template['created_at'] }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex flex-wrap gap-2">
                                        <x-status-chip :tone="$template['mapping_status']['tone']">
                                            {{ $template['mapping_status']['label'] }}
                                        </x-status-chip>
                                    </div>
                                    <p class="mt-2 text-slate-500">
                                        {{ $template['mapping_summary']['required_valid'] }} of {{ $template['mapping_summary']['required_total'] }} required mappings valid
                                    </p>
                                </td>
                                <td class="px-4 py-4">
                                    <x-status-chip :tone="$template['status']['tone']">
                                        {{ $template['status']['label'] }}
                                    </x-status-chip>
                                    @if ($template['activated_at'])
                                        <p class="mt-2 text-slate-500">{{ $template['activated_at'] }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    <div class="table-row-actions ml-auto w-fit">
                                        <x-table-action-button
                                            :href="route('admin.template-management.templates.show', $template['model'])"
                                            icon="eye"
                                            title="Open template"
                                            aria-label="Open template"
                                        >
                                            Open
                                        </x-table-action-button>
                                        <x-table-action-button
                                            :href="route('admin.template-management.templates.history', $template['model'])"
                                            icon="history"
                                            title="Open template history"
                                            aria-label="Open template history"
                                        >
                                            History
                                        </x-table-action-button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">
                                    No templates matched the current filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-4 py-4">
                {{ $templates->links() }}
            </div>
        </section>
    </div>
</x-app-layout>
