<?php

namespace App\Support\TemplateManagement;

final class Navigation
{
    public function items(): array
    {
        return [
            [
                'label' => 'Overview',
                'route' => 'admin.template-management',
                'active' => 'admin.template-management',
            ],
            [
                'label' => 'Templates',
                'route' => 'admin.template-management.templates.index',
                'active' => 'admin.template-management.templates.*',
            ],
        ];
    }
}
