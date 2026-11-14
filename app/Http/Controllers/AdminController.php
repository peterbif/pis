<?php
namespace App\Http\Controllers;

use App\Models\{ApprovedEmail,Lookup,PersonnelProfile,User};
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AdminController
{
    private const ADMIN_PERMISSIONS = [
        'view_dashboard', 'view_personnel', 'review_personnel', 'export_personnel',
        'manage_invitations', 'manage_lookups',
    ];

    private function filteredProfiles(Request $request)
    {
        $query = PersonnelProfile::query()->with('user:id,name,email');
        foreach (['name', 'email', 'ippis_no', 'file_no'] as $filter) {
            $value = trim((string) $request->query($filter, ''));
            if ($value === '') continue;
            if ($filter === 'name') {
                $query->where(fn ($sub) => $sub->where('surname', 'like', "%{$value}%")
                    ->orWhere('first_name', 'like', "%{$value}%")
                    ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$value}%")));
            } elseif ($filter === 'email') {
                $query->whereHas('user', fn ($user) => $user->where('email', 'like', "%{$value}%"));
            } else {
                $query->where($filter === 'ippis_no' ? 'ippis_number' : 'file_number', 'like', "%{$value}%");
            }
        }
        return $query;
    }

    public function dashboard(Request $request)
    {
        $counts = PersonnelProfile::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $recent = $request->user()->hasPermission('view_personnel')
            ? PersonnelProfile::with('user:id,name,email')->orderByDesc('updated_at')->limit(5)->get()->map(function (PersonnelProfile $profile) {
            $data = $profile->data ?? [];
            return [
                'id' => $profile->id,
                'name' => $profile->user?->name ?: $profile->user?->email,
                'email' => $profile->user?->email,
                'role' => $data['Designation'] ?? 'Personnel',
                'dept' => $data['Department'] ?? '—',
                'file_number' => $profile->file_number,
                'status' => $profile->status,
                'initials' => collect(preg_split('/\s+/', trim($profile->user?->name ?? 'UC')))->filter()->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode(''),
            ];
        }) : collect();
        return response()->json([
            'total_personnel' => (int) $counts->sum(),
            'complete_profiles' => (int) ($counts['complete'] ?? 0),
            'in_review' => (int) ($counts['in_review'] ?? 0),
            'incomplete_profiles' => (int) ($counts['incomplete'] ?? 0),
            'departments' => Lookup::where('category', 'department')->where('active', true)->count(),
            'pending_invitations' => ApprovedEmail::whereNull('used_at')->count(),
            'recent_personnel' => $recent,
        ]);
    }

    public function lookups()
    {
        $rows = Lookup::where('active', true)->orderBy('category')->orderBy('sort_order')->get();
        $lookups = $rows->groupBy('category')->map(fn ($items) => $items->pluck('label')->values());
        $lookups['local_government_by_state'] = $rows->where('category', 'local_government')->whereNotNull('parent')
            ->groupBy('parent')->map(fn ($items) => $items->pluck('label')->values());
        return response()->json($lookups);
    }

    public function allLookups()
    {
        return Lookup::orderBy('category')->orderBy('sort_order')->get();
    }

    public function addOption(Request $request)
    {
        $values = $request->validate([
            'category' => 'required|string|max:80', 'label' => 'required|string|max:120',
            'parent' => [Rule::requiredIf(fn () => Str::snake((string) $request->input('category')) === 'local_government'), 'nullable', 'string', 'max:120', Rule::in(Lookup::where('category', 'state')->where('active', true)->pluck('label')->all())],
            'sort_order' => 'sometimes|integer|min:0|max:10000',
        ]);
        $values['category'] = Str::snake($values['category']);
        $option = Lookup::firstOrCreate(
            ['category' => $values['category'], 'label' => trim($values['label']), 'parent' => $values['parent'] ?? null],
            ['active' => true, 'sort_order' => $values['sort_order'] ?? 0],
        );
        if (!$option->active) $option->update(['active' => true]);
        return response()->json($option, $option->wasRecentlyCreated ? 201 : 200);
    }

    public function importLookups(Request $request)
    {
        $values = $request->validate(['file' => 'required|file|mimes:csv,txt|max:10240']);
        $stream = fopen($values['file']->getRealPath(), 'r');
        if (!$stream) return response()->json(['message' => 'The CSV file could not be read.'], 422);
        $line = 0; $imported = 0; $skipped = 0; $errors = [];
        $stateNames = Lookup::where('category', 'state')->where('active', true)->pluck('label')->all();
        while (($row = fgetcsv($stream)) !== false) {
            $line++;
            $category = Str::snake(trim((string) ($row[0] ?? '')));
            $label = trim((string) ($row[1] ?? ''));
            $parent = trim((string) ($row[2] ?? '')) ?: null;
            if ($line === 1 && $category === 'category' && strtolower($label) === 'label') continue;
            if (!preg_match('/^[a-z][a-z0-9_]{0,79}$/', $category) || $label === '' || mb_strlen($label) > 120 || ($parent !== null && mb_strlen($parent) > 120)) {
                $errors[] = "Row {$line}: provide category, label, and an optional parent (maximum 120 characters).";
                continue;
            }
            if ($category === 'local_government' && !in_array($parent, $stateNames, true)) {
                $errors[] = "Row {$line}: select a configured state as the parent for this local government area.";
                continue;
            }
            $option = Lookup::firstOrCreate(['category' => $category, 'label' => $label, 'parent' => $parent], ['active' => true]);
            if ($option->wasRecentlyCreated) $imported++;
            elseif (!$option->active) { $option->update(['active' => true]); $imported++; }
            else $skipped++;
            if ($line >= 10001) { $errors[] = 'Import stopped at the 10,000-row limit.'; break; }
        }
        fclose($stream);
        return response()->json(['imported' => $imported, 'skipped' => $skipped, 'errors' => array_slice($errors, 0, 100)]);
    }

    public function updateOption(Request $request, Lookup $lookup)
    {
        $values = $request->validate([
            'label' => ['sometimes', 'required', 'string', 'max:120', Rule::unique('lookups', 'label')->where('category', $lookup->category)->ignore($lookup->id)],
            'parent' => [$lookup->category === 'local_government' ? 'required' : 'sometimes', 'nullable', 'string', 'max:120', Rule::in(Lookup::where('category', 'state')->where('active', true)->pluck('label')->all())],
            'active' => 'sometimes|boolean', 'sort_order' => 'sometimes|integer|min:0|max:10000',
        ]);
        $lookup->update($values);
        return response()->json($lookup->fresh());
    }

    public function deleteOption(Lookup $lookup)
    {
        $lookup->update(['active' => false]);
        return response()->noContent();
    }

    public function invite(Request $request)
    {
        $values = $request->validate(['email' => 'required|email:rfc|max:254']);
        $email = Str::lower($values['email']);
        if (User::where('email', $email)->exists()) return response()->json(['message' => 'An account already exists for this email.'], 422);
        $invitation = ApprovedEmail::firstOrCreate(['email' => $email], ['created_by' => $request->user()->id]);
        if ($invitation->used_at) return response()->json(['message' => 'This invitation has already been used.'], 422);
        return response()->json(['invitation' => $invitation], $invitation->wasRecentlyCreated ? 201 : 200);
    }

    public function import(Request $request)
    {
        $values = $request->validate(['file' => 'required|file|mimes:csv,txt|max:10240']);
        $stream = fopen($values['file']->getRealPath(), 'r');
        if (!$stream) return response()->json(['message' => 'The CSV file could not be read.'], 422);
        $first = fgetcsv($stream);
        $header = strtolower(trim((string) ($first[0] ?? '')));
        $line = 1;
        if (in_array($header, ['email', 'email address', 'personal email'], true)) { $first = null; $line = 2; }
        $imported = 0; $skipped = 0; $errors = [];
        do {
            $row = $first ?? fgetcsv($stream);
            $first = null;
            if ($row === false) break;
            $email = Str::lower(trim((string) ($row[0] ?? '')));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Row {$line}: invalid email.";
            elseif (User::where('email', $email)->exists()) $skipped++;
            else {
                $invite = ApprovedEmail::firstOrCreate(['email' => $email], ['created_by' => $request->user()->id]);
                if ($invite->wasRecentlyCreated) $imported++; else $skipped++;
            }
            $line++;
            if ($line > 10002) { $errors[] = 'Import stopped at the 10,000-row limit.'; break; }
        } while (true);
        fclose($stream);
        return response()->json(['imported' => $imported, 'skipped' => $skipped, 'errors' => array_slice($errors, 0, 100)]);
    }

    public function directory(Request $request)
    {
        $perPage = min(max((int) $request->query('per_page', 25), 1), 100);
        return $this->filteredProfiles($request)->orderByDesc('updated_at')->paginate($perPage);
    }

    public function showPersonnel(PersonnelProfile $profile)
    {
        $profile->load(['user:id,name,email', 'user.documents:id,user_id,category,original_name,mime_type,size_bytes,created_at']);
        return response()->json(['profile' => $profile]);
    }

    public function updatePersonnelStatus(Request $request, PersonnelProfile $profile)
    {
        $values = $request->validate(['status' => ['required', Rule::in(['incomplete', 'in_review', 'complete'])]]);
        $profile->update(['status' => $values['status']]);
        return response()->json(['profile' => $profile->fresh(), 'message' => 'Personnel profile status updated.']);
    }

    private function flatten(array $data, string $prefix = ''): array
    {
        $output = [];
        foreach ($data as $key => $value) {
            $label = $prefix === '' ? (string) $key : $prefix.' · '.$key;
            if (preg_match('/upload|passport|photo|attachment|document|_path|certificate.*(file|path)/i', (string) $key)) continue;
            if (is_array($value)) {
                if (array_is_list($value)) {
                    if (!$value) continue;
                    if (!is_array($value[0])) $output[$label] = implode('; ', array_map(fn ($item) => (string) $item, $value));
                    else foreach ($value as $index => $item) if (is_array($item)) $output += $this->flatten($item, $label.' '.($index + 1));
                } else $output += $this->flatten($value, $label);
            } elseif ($value !== null) $output[$label] = is_bool($value) ? ($value ? 'Yes' : 'No') : (string) $value;
        }
        return $output;
    }

    public function export(Request $request)
    {
        $profiles = $this->filteredProfiles($request)->with('user:id,name,email')->orderBy('id')->get();
        $rows = []; $headers = ['Personnel name', 'Personnel email', 'Profile status'];
        foreach ($profiles as $profile) {
            $row = ['Personnel name' => $profile->user?->name ?? trim(($profile->first_name ?? '').' '.($profile->surname ?? '')),
                'Personnel email' => $profile->user?->email ?? '', 'Profile status' => $profile->status];
            $row += $this->flatten($profile->data ?? []);
            $headers = array_values(array_unique([...$headers, ...array_keys($row)])); $rows[] = $row;
        }
        $workbook = new Spreadsheet(); $sheet = $workbook->getActiveSheet(); $sheet->setTitle('Personnel');
        foreach ($headers as $i => $header) $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($i + 1).'1', $header, DataType::TYPE_STRING);
        foreach ($rows as $rowIndex => $row) foreach ($headers as $columnIndex => $header) {
            $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($columnIndex + 1).($rowIndex + 2), (string) ($row[$header] ?? ''), DataType::TYPE_STRING);
        }
        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->freezePane('A2'); $sheet->setAutoFilter('A1:'.$lastColumn.max(1, count($rows) + 1));
        return response()->streamDownload(fn () => (new Xlsx($workbook))->save('php://output'), 'personnel_records.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function createAdmin(Request $request)
    {
        $values = $request->validate([
            'name' => 'required|string|max:120', 'email' => 'required|email:rfc|unique:users,email|max:254',
            'password' => ['required', 'confirmed', Password::min(14)->mixedCase()->numbers()->symbols()],
            'permissions' => 'required|array|min:1',
            'permissions.*' => ['required', 'string', Rule::in(self::ADMIN_PERMISSIONS)],
        ]);
        unset($values['password_confirmation']);
        $values['email'] = Str::lower($values['email']);
        $user = User::create([...$values, 'role' => 'admin', 'email_verified_at' => now()]);
        return response()->json(['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role, 'permissions' => $user->permissions], 201);
    }

    public function administrators()
    {
        return User::where('role', 'admin')->orderBy('name')->get(['id', 'name', 'email', 'permissions', 'created_at']);
    }

    public function updateAdminPermissions(Request $request, User $user)
    {
        abort_unless($user->role === 'admin', 404, 'That administrator account could not be found.');
        $values = $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => ['required', 'string', Rule::in(self::ADMIN_PERMISSIONS)],
        ]);
        $user->update(['permissions' => array_values(array_unique($values['permissions']))]);

        return response()->json(['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'permissions' => $user->permissions]);
    }
}
