<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Academic setup</p>
                <h1 class="mt-2 text-2xl font-semibold text-slate-900">Edit {{ $schoolYear->name }}</h1>
            </div>

            <a href="{{ route('admin.academic-setup.school-years.show', $schoolYear) }}" class="inline-flex items-center rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
                Back to details
            </a>
        </div>
    </x-slot>

    <div class="space-y-6">
        @include('admin.academic-setup.partials.flash')
        @include('admin.academic-setup.partials.navigation')

        <section class="content-card">
            <form method="POST" action="{{ route('admin.academic-setup.school-years.update', $schoolYear) }}" class="space-y-6">
                @csrf
                @method('PUT')

                @include('admin.academic-setup.school-years._form')

                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('admin.academic-setup.school-years.show', $schoolYear) }}" class="inline-flex items-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
                        Cancel
                    </a>
                    <x-primary-button>Save changes</x-primary-button>
                </div>
            </form>
        </section>
    </div>
</x-app-layout>
