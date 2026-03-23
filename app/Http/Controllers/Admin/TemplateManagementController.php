<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TemplateManagement\TemplateReadService;
use App\Support\TemplateManagement\Navigation;
use Illuminate\Contracts\View\View;

class TemplateManagementController extends Controller
{
    public function __invoke(
        Navigation $navigation,
        TemplateReadService $readService,
    ): View {
        $this->authorize('viewTemplateManagement', User::class);

        return view('admin.template-management.index', [
            'navigationItems' => $navigation->items(),
            ...$readService->overview(),
        ]);
    }
}
