<?php

namespace Tests\Unit\Frontend;

use PHPUnit\Framework\TestCase;

class SubmissionMonitoringActionStyleTest extends TestCase
{
    public function test_submission_monitoring_actions_are_inline_and_status_badges_are_compact(): void
    {
        $css = file_get_contents(dirname(__DIR__, 3).'/resources/css/app.css');

        $this->assertIsString($css);
        $this->assertMatchesRegularExpression(
            '/\.submission-monitoring__table-actions\s*\{[^}]*flex-nowrap[^}]*\}/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.submission-monitoring__section-status\s*\{[^}]*border-radius:\s*999px;[^}]*white-space:\s*nowrap;[^}]*\}/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.submission-monitoring__table-action-form\s*\{[^}]*shrink-0[^}]*\}/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.submission-monitoring__lock-action\s+\.table-action-button__icon\s*\{[^}]*background:[^}]*color:[^}]*\}/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.submission-monitoring__reopen-action\s+\.table-action-button__icon\s*\{[^}]*background:[^}]*color:[^}]*\}/s',
            $css,
        );
    }
}
