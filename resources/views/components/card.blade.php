@props([
    'as' => 'div',
    'padding' => 'default',
])

@php
    $paddingClasses = [
        'none' => 'p-0',
        'sm' => 'p-4',
        'default' => 'p-6',
        'lg' => 'p-8',
    ];
@endphp

<{{ $as }} {{ $attributes->class([
    'content-card',
    $paddingClasses[$padding] ?? $paddingClasses['default'],
]) }}>
    {{ $slot }}
</{{ $as }}>
