<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">{{ $eyebrow }}</p>
            <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ $title }}</h1>
        </div>
    </x-slot>

    <div class="space-y-6">
        <section class="content-card">
            <p class="text-base leading-7 text-slate-600">
                {{ $description }}
            </p>
        </section>

        <section class="grid gap-4 lg:grid-cols-2">
            @foreach ($links as $link)
                <a href="{{ route($link['route']) }}" class="content-card block transition hover:-translate-y-0.5 hover:border-slate-300">
                    <p class="text-lg font-semibold text-slate-900">{{ $link['label'] }}</p>
                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ $link['description'] }}</p>
                </a>
            @endforeach
        </section>
    </div>
</x-app-layout>
