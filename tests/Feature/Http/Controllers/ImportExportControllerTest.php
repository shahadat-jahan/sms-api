<?php

use App\Models\Student;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;

/**
 * Build a fake CSV upload with the given content.
 */
function csvUpload(string $content, string $name = 'students.csv'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $content);
}

describe('import', function (): void {
    test('imports every valid row and creates the accounts', function (): void {
        $admin = User::factory()->admin()->create();

        $csv = implode("\n", [
            'name,email,password,roll,class,section,phone,address',
            'Csv Student,csv.student@school.test,secret123,CSV-0001,Class 10,A,01700000002,Dhaka',
            'Csv Student Two,csv.student2@school.test,,CSV-0002,Class 9,B,,',
        ]);

        $this->actingAs($admin)
            ->postJson(route('students.import'), ['file' => csvUpload($csv)])
            ->assertOk()
            ->assertJsonPath('imported', 2);

        $this->assertDatabaseHas('users', ['email' => 'csv.student@school.test', 'role' => 'student']);
        $this->assertDatabaseHas('students', ['roll' => 'CSV-0001', 'class' => 'Class 10', 'section' => 'A']);
        $this->assertDatabaseHas('students', ['roll' => 'CSV-0002', 'section' => 'B']);

        $defaulted = User::query()->where('email', 'csv.student2@school.test')->sole();

        expect(Hash::check('password123', $defaulted->password))->toBeTrue();
    });

    test('imports nothing when a row fails validation', function (): void {
        $admin = User::factory()->admin()->create();

        $csv = implode("\n", [
            'name,email,password,roll,class',
            'Valid Student,valid.import@school.test,secret123,CSV-1001,Class 10',
            'Missing Roll,no.roll@school.test,secret123,,Class 10',
        ]);

        $this->actingAs($admin)
            ->postJson(route('students.import'), ['file' => csvUpload($csv)])
            ->assertUnprocessable()
            ->assertJsonPath('errors.line 3', ['The roll field is required.']);

        $this->assertDatabaseMissing('users', ['email' => 'valid.import@school.test']);
        $this->assertDatabaseCount('students', 0);
    });

    test('rejects a file that misses required columns', function (): void {
        $admin = User::factory()->admin()->create();

        $csv = implode("\n", ['name,email', 'No Columns,no.columns@school.test']);

        $this->actingAs($admin)
            ->postJson(route('students.import'), ['file' => csvUpload($csv)])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Missing required columns: roll, class.');
    });

    test('rejects a duplicate email inside the file', function (): void {
        $admin = User::factory()->admin()->create();

        $csv = implode("\n", [
            'name,email,roll,class',
            'First Duplicate,duplicate.import@school.test,CSV-2001,Class 10',
            'Second Duplicate,duplicate.import@school.test,CSV-2002,Class 10',
        ]);

        $this->actingAs($admin)
            ->postJson(route('students.import'), ['file' => csvUpload($csv)])
            ->assertUnprocessable()
            ->assertJsonPath('errors.line 3', ['The email or roll number is listed twice in this file.']);

        $this->assertDatabaseCount('students', 0);
    });

    test('rejects a row whose roll number already exists', function (): void {
        $admin = User::factory()->admin()->create();
        Student::factory()->create(['roll' => 'CSV-3001']);

        $csv = implode("\n", [
            'name,email,roll,class',
            'Existing Roll,existing.roll@school.test,CSV-3001,Class 10',
        ]);

        $this->actingAs($admin)
            ->postJson(route('students.import'), ['file' => csvUpload($csv)])
            ->assertUnprocessable()
            ->assertJsonPath('errors.line 2', ['The roll has already been taken.']);
    });

    test('rejects a file that is not a csv', function (): void {
        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('students.import'), ['file' => UploadedFile::fake()->image('students.jpg')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    });

    test('returns 403 for a student', function (): void {
        $this->actingAs(User::factory()->student()->create())
            ->postJson(route('students.import'), ['file' => csvUpload('name,email,roll,class')])
            ->assertForbidden();
    });
});

describe('export', function (): void {
    test('streams every student as a csv download', function (): void {
        $admin = User::factory()->admin()->create();
        $student = Student::factory()->create(['roll' => 'EXPORT-0001', 'class' => 'Class 12', 'section' => null]);
        $student->user()->update(['name' => 'Export Person', 'email' => 'export.person@school.test']);

        Student::factory()->create(['roll' => 'EXPORT-0002']);

        $response = $this->actingAs($admin)->get(route('students.export'));

        $response->assertOk()->assertDownload('students.csv');

        $csv = $response->streamedContent();

        expect($csv)->toContain('name,email,roll,class,section,phone,address')
            ->and($csv)->toContain('Export Person')
            ->and($csv)->toContain('export.person@school.test')
            ->and($csv)->toContain('EXPORT-0001')
            ->and($csv)->toContain('EXPORT-0002');
    });

    test('returns 403 for a student', function (): void {
        $this->actingAs(User::factory()->student()->create())
            ->get(route('students.export'))
            ->assertForbidden();
    });
});
