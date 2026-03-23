@props([
    'config' => [],
    'label' => 'Dashboard chart',
    'items' => null,
])

@php
    $accessibilityItems = $items;

    if ($accessibilityItems === null) {
        $labels = is_array($config['labels'] ?? null)
            ? $config['labels']
            : (is_array($config['categories'] ?? null) ? $config['categories'] : []);
        $values = is_array($config['series'] ?? null)
            ? $config['series']
            : (is_array($config['values'] ?? null) ? $config['values'] : []);

        $accessibilityItems = [];

        foreach ($labels as $index => $itemLabel) {
            $accessibilityItems[] = [
                'label' => (string) $itemLabel,
                'value' => number_format((float) ($values[$index] ?? 0)),
            ];
        }
    }
@endphp

<div class="studio-apex-chart">
    <div
        class="studio-apex-chart__canvas"
        data-dashboard-chart
        data-chart-config='@json($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)'
        role="img"
        aria-label="{{ $label }}"
    ></div>

    @if ($accessibilityItems !== [])
        <ul class="sr-only">
            @foreach ($accessibilityItems as $item)
                <li>{{ $item['label'] }}: {{ $item['value'] }}</li>
            @endforeach
        </ul>
    @endif
</div>
