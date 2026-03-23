<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>School Grading Workflow</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div class="landing-shell">
            <header class="landing-header">
                <a href="{{ url('/') }}" class="app-brand">
                    <div class="app-brand__mark">SG</div>
                    <div>
                        <p class="app-brand__title">School Grading Workflow</p>
                        <p class="app-brand__subtitle">MVP grading operations</p>
                    </div>
                </a>

                <div class="landing-header__actions">
                    <a href="#workflow" class="ui-link-button ui-link-button--ghost">Workflow</a>

                    @auth
                        <a href="{{ route('dashboard') }}" class="ui-button ui-button--primary">Open dashboard</a>
                    @else
                        @if (Route::has('login'))
                            <a href="{{ route('login') }}" class="ui-button ui-button--primary">Sign in</a>
                        @endif
                    @endauth
                </div>
            </header>

            <main class="landing-main">
                <section class="studio-hero landing-hero">
                    <div class="studio-hero__content">
                        <p class="studio-hero__eyebrow">School grading workflow system</p>
                        <h1 class="studio-hero__title">Keep grading, review, approval, and official records in one secure flow.</h1>
                        <div class="studio-hero__description">
                            Built for the MVP workflow: teacher grade entry, adviser review, admin monitoring,
                            audit visibility, and registrar-ready records without scattered spreadsheets or side approvals.
                        </div>

                        <div class="studio-hero__meta">
                            <x-status-chip tone="sky">Role-based access</x-status-chip>
                            <x-status-chip tone="amber">Explicit workflow states</x-status-chip>
                            <x-status-chip tone="teal">Approved-data exports only</x-status-chip>
                        </div>

                        <div class="studio-hero__actions">
                            @auth
                                <a href="{{ route('dashboard') }}" class="ui-button ui-button--primary">Go to my workspace</a>
                            @else
                                @if (Route::has('login'))
                                    <a href="{{ route('login') }}" class="ui-button ui-button--primary">Sign in to continue</a>
                                @endif
                            @endauth

                            <a href="#roles" class="ui-link-button">Explore roles</a>
                        </div>
                    </div>

                    <div class="landing-hero__preview">
                        <div class="landing-preview">
                            <div class="landing-preview__panel">
                                <div class="landing-preview__header">
                                    <x-status-chip tone="sky">Quarter monitoring</x-status-chip>
                                    <span class="landing-preview__eyebrow">Workflow at a glance</span>
                                </div>

                                <div class="landing-preview__grid">
                                    <div class="landing-preview__item">
                                        <span class="landing-preview__label">Teacher</span>
                                        <strong class="landing-preview__value">Draft -> Submit</strong>
                                    </div>
                                    <div class="landing-preview__item">
                                        <span class="landing-preview__label">Adviser</span>
                                        <strong class="landing-preview__value">Return or Approve</strong>
                                    </div>
                                    <div class="landing-preview__item">
                                        <span class="landing-preview__label">Admin</span>
                                        <strong class="landing-preview__value">Monitor, Lock, Reopen</strong>
                                    </div>
                                    <div class="landing-preview__item">
                                        <span class="landing-preview__label">Registrar</span>
                                        <strong class="landing-preview__value">Read-only final records</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="studio-dashboard__metrics">
                    <x-dashboard.metric-card
                        label="Teacher entry"
                        value="Own loads only"
                        description="Teachers work only on their assigned loads, rosters, and returned submissions."
                        icon="book"
                        tone="indigo"
                    />

                    <x-dashboard.metric-card
                        label="Adviser review"
                        value="Return or approve"
                        description="Advisers review only their advisory sections and keep remarks attached to the workflow."
                        icon="section"
                        tone="amber"
                    />

                    <x-dashboard.metric-card
                        label="Admin monitoring"
                        value="Lock with control"
                        description="Admins track readiness, open blockers, and protect official record generation paths."
                        icon="monitor"
                        tone="rose"
                    />

                    <x-dashboard.metric-card
                        label="Official records"
                        value="Finalized only"
                        description="Registrar access stays read-only and limited to finalized official SF9 and SF10 records."
                        icon="archive"
                        tone="emerald"
                    />
                </section>

                <section id="roles" class="studio-dashboard__split-grid">
                    <x-dashboard.panel
                        eyebrow="Roles"
                        title="Role-Aware By Default"
                        description="Each user lands in a scoped workspace designed around the responsibilities of that role."
                    >
                        <div class="studio-link-grid">
                            <div class="studio-link-card">
                                <span class="studio-link-card__icon">
                                    <x-icon name="dashboard" class="h-5 w-5" />
                                </span>
                                <span class="studio-link-card__title">Admin</span>
                                <span class="studio-link-card__description">Setup, monitor, lock, reopen, and review the audit trail across the grading cycle.</span>
                            </div>

                            <div class="studio-link-card">
                                <span class="studio-link-card__icon">
                                    <x-icon name="book" class="h-5 w-5" />
                                </span>
                                <span class="studio-link-card__title">Teacher</span>
                                <span class="studio-link-card__description">Enter quarterly grades, manage returned submissions, and stay inside owned teaching loads.</span>
                            </div>

                            <div class="studio-link-card">
                                <span class="studio-link-card__icon">
                                    <x-icon name="section" class="h-5 w-5" />
                                </span>
                                <span class="studio-link-card__title">Adviser</span>
                                <span class="studio-link-card__description">Track section readiness, review submissions, and consolidate only approved learner data.</span>
                            </div>

                            <div class="studio-link-card">
                                <span class="studio-link-card__icon">
                                    <x-icon name="archive" class="h-5 w-5" />
                                </span>
                                <span class="studio-link-card__title">Registrar</span>
                                <span class="studio-link-card__description">Verify finalized official records through a read-only repository with version history.</span>
                            </div>
                        </div>
                    </x-dashboard.panel>

                    <x-dashboard.panel
                        eyebrow="MVP scope"
                        title="Focused On The Core Grading Cycle"
                        description="The product stays intentionally narrow so the main grading workflow is reliable before expanding further."
                    >
                        <div class="landing-list">
                            <div class="landing-list__item">Authentication and role-based access</div>
                            <div class="landing-list__item">Academic setup and teacher load assignment</div>
                            <div class="landing-list__item">SF1 import and quarterly grade entry</div>
                            <div class="landing-list__item">Draft, submit, return, approve, lock, and reopen workflow</div>
                            <div class="landing-list__item">SF9 generation and export from approved data</div>
                            <div class="landing-list__item">Audit logging for protected actions</div>
                        </div>
                    </x-dashboard.panel>
                </section>

                <x-dashboard.panel
                    id="workflow"
                    eyebrow="Workflow"
                    title="A Clear Path From Grade Entry To Official Records"
                    description="Every transition is explicit so the system can validate state, protect scope, and preserve auditability."
                >
                    <div class="landing-step-grid">
                        <div class="studio-note__item">
                            <span class="studio-note__label">Step 1</span>
                            <span class="studio-note__value">Teacher saves drafts and submits grades for review.</span>
                        </div>

                        <div class="studio-note__item">
                            <span class="studio-note__label">Step 2</span>
                            <span class="studio-note__value">Adviser returns with remarks or approves the submission.</span>
                        </div>

                        <div class="studio-note__item">
                            <span class="studio-note__label">Step 3</span>
                            <span class="studio-note__value">Admin monitors readiness, then locks or reopens when needed.</span>
                        </div>

                        <div class="studio-note__item">
                            <span class="studio-note__label">Step 4</span>
                            <span class="studio-note__value">Registrar verifies finalized official records in a read-only repository.</span>
                        </div>
                    </div>
                </x-dashboard.panel>

                <x-dashboard.panel
                    eyebrow="Get started"
                    title="Open The Workspace That Matches Your Role"
                    description="Sign in to continue into your assigned dashboard and workflow area."
                    tone="soft"
                >
                    <div class="studio-hero__actions">
                        @auth
                            <a href="{{ route('dashboard') }}" class="ui-button ui-button--primary">Open dashboard</a>
                        @else
                            @if (Route::has('login'))
                                <a href="{{ route('login') }}" class="ui-button ui-button--primary">Sign in</a>
                            @endif
                        @endauth
                    </div>
                </x-dashboard.panel>
            </main>
        </div>
    </body>
</html>
