<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Admin tools</p>
            <h1 class="mt-2 text-2xl font-semibold text-slate-900">Template Management</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                Manage versioned grading sheet, SF9, and future SF10 templates without hardcoding export cells in application code.
            </p>
        </div>
    </x-slot>

    <div class="space-y-6">
        @include('admin.academic-setup.partials.flash')
        @include('admin.template-management.partials.navigation')

        <section class="grid gap-4 xl:grid-cols-3">
            @foreach ($resourceCards as $card)
                <a href="{{ route('admin.template-management.templates.index', ['document_type' => $card['document_type']]) }}" class="content-card block transition hover:-translate-y-0.5 hover:border-slate-300">
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">{{ $card['label'] }}</p>
                    <p class="mt-4 text-3xl font-semibold text-slate-900">{{ $card['count'] }}</p>
                    <p class="mt-2 text-sm font-medium text-slate-500">{{ $card['status'] }}</p>
                    <p class="mt-4 text-sm leading-6 text-slate-600">{{ $card['description'] }}</p>
                </a>
            @endforeach
        </section>

        <section class="content-card">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="max-w-3xl">
                    <p class="text-lg font-semibold text-slate-900">Module scope</p>
                    <p class="mt-3 text-sm leading-6 text-slate-600">
                        This module is limited to template upload, versioning, field-to-cell mapping management, and activation state. Export generation stays out of scope until a later workflow prompt.
                    </p>
                </div>

                @can('create', \App\Models\Template::class)
                    <a href="{{ route('admin.template-management.templates.create') }}" class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                        Upload template version
                    </a>
                @endcan
            </div>
        </section>
    </div>
</x-app-layout>
