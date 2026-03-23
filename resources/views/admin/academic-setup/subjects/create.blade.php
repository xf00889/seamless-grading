<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Academic setup</p>
            <h1 class="mt-2 text-2xl font-semibold text-slate-900">Create Subject</h1>
        </div>
    </x-slot>

    <div class="space-y-6">
        @include('admin.academic-setup.partials.navigation')

        <section class="content-card">
            <form method="POST" action="{{ route('admin.academic-setup.subjects.store') }}" class="space-y-6">
                @csrf

                @include('admin.academic-setup.subjects._form')

                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('admin.academic-setup.subjects.index') }}" class="inline-flex items-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
                        Cancel
                    </a>
                    <x-primary-button>Create subject</x-primary-button>
                </div>
            </form>
        </section>
    </div>
</x-app-layout>
