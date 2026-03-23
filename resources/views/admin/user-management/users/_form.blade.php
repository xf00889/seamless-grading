@php
    $selectedRole = old('role', $managedUser->roles->first()?->name);
@endphp

<div class="grid gap-6 lg:grid-cols-2">
    <div>
        <x-input-label for="name" value="Full name" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $managedUser->name)" required />
        <x-input-error class="mt-2" :messages="$errors->get('name')" />
    </div>

    <div>
        <x-input-label for="email" value="Email address" />
        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $managedUser->email)" required />
        <x-input-error class="mt-2" :messages="$errors->get('email')" />
    </div>

    <div>
        <x-input-label for="role" value="Primary role" />
        <select id="role" name="role" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-400" required>
            <option value="">Select a role</option>
            @foreach ($roleOptions as $roleOption)
                <option value="{{ $roleOption->value }}" @selected($selectedRole === $roleOption->value)>
                    {{ $roleOption->label() }}
                </option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('role')" />
    </div>

    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm leading-6 text-slate-600">
        Role assignment is required so every authenticated user maps to an allowed dashboard and protected area.
    </div>

    <div>
        <x-input-label for="password" :value="$passwordRequired ? 'Password' : 'Password (leave blank to keep current password)'" />
        <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" :required="$passwordRequired" />
        <x-input-error class="mt-2" :messages="$errors->get('password')" />
    </div>

    <div>
        <x-input-label for="password_confirmation" value="Confirm password" />
        <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" :required="$passwordRequired" />
    </div>
</div>
