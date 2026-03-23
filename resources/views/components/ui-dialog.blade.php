@props([
    'name',
    'maxWidth' => 'lg',
])

<dialog
    {{ $attributes->class([
        'ui-dialog',
        'ui-dialog--'.$maxWidth,
    ])->merge([
        'data-modal' => $name,
    ]) }}
>
    <div class="ui-dialog__panel">
        {{ $slot }}
    </div>
</dialog>
