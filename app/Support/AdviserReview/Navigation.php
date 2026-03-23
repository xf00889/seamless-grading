<?php

namespace App\Support\AdviserReview;

final class Navigation
{
    public function items(): array
    {
        return [
            [
                'label' => 'Overview',
                'route' => 'adviser.dashboard',
                'active' => 'adviser.dashboard',
            ],
            [
                'label' => 'Advisory Sections',
                'route' => 'adviser.sections.index',
                'active' => 'adviser.sections.*',
            ],
        ];
    }
}
