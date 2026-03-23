<x-app-layout>
    <x-slot name="header">
        <x-page-header
            eyebrow="Admin tools"
            title="Audit Log"
            description="Review key workflow activity across imports, grading submissions, year-end status changes, templates, grading-sheet exports, and finalized SF9 or SF10 records without merging unrelated audit schemas."
        />
    </x-slot>

    <div class="page-stack">
        @include('admin.academic-setup.partials.flash')
        @include('admin.submission-monitoring.partials.navigation')

        <x-filter-bar title="Audit filters" description="Slice the aggregated audit stream by time, user, action, or module without changing the underlying audit sources.">
            <form method="GET" action="{{ route('admin.submission-monitoring.audit') }}" class="grid gap-4 lg:grid-cols-5">
                <label class="block">
                    <span class="ui-label">From date</span>
                    <input
                        type="date"
                        name="from_date"
                        value="{{ $filters['from_date'] }}"
                        class="ui-input mt-2"
                    />
                </label>

                <label class="block">
                    <span class="ui-label">To date</span>
                    <input
                        type="date"
                        name="to_date"
                        value="{{ $filters['to_date'] }}"
                        class="ui-input mt-2"
                    />
                </label>

                <label class="block">
                    <span class="ui-label">User</span>
                    <select name="user_id" class="ui-select mt-2">
                        <option value="">All users</option>
                        @foreach ($availableUsers as $user)
                            <option value="{{ $user->id }}" @selected($filters['user_id'] === $user->id)>
                                {{ $user->name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="ui-label">Action</span>
                    <select name="action" class="ui-select mt-2">
                        @foreach ($actionOptions as $option)
                            <option value="{{ $option['value'] }}" @selected($filters['action'] === $option['value'])>
                                {{ $option['label'] }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="ui-label">Entity / Module</span>
                    <select name="module" class="ui-select mt-2">
                        @foreach ($moduleOptions as $option)
                            <option value="{{ $option['value'] }}" @selected($filters['module'] === $option['value'])>
                                {{ $option['label'] }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <div class="action-bar items-end lg:col-span-5">
                    <button type="submit" class="ui-button ui-button--primary">
                        Apply filters
                    </button>
                    <a href="{{ route('admin.submission-monitoring.audit') }}" class="ui-link-button">
                        Reset
                    </a>
                </div>
            </form>
        </x-filter-bar>

        <x-table-wrapper title="Aggregated audit stream" description="This page reads from the app’s existing audit sources without flattening them into one write schema." :count="$events->total().' event'.($events->total() === 1 ? '' : 's')">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>User</th>
                            <th>Entity / Module</th>
                            <th>Action</th>
                            <th>Context</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($events as $event)
                            <tr>
                                <td class="text-slate-600">{{ $event['occurred_at_label'] }}</td>
                                <td class="font-medium text-slate-900">{{ $event['user_name'] }}</td>
                                <td class="text-slate-600">
                                    <p class="font-medium text-slate-900">{{ $event['entity_label'] }}</p>
                                    <p class="mt-1 text-slate-500">{{ $event['module_label'] }}</p>
                                </td>
                                <td>
                                    <x-status-chip tone="slate">
                                        {{ $event['action_label'] }}
                                    </x-status-chip>
                                </td>
                                <td class="text-slate-600">
                                    {{ $event['context'] !== '' ? $event['context'] : 'No extra context recorded.' }}
                                </td>
                                <td class="text-slate-600">
                                    {{ filled($event['remarks']) ? $event['remarks'] : 'No remarks recorded.' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <x-empty-state icon="audit" title="No audit events matched the current filters." description="Try widening the date range or clearing one of the audit filters." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($events->hasPages())
                <x-slot name="footer">
                    {{ $events->links() }}
                </x-slot>
            @endif
        </x-table-wrapper>
    </div>
</x-app-layout>
