<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Academic setup</p>
                <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ $gradeLevel->name }}</h1>
            </div>

            @can('update', $gradeLevel)
                <a href="{{ route('admin.academic-setup.grade-levels.edit', $gradeLevel) }}" class="inline-flex items-center rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
                    Edit grade level
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="space-y-6">
        @include('admin.academic-setup.partials.flash')
        @include('admin.academic-setup.partials.navigation')

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
            <div class="content-card space-y-4">
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Code</p>
                        <p class="mt-3 text-lg font-semibold text-slate-900">{{ $gradeLevel->code }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Sort order</p>
                        <p class="mt-3 text-lg font-semibold text-slate-900">{{ $gradeLevel->sort_order }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Sections</p>
                        <p class="mt-3 text-lg font-semibold text-slate-900">{{ $gradeLevel->sections_count }}</p>
                    </div>
                </div>

                <div>
                    <p class="text-sm font-semibold text-slate-900">Sections using this grade level</p>
                    <div class="mt-3 space-y-3">
                        @forelse ($gradeLevel->sections as $section)
                            <a href="{{ route('admin.academic-setup.sections.show', $section) }}" class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 text-sm transition hover:border-slate-300">
                                <div>
                                    <p class="font-semibold text-slate-900">{{ $section->name }}</p>
                                    <p class="mt-1 text-slate-500">{{ $section->schoolYear->name }}</p>
                                </div>
                                <x-status-chip :tone="$section->is_active ? 'emerald' : 'slate'">
                                    {{ $section->is_active ? 'Active' : 'Inactive' }}
                                </x-status-chip>
                            </a>
                        @empty
                            <p class="text-sm text-slate-500">No sections are assigned to this grade level yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            @can('delete', $gradeLevel)
                <aside class="content-card space-y-3">
                    <p class="text-sm font-semibold text-slate-900">Danger zone</p>
                    <p class="text-sm leading-6 text-slate-500">
                        Grade levels can only be deleted when no sections are using them.
                    </p>
                    <form method="POST" action="{{ route('admin.academic-setup.grade-levels.destroy', $gradeLevel) }}" data-confirm-message="Delete this grade level? This action cannot be undone.">
                        @csrf
                        @method('DELETE')
                        <x-danger-button class="w-full justify-center">Delete grade level</x-danger-button>
                    </form>
                </aside>
            @endcan
        </section>
    </div>
</x-app-layout>
