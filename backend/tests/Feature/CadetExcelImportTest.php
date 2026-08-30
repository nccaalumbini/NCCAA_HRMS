<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Permission;
use App\Models\Province;
use App\Models\Rank;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class CadetExcelImportTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $roleSlug, array $permissionSlugs = [], array $attrs = []): User
    {
        $role = Role::firstOrCreate(['slug' => $roleSlug], ['name' => ucfirst(str_replace('-', ' ', $roleSlug))]);

        foreach ($permissionSlugs as $slug) {
            $permission = Permission::firstOrCreate(['slug' => $slug], [
                'name' => ucfirst($slug),
                'group' => explode('.', $slug)[0],
            ]);
            if (! $role->permissions->contains($permission->id)) {
                $role->permissions()->attach($permission->id);
            }
        }

        $user = User::factory()->create($attrs);
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    private function createSpreadsheetFile(array $rows, string $filename = 'cadets.xlsx'): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($rows);

        $tempPath = tempnam(sys_get_temp_dir(), 'test_xlsx_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        return new UploadedFile($tempPath, $filename, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_inspect_spreadsheet_detects_headers_and_sample_rows(): void
    {
        $admin = $this->actor(Role::SUPER_ADMIN, ['cadets.import']);
        $token = $admin->createToken('test')->plainTextToken;

        $file = $this->createSpreadsheetFile([
            ['Cadet No', 'Full Name', 'Email Address', 'Phone Number', 'Rank'],
            ['NCC-9901', 'Aarav Sharma', 'aarav@example.com', '9841000001', 'Cadet Sergeant'],
            ['NCC-9902', 'Bhawana Karki', 'bhawana@example.com', '9841000002', 'Cadet Corporal'],
        ]);

        $response = $this->withToken($token)->postJson('/api/v1/cadets/import/inspect', [
            'file' => $file,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.headers.0', 'Cadet No')
            ->assertJsonPath('data.detected_mapping.cadet_number', 'Cadet No')
            ->assertJsonPath('data.detected_mapping.name', 'Full Name')
            ->assertJsonPath('data.detected_mapping.email', 'Email Address');
    }

    public function test_preview_validates_rows_and_classifies_emails_and_duplicates(): void
    {
        $province = Province::factory()->create(['name_en' => 'Bagmati']);
        $district = District::factory()->create(['name_en' => 'Kathmandu', 'province_id' => $province->id]);
        $rank = Rank::factory()->create(['name_en' => 'Cadet Sergeant', 'short_code' => 'C/SGT']);

        $admin = $this->actor(Role::SUPER_ADMIN, ['cadets.import']);
        $token = $admin->createToken('test')->plainTextToken;

        $file = $this->createSpreadsheetFile([
            ['Cadet Number', 'Full Name', 'Email', 'Phone', 'Rank', 'Province', 'District'],
            ['NCC-101', 'Valid Cadet', 'valid@example.com', '9801111111', 'Cadet Sergeant', 'Bagmati', 'Kathmandu'],
            ['NCC-102', 'Missing Email Cadet', '', '9801111112', 'C/SGT', 'Bagmati', 'Kathmandu'],
            ['NCC-103', 'Invalid Email Cadet', 'not-an-email', '9801111113', 'Cadet Sergeant', 'Bagmati', 'Kathmandu'],
            ['NCC-101', 'Duplicate Number Cadet', 'dup@example.com', '9801111114', 'Cadet Sergeant', 'Bagmati', 'Kathmandu'],
        ]);

        $response = $this->withToken($token)->postJson('/api/v1/cadets/import/preview', [
            'file' => $file,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.summary.total_rows', 4)
            ->assertJsonPath('data.summary.valid_rows', 3) // NCC-101, NCC-102, NCC-103 valid (warnings for email), second NCC-101 duplicate error
            ->assertJsonPath('data.summary.duplicates', 1)
            ->assertJsonPath('data.summary.missing_emails', 1)
            ->assertJsonPath('data.summary.invalid_emails', 1);
    }

    public function test_commit_creates_users_and_cadets_transactionally(): void
    {
        $province = Province::factory()->create();
        $district = District::factory()->create(['province_id' => $province->id]);
        $rank = Rank::factory()->create();

        $admin = $this->actor(Role::SUPER_ADMIN, ['cadets.import']);
        $token = $admin->createToken('test')->plainTextToken;

        $rows = [
            [
                'cadet_number' => 'NCC-IMPORT-001',
                'name' => 'Suman Thapa',
                'email' => 'suman.thapa@example.com',
                'phone' => '9841223344',
                'rank_id' => $rank->id,
                'province_id' => $province->id,
                'district_id' => $district->id,
            ],
            [
                'cadet_number' => 'NCC-IMPORT-002',
                'name' => 'Prashant KC',
                'email' => 'prashant.kc@example.com',
                'phone' => '9841223345',
                'rank_id' => $rank->id,
                'province_id' => $province->id,
                'district_id' => $district->id,
            ],
        ];

        $response = $this->withToken($token)->postJson('/api/v1/cadets/import/commit', [
            'rows' => $rows,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.imported', 2)
            ->assertJsonPath('data.skipped', 0);

        $this->assertDatabaseHas('cadets', ['cadet_number' => 'NCC-IMPORT-001', 'name' => 'Suman Thapa']);
        $this->assertDatabaseHas('users', ['cadet_number' => 'NCC-IMPORT-001', 'email' => 'suman.thapa@example.com']);
        $this->assertDatabaseHas('cadets', ['cadet_number' => 'NCC-IMPORT-002', 'name' => 'Prashant KC']);
    }

    public function test_province_admin_cannot_commit_cadets_outside_their_province(): void
    {
        $province1 = Province::factory()->create();
        $province2 = Province::factory()->create();
        $district1 = District::factory()->create(['province_id' => $province1->id]);
        $district2 = District::factory()->create(['province_id' => $province2->id]);

        $provinceAdmin = $this->actor(Role::PROVINCE_ADMIN, ['cadets.import'], [
            'province_id' => $province1->id,
        ]);
        $token = $provinceAdmin->createToken('test')->plainTextToken;

        $rows = [
            [
                'cadet_number' => 'NCC-FOREIGN-01',
                'name' => 'Foreign Cadet',
                'province_id' => $province2->id,
                'district_id' => $district2->id,
            ],
        ];

        $response = $this->withToken($token)->postJson('/api/v1/cadets/import/commit', [
            'rows' => $rows,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.imported', 0)
            ->assertJsonPath('data.skipped', 1);

        $this->assertDatabaseMissing('cadets', ['cadet_number' => 'NCC-FOREIGN-01']);
    }

    public function test_unauthorized_user_cannot_inspect_or_commit_cadets(): void
    {
        $unauthorized = $this->actor(Role::CADET, []);
        $token = $unauthorized->createToken('test')->plainTextToken;

        $file = UploadedFile::fake()->create('test.xlsx', 100);

        $this->withToken($token)->postJson('/api/v1/cadets/import/inspect', ['file' => $file])
            ->assertStatus(403);

        $this->withToken($token)->postJson('/api/v1/cadets/import/commit', [
            'rows' => [['cadet_number' => 'NCC-01', 'name' => 'Name']],
        ])->assertStatus(403);
    }

    public function test_invalid_file_extension_fails_validation(): void
    {
        $admin = $this->actor(Role::SUPER_ADMIN, ['cadets.import']);
        $token = $admin->createToken('test')->plainTextToken;

        $badFile = UploadedFile::fake()->create('script.pdf', 100);

        $this->withToken($token)->postJson('/api/v1/cadets/import/inspect', ['file' => $badFile])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_csv_upload_and_preview_works_properly(): void
    {
        $admin = $this->actor(Role::SUPER_ADMIN, ['cadets.import']);
        $token = $admin->createToken('test')->plainTextToken;

        $csvContent = "Cadet Number,Full Name,Email\nNCC-CSV-1,Ramesh KC,ramesh@example.com\nNCC-CSV-2,Sita Sharma,sita@example.com";
        $file = UploadedFile::fake()->createWithContent('cadets.csv', $csvContent);

        $response = $this->withToken($token)->postJson('/api/v1/cadets/import/preview', [
            'file' => $file,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.summary.total_rows', 2)
            ->assertJsonPath('data.summary.valid_rows', 2)
            ->assertJsonPath('data.rows.0.cadet_number', 'NCC-CSV-1');
    }
}
