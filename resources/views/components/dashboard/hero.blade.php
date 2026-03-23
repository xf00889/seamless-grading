@props([
    'eyebrow' => null,
    'title' => null,
    'description' => null,
])

@php
    $hasMeta = isset($meta) && trim((string) $meta) !== '';
    $hasActions = isset($actions) && trim((string) $actions) !== '';
@endphp

@if ($hasMeta || $hasActions)
    <section {{ $attributes->class('studio-hero') }}>
        @if ($hasMeta)
            <div class="studio-hero__meta">
                {{ $meta }}
            </div>
        @endif

        @if ($hasActions)
            <div class="studio-hero__actions">
                {{ $actions }}
            </div>
        @endif
    </section>
@endif
