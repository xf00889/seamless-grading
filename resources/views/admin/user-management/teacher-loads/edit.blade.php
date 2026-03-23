<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">User management</p>
            <h1 class="mt-2 text-2xl font-semibold text-slate-900">Edit Teacher Load</h1>
        </div>
    </x-slot>

    <div class="space-y-6">
        @include('admin.academic-setup.partials.flash')
        @include('admin.user-management.partials.navigation')

        <section class="content-card">
            <form method="POST" action="{{ route('admin.user-management.teacher-loads.update', $teacherLoad) }}" class="space-y-6">
                @csrf
                @method('PUT')

                @include('admin.user-management.teacher-loads._form')

                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('admin.user-management.teacher-loads.show', $teacherLoad) }}" class="inline-flex items-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
                        Cancel
                    </a>
                    <x-primary-button>Save changes</x-primary-button>
                </div>
            </form>
        </section>
    </div>
</x-app-layout>
