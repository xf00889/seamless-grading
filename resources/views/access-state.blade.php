<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">{{ $eyebrow }}</p>
            <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ $title }}</h1>
        </div>
    </x-slot>

    <div class="content-card">
        <div class="flex items-center gap-3">
            <span class="status-chip">{{ $status }}</span>
            <p class="text-sm text-slate-500">Protected access state</p>
        </div>

        <p class="mt-6 text-base leading-7 text-slate-600">
            {{ $description }}
        </p>

        <p class="mt-4 text-sm leading-6 text-slate-500">
            {{ $guidance }}
        </p>
    </div>
</x-app-layout>
