<?php

namespace Tests\Unit\Frontend;

use PHPUnit\Framework\TestCase;

class StatCardStyleTest extends TestCase
{
    public function test_stat_card_accent_rail_is_disabled(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertIsString($css);
        $this->assertMatchesRegularExpression(
            '/\.stat-card::after\s*\{[^}]*display:\s*none;[^}]*\}/s',
            $css,
        );
    }
}
