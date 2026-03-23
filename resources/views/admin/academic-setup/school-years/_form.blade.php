@php
    $startsOn = old('starts_on', optional($schoolYear->starts_on)->format('Y-m-d'));
    $endsOn = old('ends_on', optional($schoolYear->ends_on)->format('Y-m-d'));
@endphp

<div class="grid gap-6 lg:grid-cols-2">
    <div>
        <x-input-label for="name" value="School year name" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $schoolYear->name)" required />
        <x-input-error class="mt-2" :messages="$errors->get('name')" />
    </div>

    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm leading-6 text-slate-600">
        Use a clear label such as <span class="font-semibold text-slate-900">2026-2027</span> so administrators can identify the academic year quickly in lists and related setup forms.
    </div>

    <div>
        <x-input-label for="starts_on" value="Start date" />
        <x-text-input id="starts_on" name="starts_on" type="date" class="mt-1 block w-full" :value="$startsOn" required />
        <x-input-error class="mt-2" :messages="$errors->get('starts_on')" />
    </div>

    <div>
        <x-input-label for="ends_on" value="End date" />
        <x-text-input id="ends_on" name="ends_on" type="date" class="mt-1 block w-full" :value="$endsOn" required />
        <x-input-error class="mt-2" :messages="$errors->get('ends_on')" />
    </div>
</div>
