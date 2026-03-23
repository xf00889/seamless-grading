<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Academic setup</p>
                <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ $section->name }}</h1>
            </div>

            @can('update', $section)
                <a href="{{ route('admin.academic-setup.sections.edit', $section) }}" class="inline-flex items-center rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
                    Edit section
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="space-y-6">
        @include('admin.academic-setup.partials.flash')
        @include('admin.academic-setup.partials.navigation')

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
            <div class="content-card space-y-4">
                <div class="flex items-center gap-3">
                    <x-status-chip :tone="$section->is_active ? 'emerald' : 'slate'">
                        {{ $section->is_active ? 'Active' : 'Inactive' }}
                    </x-status-chip>
                    <p class="text-sm text-slate-500">{{ $section->schoolYear->name }} · {{ $section->gradeLevel->name }}</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Adviser</p>
                        <p class="mt-3 text-lg font-semibold text-slate-900">{{ $section->adviser?->name ?? 'Unassigned' }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Teacher loads</p>
                        <p class="mt-3 text-lg font-semibold text-slate-900">{{ $section->teacher_loads_count }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Rosters</p>
                        <p class="mt-3 text-lg font-semibold text-slate-900">{{ $section->section_rosters_count }}</p>
                    </div>
                </div>
            </div>

            <aside class="space-y-4">
                @can('activate', $section)
                    <section class="content-card space-y-3">
                        <p class="text-sm font-semibold text-slate-900">Status</p>
                        @if ($section->is_active)
                            <form method="POST" action="{{ route('admin.academic-setup.sections.deactivate', $section) }}" data-confirm-message="Deactivate this section?">
                                @csrf
                                <x-secondary-button type="submit" class="w-full justify-center">Deactivate section</x-secondary-button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.academic-setup.sections.activate', $section) }}" data-confirm-message="Activate this section?">
                                @csrf
                                <x-primary-button class="w-full justify-center">Activate section</x-primary-button>
                            </form>
                        @endif
                    </section>
                @endcan

                @can('delete', $section)
                    <section class="content-card space-y-3">
                        <p class="text-sm font-semibold text-slate-900">Danger zone</p>
                        <p class="text-sm leading-6 text-slate-500">
                            Sections can only be deleted while they have no linked loads, imports, or rosters.
                        </p>
                        <form method="POST" action="{{ route('admin.academic-setup.sections.destroy', $section) }}" data-confirm-message="Delete this section? This action cannot be undone.">
                            @csrf
                            @method('DELETE')
                            <x-danger-button class="w-full justify-center">Delete section</x-danger-button>
                        </form>
                    </section>
                @endcan
            </aside>
        </section>
    </div>
</x-app-layout>
