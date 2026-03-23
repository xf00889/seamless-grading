<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Academic setup</p>
                <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ $subject->name }}</h1>
            </div>

            @can('update', $subject)
                <a href="{{ route('admin.academic-setup.subjects.edit', $subject) }}" class="inline-flex items-center rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
                    Edit subject
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="space-y-6">
        @include('admin.academic-setup.partials.flash')
        @include('admin.academic-setup.partials.navigation')

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
            <div class="content-card space-y-4">
                <div class="flex flex-wrap gap-3">
                    <x-status-chip :tone="$subject->is_active ? 'emerald' : 'slate'">
                        {{ $subject->is_active ? 'Active' : 'Inactive' }}
                    </x-status-chip>
                    <x-status-chip :tone="$subject->is_core ? 'amber' : 'sky'">
                        {{ $subject->is_core ? 'Core' : 'Elective' }}
                    </x-status-chip>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Code</p>
                        <p class="mt-3 text-lg font-semibold text-slate-900">{{ $subject->code }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Short name</p>
                        <p class="mt-3 text-lg font-semibold text-slate-900">{{ $subject->short_name ?: 'N/A' }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Teacher loads</p>
                        <p class="mt-3 text-lg font-semibold text-slate-900">{{ $subject->teacher_loads_count }}</p>
                    </div>
                </div>
            </div>

            <aside class="space-y-4">
                @can('activate', $subject)
                    <section class="content-card space-y-3">
                        <p class="text-sm font-semibold text-slate-900">Status</p>
                        @if ($subject->is_active)
                            <form method="POST" action="{{ route('admin.academic-setup.subjects.deactivate', $subject) }}" data-confirm-message="Deactivate this subject?">
                                @csrf
                                <x-secondary-button type="submit" class="w-full justify-center">Deactivate subject</x-secondary-button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.academic-setup.subjects.activate', $subject) }}" data-confirm-message="Activate this subject?">
                                @csrf
                                <x-primary-button class="w-full justify-center">Activate subject</x-primary-button>
                            </form>
                        @endif
                    </section>
                @endcan

                @can('delete', $subject)
                    <section class="content-card space-y-3">
                        <p class="text-sm font-semibold text-slate-900">Danger zone</p>
                        <p class="text-sm leading-6 text-slate-500">
                            Subjects can only be deleted when they are not referenced by teacher loads.
                        </p>
                        <form method="POST" action="{{ route('admin.academic-setup.subjects.destroy', $subject) }}" data-confirm-message="Delete this subject? This action cannot be undone.">
                            @csrf
                            @method('DELETE')
                            <x-danger-button class="w-full justify-center">Delete subject</x-danger-button>
                        </form>
                    </section>
                @endcan
            </aside>
        </section>
    </div>
</x-app-layout>
