<?php

namespace App\Support\SubmissionMonitoring;

final class Navigation
{
    public function items(): array
    {
        return [
            [
                'label' => 'Monitoring',
                'route' => 'admin.submission-monitoring',
                'active' => ['admin.submission-monitoring', 'admin.submission-monitoring.sections.*'],
            ],
            [
                'label' => 'Audit Log',
                'route' => 'admin.submission-monitoring.audit',
                'active' => 'admin.submission-monitoring.audit',
            ],
        ];
    }
}
