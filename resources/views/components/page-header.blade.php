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
    <div {{ $attributes->class('page-header') }}>
        @if ($hasMeta)
            <div class="page-header__meta">
                {{ $meta }}
            </div>
        @endif

        @if ($hasActions)
            <div class="page-header__actions">
                {{ $actions }}
            </div>
        @endif
    </div>
@endif
