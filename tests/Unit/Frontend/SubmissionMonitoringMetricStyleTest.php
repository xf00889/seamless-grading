<?php

namespace Tests\Unit\Frontend;

use PHPUnit\Framework\TestCase;

class SubmissionMonitoringMetricStyleTest extends TestCase
{
    public function test_submission_monitoring_metrics_are_taller_than_default_dashboard_cards(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertIsString($css);
        $this->assertMatchesRegularExpression(
            '/\.submission-monitoring__metrics\s+\.studio-metric\s*\{[^}]*min-height:\s*9\.5rem;[^}]*\}/s',
            $css,
        );
    }
}
