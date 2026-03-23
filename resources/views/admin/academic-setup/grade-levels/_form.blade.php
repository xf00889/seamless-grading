<div class="grid gap-6 lg:grid-cols-3">
    <div>
        <x-input-label for="code" value="Code" />
        <x-text-input id="code" name="code" type="text" class="mt-1 block w-full" :value="old('code', $gradeLevel->code)" required />
        <x-input-error class="mt-2" :messages="$errors->get('code')" />
    </div>

    <div class="lg:col-span-2">
        <x-input-label for="name" value="Grade level name" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $gradeLevel->name)" required />
        <x-input-error class="mt-2" :messages="$errors->get('name')" />
    </div>

    <div>
        <x-input-label for="sort_order" value="Sort order" />
        <x-text-input id="sort_order" name="sort_order" type="number" min="1" class="mt-1 block w-full" :value="old('sort_order', $gradeLevel->sort_order)" required />
        <x-input-error class="mt-2" :messages="$errors->get('sort_order')" />
    </div>
</div>
