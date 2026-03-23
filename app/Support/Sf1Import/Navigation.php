<?php

namespace App\Support\Sf1Import;

final class Navigation
{
    public function items(): array
    {
        return [
            [
                'label' => 'Import Batches',
                'route' => 'admin.sf1-imports.index',
                'active' => [
                    'admin.sf1-imports.index',
                    'admin.sf1-imports.show',
                    'admin.sf1-imports.rows.*',
                ],
            ],
            [
                'label' => 'Upload Batch',
                'route' => 'admin.sf1-imports.create',
                'active' => 'admin.sf1-imports.create',
            ],
        ];
    }
}
