<?php

namespace App\Http\Controllers;

use App\Models\Lookup;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\{Rule, ValidationException};

class PersonnelController
{
    private const PERSONNEL_LOOKUP_CATEGORIES = [
        'title',
        'gender',
        'marital_status',
        'religion',
        'salary_structure',
        'special_categorization',
        'level',
        'step',
        'department',
        'designation',
        'qualification',
        'pfa_name',
        'state',
        'local_government',
        'senatorial_district',
        'job_categorization',
        'bank_name',
        'next_of_kin_relationship',
    ];

    private const CLEARABLE_PROFILE_SECTIONS = [
        'appointment' => ['File number', 'IPPIS number', 'Date of first appointment', 'PFA name', 'Pension PIN', 'Salary structure', 'Special categorization', 'Level', 'Step', 'Department', 'Designation'],
        'personal-details' => ['Title', 'Surname', 'First name', 'Other names', 'Maiden name', 'Gender', 'Date of birth', 'Marital status', 'Religion'],
        'contact-address' => ['Phone number', 'Residential address', 'City / town', 'State of residence', 'LGA of residence', 'Permanent address', 'State of origin', 'LGA of origin', 'Senatorial district'],
        'next-of-kin' => ['Next of kin surname', 'Next of kin first name', 'Next of kin relationship', 'Next of kin email', 'Next of kin phone number'],
        'job-categorization' => ['job_categories'],
        'education' => ['education', 'olevel_results'],
        'bank-details' => ['Bank name', 'Account name', 'Account number'],
    ];

    public function addLookupOption(Request $request)
    {
        $values = $request->validate([
            'category' => ['required', 'string', Rule::in(self::PERSONNEL_LOOKUP_CATEGORIES)],
            'label' => 'required|string|max:120',
            'parent' => [Rule::requiredIf(fn() => $request->input('category') === 'local_government'), 'nullable', 'string', 'max:120', Rule::in(Lookup::where('category', 'state')->where('active', true)->pluck('label')->all())],
        ]);
        $label = trim($values['label']);
        $label = preg_replace_callback(
            '/(^|[^\p{L}\p{N}])(\p{L})/u',
            fn($matches) => $matches[1] . Str::upper($matches[2]),
            $label,
        ) ?? $label;
        $option = Lookup::firstOrCreate(
            ['category' => $values['category'], 'label' => $label, 'parent' => $values['parent'] ?? null],
            ['active' => true, 'sort_order' => (int) Lookup::where('category', $values['category'])->max('sort_order') + 1],
        );
        if (!$option->active) $option->update(['active' => true]);

        return response()->json(['option' => $option->fresh(), 'message' => 'This option has been added and selected for your form.'], $option->wasRecentlyCreated ? 201 : 200);
    }

    public function show(Request $request)
    {
        $profile = $request->user()->profile()->firstOrCreate(['user_id' => $request->user()->id]);
        $data = $profile->data ?? [];
        $data['Date of first appointment'] = $profile->date_of_first_appointment?->format('Y-m-d')
            ?? ($data['Date of first appointment'] ?? null);
        $profile->setAttribute('data', $data);
        return response()->json(['profile' => $profile]);
    }

    public function update(Request $request)
    {
        if ($request->exists('clear_section')) {
            $section = $request->input('clear_section');
            if (!is_string($section) || $section === '') {
                throw ValidationException::withMessages([
                    'section' => ['Choose a valid personnel profile section to clear.'],
                ]);
            }

            return $this->clearSection($request, $section);
        }

        $profile = $request->user()->profile()->firstOrCreate(['user_id' => $request->user()->id]);
        $rules = [
            'data' => 'required|array|max:100',
            'data.Title' => 'nullable|string|max:60',
            'data.Surname' => 'nullable|string|max:120',
            'data.First name' => 'nullable|string|max:120',
            'data.Other names' => 'nullable|string|max:180',
            'data.Maiden name' => 'nullable|string|max:120',
            'data.Date of first appointment' => 'nullable|date|before_or_equal:today',
            'data.Pension PIN' => 'nullable|string|max:80',
            'data.Email' => ['required', 'email:rfc', 'max:254', Rule::in([$request->user()->email])],
            'data.Phone number' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9 ()-]{7,24}$/'],
            'data.Residential address' => 'nullable|string|max:500',
            'data.Permanent address' => 'nullable|string|max:500',
            'data.City / town' => 'nullable|string|max:120',
            'data.Next of kin surname' => 'nullable|string|max:120',
            'data.Next of kin first name' => 'nullable|string|max:120',
            'data.Next of kin email' => 'nullable|email:rfc|max:254',
            'data.Next of kin phone number' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9 ()-]{7,24}$/'],
            'data.Date of birth' => 'nullable|date|before:today',
            'data.File number' => 'nullable|string|max:80',
            'data.IPPIS number' => 'nullable|string|max:80',
            'data.Account number' => ['nullable', 'string', 'regex:/^[0-9]{6,20}$/'],
            'data.Account name' => 'nullable|string|max:160',
            'data.education' => 'sometimes|array|max:30',
            'data.education.*.institution' => 'nullable|string|max:200',
            'data.education.*.course' => 'nullable|string|max:200',
            'data.education.*.qualification' => ['nullable', 'string', 'max:120'],
            'data.education.*.year' => 'nullable|integer|between:1950,2100',
            'data.olevel_results' => 'sometimes|array|max:10',
            'data.olevel_results.*' => 'array',
            'data.olevel_results.*.exam_year' => 'nullable|integer|between:1950,' . now()->year,
            'data.olevel_results.*.exam_type' => 'nullable|string|max:80',
            'data.olevel_results.*.sitting' => 'sometimes|nullable|in:1st,2nd',
            'data.olevel_results.*.subjects' => 'sometimes|array|max:30',
            'data.olevel_results.*.subjects.*' => 'array',
            'data.olevel_results.*.subjects.*.subject' => 'nullable|string|max:120',
            'data.olevel_results.*.subjects.*.grade' => ['nullable', Rule::in(['A1', 'B2', 'B3', 'C4', 'C5', 'C6', 'D7', 'E8', 'F9'])],
            'data.job_categories' => 'sometimes|array|max:30',
            'data.job_categories.*' => ['string', 'max:120'],
        ];
        $lookupFields = [
            'Title' => 'title',
            'Gender' => 'gender',
            'Marital status' => 'marital_status',
            'Religion' => 'religion',
            'Salary structure' => 'salary_structure',
            'Special categorization' => 'special_categorization',
            'Level' => 'level',
            'Step' => 'step',
            'Department' => 'department',
            'Designation' => 'designation',
            'PFA name' => 'pfa_name',
            'State of residence' => 'state',
            'State of origin' => 'state',
            'LGA of residence' => 'local_government',
            'LGA of origin' => 'local_government',
            'Senatorial district' => 'senatorial_district',
            'Bank name' => 'bank_name',
            'Next of kin relationship' => 'next_of_kin_relationship',
        ];
        foreach ($lookupFields as $field => $category) {
            $query = Lookup::where('category', $category)->where('active', true);
            $parentField = match ($field) {
                'LGA of residence' => 'State of residence',
                'LGA of origin' => 'State of origin',
                default => null,
            };
            $selectedParent = $parentField ? $request->input('data.' . $parentField) : null;
            if ($parentField) $query->where('parent', $selectedParent);
            $allowed = $query->pluck('label')->all();
            $previousParent = $parentField ? ($profile->data[$parentField] ?? null) : null;
            if (!empty($profile->data[$field]) && (!$parentField || $previousParent === $selectedParent)) $allowed[] = $profile->data[$field];
            $rules['data.' . $field] = ['nullable', 'string', 'max:200', Rule::in($allowed)];
        }
        $educationQualifications = Lookup::where('category', 'qualification')->where('active', true)->pluck('label')->all();
        foreach ($profile->data['education'] ?? [] as $education) {
            if (!empty($education['qualification'])) $educationQualifications[] = $education['qualification'];
        }
        $rules['data.education.*.qualification'] = ['nullable', 'string', 'max:120', Rule::in(array_unique($educationQualifications))];
        $jobCategories = Lookup::where('category', 'job_categorization')->where('active', true)->pluck('label')->all();
        $jobCategories = array_merge($jobCategories, $profile->data['job_categories'] ?? []);
        $rules['data.job_categories.*'][] = Rule::in($jobCategories);
        $values = $request->validate($rules);
        $jobCategories = $values['data']['job_categories'] ?? ($profile->data['job_categories'] ?? []);
        $isJuniorStaff = collect(is_array($jobCategories) ? $jobCategories : [])
            ->contains(fn($category) => str_starts_with(strtolower(trim((string) $category)), 'junior staff'));
        $submittedOlevelResults = collect($values['data']['olevel_results'] ?? []);
        if ($isJuniorStaff) {
            $examResults = $submittedOlevelResults->filter(
                fn($exam) =>
                filled($exam['exam_year'] ?? null)
                    || filled($exam['exam_type'] ?? null)
                    || collect($exam['subjects'] ?? [])->contains(
                        fn($subject) =>
                        filled($subject['subject'] ?? null) || filled($subject['grade'] ?? null)
                    )
            )->values();
            $educationForRule = $values['data']['education'] ?? ($profile->data['education'] ?? []);
            $isNursingSchool = collect($educationForRule)->contains(fn($row) => str_contains(strtolower($row['institution'] ?? ''), 'school of nursing'));
            if ($isNursingSchool && $examResults->count() > 1) {
                throw ValidationException::withMessages(['data.olevel_results' => ['School of Nursing records may include one O’Level examination sitting only.']]);
            }
            foreach (
                $examResults->filter(fn($exam) => filled($exam['exam_type'] ?? null))->groupBy(function ($exam) {
                    $type = strtolower(trim($exam['exam_type']));
                    if (str_contains($type, 'wassce')) return 'waec';
                    if (str_contains($type, 'neco')) return 'neco';
                    if (str_contains($type, 'nabteb')) return 'nabteb';
                    return $type;
                }) as $body => $sittings
            ) {
                if ($sittings->count() > 2) {
                    throw ValidationException::withMessages(['data.olevel_results' => ["A maximum of two sittings is allowed for the {$body} examination body."]]);
                }
                $chosenSittings = $sittings->pluck('sitting')->filter()->values();
                if ($chosenSittings->count() > 1 && $chosenSittings->unique()->count() !== $chosenSittings->count()) {
                    throw ValidationException::withMessages(['data.olevel_results' => ["Choose a different sitting for each {$body} result."]]);
                }
            }
        }
        $data = array_replace_recursive($profile->data ?? [], $values['data']);
        if (array_key_exists('education', $values['data'])) $data['education'] = $values['data']['education'];
        if (array_key_exists('job_categories', $values['data'])) $data['job_categories'] = $values['data']['job_categories'];
        $olevelResultsToSave = $values['data']['olevel_results'] ?? ($profile->data['olevel_results'] ?? []);
        $data['olevel_results'] = collect($olevelResultsToSave)
            ->filter(
                fn($exam) =>
                filled($exam['exam_year'] ?? null)
                    || filled($exam['exam_type'] ?? null)
                    || collect($exam['subjects'] ?? [])->contains(
                        fn($subject) =>
                        filled($subject['subject'] ?? null) || filled($subject['grade'] ?? null)
                    )
            )
            ->map(function ($exam) {
                unset($exam['exam_number']);
                $exam['subjects'] = collect($exam['subjects'] ?? [])
                    ->filter(fn($subject) => filled($subject['subject'] ?? null) || filled($subject['grade'] ?? null))
                    ->values()->all();
                return $exam;
            })->values()->all();
        $profileReadyForReview = $this->profileReadyForReview($data);
        $profile->update([
            'data' => $data,
            'date_of_first_appointment' => $data['Date of first appointment'] ?? null,
            'file_number' => array_key_exists('File number', $data) ? $data['File number'] : $profile->file_number,
            'ippis_number' => array_key_exists('IPPIS number', $data) ? $data['IPPIS number'] : $profile->ippis_number,
            'surname' => array_key_exists('Surname', $data) ? $data['Surname'] : $profile->surname,
            'first_name' => array_key_exists('First name', $data) ? $data['First name'] : $profile->first_name,
            'status' => $profileReadyForReview ? 'in_review' : 'incomplete',
        ]);
        if (array_key_exists('Surname', $data) || array_key_exists('First name', $data)) {
            $displayName = trim(($data['First name'] ?? '') . ' ' . ($data['Surname'] ?? ''));
            $request->user()->update(['name' => $displayName !== '' ? $displayName : $request->user()->email]);
        }
        return response()->json(['profile' => $profile->fresh(), 'message' => 'Your profile has been saved.']);
    }

    public function clearSection(Request $request, string $section)
    {
        $fields = self::CLEARABLE_PROFILE_SECTIONS[$section] ?? null;
        if (!$fields) {
            throw ValidationException::withMessages([
                'section' => ['Choose a valid personnel profile section to clear.'],
            ]);
        }

        $user = $request->user();
        $profile = $user->profile()->firstOrCreate(['user_id' => $user->id]);
        $data = $profile->data ?? [];
        foreach ($fields as $field) unset($data[$field]);
        $data['Email'] = $data['Email'] ?? $user->email;

        DB::transaction(function () use ($profile, $user, $section, $data) {
            $profile->update([
                'data' => $data,
                'date_of_first_appointment' => $data['Date of first appointment'] ?? null,
                'file_number' => $data['File number'] ?? null,
                'ippis_number' => $data['IPPIS number'] ?? null,
                'surname' => $data['Surname'] ?? null,
                'first_name' => $data['First name'] ?? null,
                'status' => $this->profileReadyForReview($data) ? 'in_review' : 'incomplete',
            ]);

            if ($section === 'personal-details') {
                $user->update(['name' => $user->email]);
            }
        });

        return response()->json([
            'profile' => $profile->fresh(),
            'message' => 'The profile section was cleared.',
        ]);
    }

    private function profileReadyForReview(array $data): bool
    {
        $requiredFields = ['Title', 'Surname', 'First name', 'Gender', 'Date of birth', 'Email', 'Phone number', 'File number', 'IPPIS number', 'Date of first appointment', 'Department', 'Designation', 'Salary structure', 'Level', 'Step'];
        $isJuniorStaff = collect(is_array($data['job_categories'] ?? null) ? $data['job_categories'] : [])
            ->contains(fn($category) => str_starts_with(strtolower(trim((string) $category)), 'junior staff'));
        $hasCompleteOlevelResult = false;
        $olevelResults = is_array($data['olevel_results'] ?? null)
            ? $data['olevel_results']
            : [];

        foreach ($olevelResults as $exam) {
            if (
                !is_array($exam)
                || !filled($exam['exam_year'] ?? null)
                || !filled($exam['exam_type'] ?? null)
                || !filled($exam['sitting'] ?? null)
            ) {
                continue;
            }

            $subjects = is_array($exam['subjects'] ?? null)
                ? $exam['subjects']
                : [];
            foreach ($subjects as $subject) {
                if (
                    is_array($subject)
                    && filled($subject['subject'] ?? null)
                    && filled($subject['grade'] ?? null)
                ) {
                    $hasCompleteOlevelResult = true;
                    break 2;
                }
            }
        }

        return collect($requiredFields)->every(fn($field) => isset($data[$field]) && trim((string) $data[$field]) !== '')
            && (!$isJuniorStaff || $hasCompleteOlevelResult);
    }
}
