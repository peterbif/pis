<?php

namespace App\Http\Controllers;

use App\Models\Lookup;
use App\Models\PersonnelDocument;
use App\Models\PersonnelProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Crypt, Storage};
use Illuminate\Support\Str;
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

    public function addLookupOption(Request $request)
    {
        $values = $request->validate([
            'category' => ['required', 'string', Rule::in(self::PERSONNEL_LOOKUP_CATEGORIES)],
            'label' => 'required|string|max:120',
            'parent' => [Rule::requiredIf(fn() => $request->input('category') === 'local_government'), 'nullable', 'string', 'max:120', Rule::in(Lookup::where('category', 'state')->where('active', true)->pluck('label')->all())],
        ]);
        $option = Lookup::firstOrCreate(
            ['category' => $values['category'], 'label' => trim($values['label']), 'parent' => $values['parent'] ?? null],
            ['active' => true, 'sort_order' => (int) Lookup::where('category', $values['category'])->max('sort_order') + 1],
        );
        if (!$option->active) $option->update(['active' => true]);

        return response()->json(['option' => $option->fresh(), 'message' => 'This option has been added and selected for your form.'], $option->wasRecentlyCreated ? 201 : 200);
    }

    public function show(Request $request)
    {
        $profile = $request->user()->profile()->firstOrCreate(['user_id' => $request->user()->id]);
        return response()->json(['profile' => $profile, 'documents' => $request->user()->documents()->latest()->get()]);
    }

    public function update(Request $request)
    {
        $profile = $request->user()->profile()->firstOrCreate(['user_id' => $request->user()->id]);
        $rules = [
            'data' => 'required|array|max:100',
            'data.Title' => 'nullable|string|max:60',
            'data.Surname' => 'nullable|string|max:120',
            'data.First name' => 'nullable|string|max:120',
            'data.Other names' => 'nullable|string|max:180',
            'data.Maiden name' => 'nullable|string|max:120',
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
            'data.olevel_results.*.exam_year' => 'required|integer|between:1950,' . now()->year,
            'data.olevel_results.*.exam_type' => 'required|string|max:80',
            'data.olevel_results.*.exam_number' => 'required|string|max:60',
            'data.olevel_results.*.sitting' => 'required|in:1st,2nd',
            'data.olevel_results.*.subjects' => 'required|array|min:1|max:30',
            'data.olevel_results.*.subjects.*.subject' => 'required|string|max:120',
            'data.olevel_results.*.subjects.*.grade' => ['required', Rule::in(['A1', 'B2', 'B3', 'C4', 'C5', 'C6', 'D7', 'E8', 'F9'])],
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
        $values = $request->validate($rules); {
            $examResults = collect($values['data']['olevel_results'] ?? ($profile->data['olevel_results'] ?? []));
            $educationForRule = $values['data']['education'] ?? ($profile->data['education'] ?? []);
            $isNursingSchool = collect($educationForRule)->contains(fn($row) => str_contains(strtolower($row['institution'] ?? ''), 'school of nursing'));
            if ($isNursingSchool && $examResults->count() > 1) {
                throw ValidationException::withMessages(['data.olevel_results' => ['School of Nursing records may include one O’Level examination sitting only.']]);
            }
            foreach (
                $examResults->groupBy(function ($exam) {
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
                if ($sittings->pluck('sitting')->unique()->count() !== $sittings->count()) {
                    throw ValidationException::withMessages(['data.olevel_results' => ["Choose a different sitting for each {$body} result."]]);
                }
            }
        }
        $data = array_replace_recursive($profile->data ?? [], $values['data']);
        if (array_key_exists('education', $values['data'])) $data['education'] = $values['data']['education'];
        if (array_key_exists('olevel_results', $values['data'])) $data['olevel_results'] = $values['data']['olevel_results'];
        $requiredFields = ['Title', 'Surname', 'First name', 'Gender', 'Date of birth', 'Email', 'Phone number', 'File number', 'IPPIS number', 'Department', 'Designation', 'Salary structure', 'Level', 'Step'];
        $profileReadyForReview = collect($requiredFields)->every(fn($field) => isset($data[$field]) && trim((string) $data[$field]) !== '');
        $profile->update([
            'data' => $data,
            'file_number' => $data['File number'] ?? $profile->file_number,
            'ippis_number' => $data['IPPIS number'] ?? $profile->ippis_number,
            'surname' => $data['Surname'] ?? $profile->surname,
            'first_name' => $data['First name'] ?? $profile->first_name,
            'status' => $profileReadyForReview ? 'in_review' : 'incomplete',
        ]);
        if (!empty($data['Surname']) || !empty($data['First name'])) {
            $request->user()->update(['name' => trim(($data['First name'] ?? '') . ' ' . ($data['Surname'] ?? ''))]);
        }
        return response()->json(['profile' => $profile->fresh(), 'message' => 'Your profile has been saved.']);
    }

    public function upload(Request $request)
    {
        $values = $request->validate([
            'category' => 'required|in:passport,education_certificate',
            'file' => 'required|file|max:5120',
            'education_index' => 'required_if:category,education_certificate|nullable|integer|min:0|max:29',
        ]);
        $allowedMimes = $values['category'] === 'passport' ? 'image/jpeg,image/png,image/webp' : 'application/pdf,image/jpeg,image/png';
        abort_unless(in_array($values['file']->getMimeType(), explode(',', $allowedMimes), true), 422, 'This file type is not supported for that document.');
        $file = $values['file'];
        $path = 'personnel-documents/' . $request->user()->id . '/' . Str::uuid() . '.enc';
        $stored = Storage::disk('local')->put($path, Crypt::encryptString(file_get_contents($file->getRealPath())));
        abort_unless($stored, 503, 'We could not safely store your document right now. Please try again later.');
        $document = $request->user()->documents()->create([
            'category' => $values['category'],
            'education_index' => $values['category'] === 'education_certificate' ? ($values['education_index'] ?? null) : null,
            'original_name' => Str::limit(basename($file->getClientOriginalName()), 180, ''),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'storage_path' => $path,
        ]);
        return response()->json(['document' => $document, 'message' => 'Your document has been uploaded securely.'], 201);
    }

    public function download(Request $request, PersonnelDocument $document)
    {
        $isOwner = $document->user_id === $request->user()->id;
        $isHrAdmin = in_array($request->user()->role, ['admin', 'superadmin'], true)
            && $request->user()->hasPermission('view_personnel');
        abort_unless($isOwner || $isHrAdmin, 404);
        abort_unless(Storage::disk('local')->exists($document->storage_path), 404);
        $contents = Crypt::decryptString(Storage::disk('local')->get($document->storage_path));
        $filename = rawurlencode(Str::ascii($document->original_name));
        return response($contents, 200, [
            'Content-Type' => $document->mime_type,
            'Content-Disposition' => "attachment; filename*=UTF-8''{$filename}",
            'Content-Length' => (string) strlen($contents),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}
