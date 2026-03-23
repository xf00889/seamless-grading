<div class="grid gap-6 lg:grid-cols-2">
    <div>
        <x-input-label for="code" value="Code" />
        <x-text-input id="code" name="code" type="text" class="mt-1 block w-full" :value="old('code', $subject->code)" required />
        <x-input-error class="mt-2" :messages="$errors->get('code')" />
    </div>

    <div>
        <x-input-label for="short_name" value="Short name" />
        <x-text-input id="short_name" name="short_name" type="text" class="mt-1 block w-full" :value="old('short_name', $subject->short_name)" />
        <x-input-error class="mt-2" :messages="$errors->get('short_name')" />
    </div>

    <div class="lg:col-span-2">
        <x-input-label for="name" value="Subject name" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $subject->name)" required />
        <x-input-error class="mt-2" :messages="$errors->get('name')" />
    </div>

    <label class="inline-flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
        <input type="checkbox" name="is_core" value="1" class="mt-1 rounded border-slate-300 text-slate-900 shadow-sm focus:ring-slate-400" @checked(old('is_core', $subject->exists ? $subject->is_core : true))>
        <span>
            <span class="block text-sm font-semibold text-slate-900">Core subject</span>
            <span class="mt-1 block text-sm leading-6 text-slate-500">Leave checked for required subjects. Clear it for electives.</span>
        </span>
    </label>
    <x-input-error class="-mt-2 lg:col-span-2" :messages="$errors->get('is_core')" />
</div>
