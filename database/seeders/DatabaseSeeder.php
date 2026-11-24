<?php

namespace Database\Seeders;

use App\Models\Lookup;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            foreach ([
                'SUPERADMIN_PASSWORD' => 'ChangeThisSuperAdmin!2026',
                'ADMIN_PASSWORD' => 'ChangeThisAdmin!2026',
            ] as $key => $unsafeDefault) {
                $password = (string) env($key, '');
                if (strlen($password) < 14 || hash_equals($unsafeDefault, $password)) {
                    throw new \RuntimeException("Set a unique {$key} of at least 14 characters before seeding production.");
                }
            }
        }
        User::firstOrCreate(['email' => env('SUPERADMIN_EMAIL', 'superadmin@personnel.local')], [
            'name' => 'System Super Administrator', 'password' => env('SUPERADMIN_PASSWORD', 'ChangeThisSuperAdmin!2026'),
            'role' => 'superadmin', 'email_verified_at' => now(),
        ]);
        User::firstOrCreate(['email' => env('ADMIN_EMAIL', 'admin@personnel.local')], [
            'name' => 'Personnel Administrator', 'password' => env('ADMIN_PASSWORD', 'ChangeThisAdmin!2026'),
            'role' => 'admin', 'permissions' => ['view_dashboard', 'view_personnel', 'review_personnel', 'export_personnel', 'manage_invitations', 'manage_lookups'],
            'email_verified_at' => now(),
        ]);

        $lists = [
            'title' => ['Mr', 'Mrs', 'Ms', 'Dr', 'Prof'],
            'gender' => ['Female', 'Male', 'Prefer not to say'],
            'marital_status' => ['Single', 'Married', 'Divorced', 'Widowed', 'Prefer not to say'],
            'religion' => ['Christianity', 'Islam', 'Traditional', 'Other', 'Prefer not to say'],
            'salary_structure' => ['CONHESS', 'CONMESS', 'CONPASS', 'CONRAISS'],
            'special_categorization' => ['Industrial Training', 'Contract', 'Permanent', 'Secondment'],
            'level' => array_map(fn ($n) => (string) $n, range(1, 17)),
            'step' => array_map(fn ($n) => (string) $n, range(1, 15)),
            'department' => ['People & Culture', 'Finance', 'Programmes', 'Technology', 'Legal Services'],
            'designation' => ['Director', 'Assistant Director', 'Senior Officer', 'Programme Officer', 'IT Support Officer'],
            'job_categorization' => ['Clinical staff', 'Non-clinical staff', 'Admin staff', 'Technical staff', 'Nursing staff', 'Medical staff', 'Junior staff', 'Senior staff'],
            'qualification' => ['SSCE', 'OND', 'HND', 'BSc', 'BA', 'MSc', 'MA', 'PhD'],
            'pfa_name' => ['Access Pensions', 'ARM Pension Managers', 'Leadway Pensure', 'Stanbic IBTC Pension Managers', 'Trustfund Pensions'],
            'bank_name' => ['Access Bank', 'FirstBank', 'GTBank', 'UBA', 'Zenith Bank'],
            'next_of_kin_relationship' => ['Spouse', 'Parent', 'Child', 'Sibling', 'Other'],
            'state' => ['Abia', 'Adamawa', 'Akwa Ibom', 'Anambra', 'Bauchi', 'Bayelsa', 'Benue', 'Borno', 'Cross River', 'Delta', 'Ebonyi', 'Edo', 'Ekiti', 'Enugu', 'Federal Capital Territory', 'Gombe', 'Imo', 'Jigawa', 'Kaduna', 'Kano', 'Katsina', 'Kebbi', 'Kogi', 'Kwara', 'Lagos', 'Nasarawa', 'Niger', 'Ogun', 'Ondo', 'Osun', 'Oyo', 'Plateau', 'Rivers', 'Sokoto', 'Taraba', 'Yobe', 'Zamfara'],
        ];

        foreach ($lists as $category => $items) {
            foreach ($items as $index => $label) {
                Lookup::firstOrCreate(
                    ['category' => $category, 'label' => $label, 'parent' => null],
                    ['sort_order' => $index, 'active' => true],
                );
            }
        }

        $lgaRows = json_decode(file_get_contents(__DIR__.'/data/nigeria-lgas.json'), true, flags: JSON_THROW_ON_ERROR);
        foreach ($lgaRows as $index => $row) {
            Lookup::firstOrCreate(
                ['category' => 'local_government', 'label' => $row['name'], 'parent' => $row['state']],
                ['sort_order' => $index, 'active' => true],
            );
        }
    }
}
