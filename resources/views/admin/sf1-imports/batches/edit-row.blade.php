<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Admin imports</p>
            <h1 class="mt-2 text-2xl font-semibold text-slate-900">Resolve Row {{ $importBatchRow->row_number }}</h1>
            <p class="mt-2 text-sm text-slate-600">
                {{ $importBatch->section->schoolYear->name }} · {{ $importBatch->section->gradeLevel->name }} · {{ $importBatch->section->name }}
            </p>
        </div>
    </x-slot>

    <div class="space-y-6">
        @include('admin.academic-setup.partials.flash')
        @include('admin.sf1-imports.partials.navigation')

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,0.9fr)]">
            <article class="content-card">
                <h2 class="text-lg font-semibold text-slate-900">Current issues</h2>

                @if (($importBatchRow->errors ?? []) !== [])
                    <ul class="mt-4 space-y-2 text-sm text-rose-700">
                        @foreach ($importBatchRow->errors as $error)
                            <li>{{ $error['message'] }}</li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-4 text-sm text-slate-500">This row currently has no blocking issues.</p>
                @endif

                <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Original payload</p>
                    <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                        @foreach ($importBatchRow->payload as $key => $value)
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ str_replace('_', ' ', $key) }}</dt>
                                <dd class="mt-1 text-sm text-slate-700">{{ $value !== null && $value !== '' ? $value : 'Blank' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            </article>

            <article class="content-card">
                <h2 class="text-lg font-semibold text-slate-900">Possible learner matches</h2>

                @if ($candidateLearners->isEmpty())
                    <p class="mt-4 text-sm text-slate-500">No learner candidates matched the current row identity.</p>
                @else
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr class="text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                                    <th class="px-4 py-3">Learner</th>
                                    <th class="px-4 py-3">LRN</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white text-sm text-slate-700">
                                @foreach ($candidateLearners as $candidateLearner)
                                    <tr>
                                        <td class="px-4 py-4">
                                            {{ $candidateLearner->last_name }}, {{ $candidateLearner->first_name }}
                                        </td>
                                        <td class="px-4 py-4">{{ $candidateLearner->lrn }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </article>
        </section>

        <section class="content-card">
            <form method="POST" action="{{ route('admin.sf1-imports.rows.update', [$importBatch, $importBatchRow]) }}" class="space-y-6">
                @csrf
                @method('PUT')

                <div class="grid gap-6 lg:grid-cols-2">
                    <div>
                        <x-input-label for="learner_id" value="Link existing learner (optional)" />
                        <select id="learner_id" name="learner_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-400">
                            <option value="">Create or keep as new learner</option>
                            @foreach ($candidateLearners as $candidateLearner)
                                <option value="{{ $candidateLearner->id }}" @selected((int) old('learner_id', $importBatchRow->learner_id) === $candidateLearner->id)>
                                    {{ $candidateLearner->last_name }}, {{ $candidateLearner->first_name }} · {{ $candidateLearner->lrn }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('learner_id')" />
                    </div>

                    <div>
                        <x-input-label for="lrn" value="LRN" />
                        <x-text-input id="lrn" name="lrn" type="text" class="mt-1 block w-full" :value="old('lrn', $importBatchRow->normalized_data['lrn'] ?? '')" required />
                        <x-input-error class="mt-2" :messages="$errors->get('lrn')" />
                    </div>

                    <div>
                        <x-input-label for="last_name" value="Last name" />
                        <x-text-input id="last_name" name="last_name" type="text" class="mt-1 block w-full" :value="old('last_name', $importBatchRow->normalized_data['last_name'] ?? '')" required />
                        <x-input-error class="mt-2" :messages="$errors->get('last_name')" />
                    </div>

                    <div>
                        <x-input-label for="first_name" value="First name" />
                        <x-text-input id="first_name" name="first_name" type="text" class="mt-1 block w-full" :value="old('first_name', $importBatchRow->normalized_data['first_name'] ?? '')" required />
                        <x-input-error class="mt-2" :messages="$errors->get('first_name')" />
                    </div>

                    <div>
                        <x-input-label for="middle_name" value="Middle name" />
                        <x-text-input id="middle_name" name="middle_name" type="text" class="mt-1 block w-full" :value="old('middle_name', $importBatchRow->normalized_data['middle_name'] ?? '')" />
                        <x-input-error class="mt-2" :messages="$errors->get('middle_name')" />
                    </div>

                    <div>
                        <x-input-label for="suffix" value="Suffix" />
                        <x-text-input id="suffix" name="suffix" type="text" class="mt-1 block w-full" :value="old('suffix', $importBatchRow->normalized_data['suffix'] ?? '')" />
                        <x-input-error class="mt-2" :messages="$errors->get('suffix')" />
                    </div>

                    <div>
                        <x-input-label for="sex" value="Sex" />
                        <select id="sex" name="sex" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-400" required>
                            <option value="">Select sex</option>
                            <option value="male" @selected(old('sex', $importBatchRow->normalized_data['sex'] ?? '') === 'male')>Male</option>
                            <option value="female" @selected(old('sex', $importBatchRow->normalized_data['sex'] ?? '') === 'female')>Female</option>
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('sex')" />
                    </div>

                    <div>
                        <x-input-label for="birth_date" value="Birth date" />
                        <x-text-input id="birth_date" name="birth_date" type="date" class="mt-1 block w-full" :value="old('birth_date', $importBatchRow->normalized_data['birth_date'] ?? '')" required />
                        <x-input-error class="mt-2" :messages="$errors->get('birth_date')" />
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('admin.sf1-imports.show', $importBatch) }}" class="inline-flex items-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
                        Back to preview
                    </a>
                    <x-primary-button>Save and revalidate</x-primary-button>
                </div>
            </form>
        </section>
    </div>
</x-app-layout>
