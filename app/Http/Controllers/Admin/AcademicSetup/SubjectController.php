<?php

namespace App\Http\Controllers\Admin\AcademicSetup;

use App\Actions\Admin\AcademicSetup\Subjects\CreateSubjectAction;
use App\Actions\Admin\AcademicSetup\Subjects\DeleteSubjectAction;
use App\Actions\Admin\AcademicSetup\Subjects\SetSubjectActivationAction;
use App\Actions\Admin\AcademicSetup\Subjects\UpdateSubjectAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\AcademicSetup\SubjectRequest;
use App\Models\Subject;
use App\Support\AcademicSetup\Navigation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    public function __construct(private readonly Navigation $navigation)
    {
        $this->authorizeResource(Subject::class, 'subject');
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search'));
        $status = (string) $request->string('status');
        $type = (string) $request->string('type');

        $subjects = Subject::query()
            ->withCount('teacherLoads')
            ->when(
                $search !== '',
                fn ($query) => $query->where(function ($subjectQuery) use ($search): void {
                    $subjectQuery
                        ->where('code', 'like', '%'.$search.'%')
                        ->orWhere('name', 'like', '%'.$search.'%')
                        ->orWhere('short_name', 'like', '%'.$search.'%');
                }),
            )
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($type === 'core', fn ($query) => $query->where('is_core', true))
            ->when($type === 'elective', fn ($query) => $query->where('is_core', false))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('admin.academic-setup.subjects.index', [
            'navigationItems' => $this->navigation->items(),
            'subjects' => $subjects,
            'filters' => compact('search', 'status', 'type'),
        ]);
    }

    public function create(): View
    {
        return view('admin.academic-setup.subjects.create', [
            'navigationItems' => $this->navigation->items(),
            'subject' => new Subject,
        ]);
    }

    public function store(SubjectRequest $request, CreateSubjectAction $action): RedirectResponse
    {
        $subject = $action->handle($request->validated());

        return redirect()
            ->route('admin.academic-setup.subjects.show', $subject)
            ->with('status', 'Subject created successfully.');
    }

    public function show(Subject $subject): View
    {
        $subject->loadCount('teacherLoads');

        return view('admin.academic-setup.subjects.show', [
            'navigationItems' => $this->navigation->items(),
            'subject' => $subject,
        ]);
    }

    public function edit(Subject $subject): View
    {
        return view('admin.academic-setup.subjects.edit', [
            'navigationItems' => $this->navigation->items(),
            'subject' => $subject,
        ]);
    }

    public function update(
        SubjectRequest $request,
        Subject $subject,
        UpdateSubjectAction $action,
    ): RedirectResponse {
        $action->handle($subject, $request->validated());

        return redirect()
            ->route('admin.academic-setup.subjects.show', $subject)
            ->with('status', 'Subject updated successfully.');
    }

    public function destroy(Subject $subject, DeleteSubjectAction $action): RedirectResponse
    {
        $action->handle($subject);

        return redirect()
            ->route('admin.academic-setup.subjects.index')
            ->with('status', 'Subject deleted successfully.');
    }

    public function activate(Subject $subject, SetSubjectActivationAction $action): RedirectResponse
    {
        $this->authorize('activate', $subject);
        $action->handle($subject, true);

        return redirect()
            ->route('admin.academic-setup.subjects.show', $subject)
            ->with('status', 'Subject activated successfully.');
    }

    public function deactivate(Subject $subject, SetSubjectActivationAction $action): RedirectResponse
    {
        $this->authorize('deactivate', $subject);
        $action->handle($subject, false);

        return redirect()
            ->route('admin.academic-setup.subjects.show', $subject)
            ->with('status', 'Subject deactivated successfully.');
    }
}
