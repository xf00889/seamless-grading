<?php

namespace App\Support\Dashboard;

final class BarChartPresenter
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public function present(array $items): array
    {
        return array_map(
            static fn (array $item): array => [
                ...$item,
                'value_label' => $item['value_label'] ?? number_format((float) ($item['value'] ?? 0)),
                'emphasis' => (bool) ($item['emphasis'] ?? false),
            ],
            $items,
        );
    }
}
