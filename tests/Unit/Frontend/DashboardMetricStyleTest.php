<?php

namespace Tests\Unit\Frontend;

use PHPUnit\Framework\TestCase;

class DashboardMetricStyleTest extends TestCase
{
    public function test_dashboard_metric_vertical_rail_is_disabled(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertIsString($css);
        $this->assertMatchesRegularExpression(
            '/\.studio-metric::after\s*\{[^}]*display:\s*none;[^}]*\}/s',
            $css,
        );
    }
}
