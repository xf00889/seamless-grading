@props([
    'name',
    'class' => 'h-5 w-5',
])

@switch($name)
    @case('dashboard')
        <svg {{ $attributes->class($class) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path d="M4 13.5h7V20H4zM13 4h7v9h-7zM13 15h7v5h-7zM4 4h7v7H4z" stroke-linejoin="round" />
        </svg>
        @break
    @case('calendar')
        <svg {{ $attributes->class($class) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path d="M7 3v4M17 3v4M4 9h16M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        @break
    @case('users')
        <svg {{ $attributes->class($class) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path d="M15 19v-1a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v1M9 10a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM21 19v-1a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        @break
    @case('eye')
        <svg {{ $attributes->class($class) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path d="M2.5 12s3.5-6.5 9.5-6.5S21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z" stroke-linecap="round" stroke-linejoin="round" />
            <circle cx="12" cy="12" r="3" />
        </svg>
        @break
    @case('edit')
        <svg {{ $attributes->class($class) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path d="m4 20 4.5-1 9.75-9.75a2.12 2.12 0 0 0-3-3L5.5 16 4 20Z" stroke-linecap="round" stroke-linejoin="round" />
            <path d="m13.5 6.5 4 4" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        @break
    @case('download')
        <svg {{ $attributes->class($class) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path d="M12 4v11M7.5 10.5 12 15l4.5-4.5M4 20h16" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        @break
    @case('history')
        <svg {{ $attributes->class($class) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path d="M4 12a8 8 0 1 0 2.34-5.66L4 8.5" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M4 4v4.5h4.5M12 8v4l2.5 2.5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        @break
    @case('upload')
        <svg {{ $attributes->class($class) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path d="M12 16V4M7 9l5-5 5 5M4 20h16" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        @break
    @case('template')
        <svg {{ $attributes->class($class) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path d="M7 3h10l4 4v14H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" stroke-linejoin="round" />
            <path d="M17 3v5h5M9 12h6M9 16h6" stroke-linecap="round" />
        </svg>
        @break
    @case('monitor')
        <svg {{ $attributes->class($class) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path d="M4 5h16v11H4zM8 19h8M12 16v3" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        @break
    @case('audit')
        <svg {{ $attributes->class($class) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path d="M9 7h11M9 12h11M9 17h11M4 7h.01M4 12h.01M4 17h.01" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        @break
    @case('book')
        <svg {{ $attributes->class($class) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path d="M5 5a3 3 0 0 1 3-3h11v18H8a3 3 0 0 0-3 3V5Z" stroke-linejoin="round" />
            <path d="M8 7h8M8 11h8" stroke-linecap="round" />
        </svg>
        @break
    @case('undo')
        <svg {{ $attributes->class($class) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path d="M9 14 4 9l5-5M20 20a8 8 0 0 0-8-8H4" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        @break
    @case('section')
        <svg {{ $attributes->class($class) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path d="M4 5h16v5H4zM4 12h7v7H4zM13 12h7v7h-7z" stroke-linejoin="round" />
        </svg>
        @break
    @case('archive')
        <svg {{ $attributes->class($class) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path d="M4 7h16v13H4zM3 4h18v3H3zM10 12h4" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        @break
    @case('clock')
        <svg {{ $attributes->class($class) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <circle cx="12" cy="12" r="9" />
            <path d="M12 7v5l3 2" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        @break
    @case('check-circle')
        <svg {{ $attributes->class($class) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <circle cx="12" cy="12" r="9" />
            <path d="m8.5 12 2.5 2.5L15.5 10" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        @break
    @case('lock')
        <svg {{ $attributes->class($class) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path d="M7 11V8a5 5 0 0 1 10 0v3M6 11h12v9H6z" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        @break
    @case('close')
        <svg {{ $attributes->class($class) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path d="m6 6 12 12M18 6 6 18" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        @break
    @default
        <svg {{ $attributes->class($class) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <circle cx="12" cy="12" r="9" />
        </svg>
@endswitch
