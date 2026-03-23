@props([
    'items' => [],
    'label' => 'Dashboard bar chart',
])

@php
    $chartPayload = [
        'type' => 'bar',
        'height' => 360,
        'label' => $label,
        'categories' => array_values(array_map(
            static fn (array $item): string => (string) ($item['label'] ?? ''),
            $items,
        )),
        'values' => array_values(array_map(
            static fn (array $item): float => (float) ($item['value'] ?? 0),
            $items,
        )),
        'colors' => array_values(array_map(
            static fn (array $item): string => (bool) ($item['emphasis'] ?? false) ? '#5b68b2' : '#b6bfdc',
            $items,
        )),
        'max' => $items !== []
            ? max(array_map(static fn (array $item): float => (float) ($item['value'] ?? 0), $items))
            : 0,
        'emptyText' => 'No chart data available',
    ];
@endphp

<x-dashboard.apex-chart
    :config="$chartPayload"
    :label="$label"
    :items="array_map(
        static fn (array $item): array => [
            'label' => (string) ($item['label'] ?? ''),
            'value' => (string) ($item['value_label'] ?? number_format((float) ($item['value'] ?? 0))),
        ],
        $items,
    )"
/>
