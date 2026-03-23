@if (session('status'))
    <x-alert-panel tone="emerald" title="Success">
        {{ session('status') }}
    </x-alert-panel>
@endif

@if (session('error'))
    <x-alert-panel tone="rose" title="Action needed">
        {{ session('error') }}
    </x-alert-panel>
@endif

@if ($errors->has('record'))
    <x-alert-panel tone="rose" title="Workflow blocked">
        {{ $errors->first('record') }}
    </x-alert-panel>
@endif
