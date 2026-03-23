<?php

namespace Tests\Feature;

use Tests\TestCase;

class WelcomePageTest extends TestCase
{
    public function test_welcome_page_loads_with_project_messaging(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSeeText('School Grading Workflow')
            ->assertSeeText('Open The Workspace That Matches Your Role');
    }
}
