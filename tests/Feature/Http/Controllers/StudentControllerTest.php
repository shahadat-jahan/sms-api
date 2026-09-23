<?php

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

describe('index', function (): void {
    test('lists students with their user account', function (): void {
        $admin = User::factory()->admin()->create();
        $student = Student::factory()->create(['roll' => 'ROLL-1001']);

        $this->actingAs($admin)->getJson(route('students.index'))
            ->assertOk()
            ->assertJsonPath('data.0.roll', 'ROLL-1001')
            ->assertJsonPath('data.0.user.email', $student->user->email)
            ->assertJsonStructure(['data', 'links', 'meta']);
    });

    test('searches by name, email or roll number', function (string $search, string $expectedRoll): void {
        $admin = User::factory()->admin()->create();
        $target = Student::factory()->create(['roll' => 'ROLL-2001']);
        $target->user()->update(['name' => 'Ayesha Siddiqua', 'email' => 'ayesha@school.test']);

        Student::factory()->create(['roll' => 'ROLL-2002']);

        $this->actingAs($admin)
            ->getJson(route('students.index', ['search' => $search]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.roll', $expectedRoll);
    })->with([
        'name' => ['Ayesha', 'ROLL-2001'],
        'email' => ['ayesha@school.test', 'ROLL-2001'],
        'roll' => ['ROLL-2002', 'ROLL-2002'],
    ]);

    test('applies the class filter together with the search term', function (): void {
        $admin = User::factory()->admin()->create();

        $match = Student::factory()->create(['roll' => 'ROLL-3001', 'class' => 'Class 10']);
        $match->user()->update(['name' => 'Nadia Islam']);

        $other = Student::factory()->create(['roll' => 'ROLL-3002', 'class' => 'Class 9']);
        $other->user()->update(['name' => 'Nadia Karim']);

        $this->actingAs($admin)
            ->getJson(route('students.index', ['search' => 'Nadia', 'class' => 'Class 10']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.roll', 'ROLL-3001');
    });

    test('filters by section', function (): void {
        $admin = User::factory()->admin()->create();
        Student::factory()->create(['roll' => 'ROLL-4001', 'section' => 'A']);
        Student::factory()->create(['roll' => 'ROLL-4002', 'section' => 'B']);

        $this->actingAs($admin)
            ->getJson(route('students.index', ['section' => 'B']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.roll', 'ROLL-4002');
    });

    test('paginates the result and caps the page size', function (): void {
        $admin = User::factory()->admin()->create();
        Student::factory()->count(3)->create();

        $this->actingAs($admin)
            ->getJson(route('students.index', ['per_page' => 500]))
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100)
            ->assertJsonCount(3, 'data');
    });

    test('returns 401 without a token', function (): void {
        $this->getJson(route('students.index'))->assertUnauthorized();
    });

    test('returns 403 for a student', function (): void {
        $this->actingAs(User::factory()->student()->create())
            ->getJson(route('students.index'))
            ->assertForbidden();
    });

    test('never exposes the password hash of a user', function (): void {
        $admin = User::factory()->admin()->create();
        Student::factory()->create(['roll' => 'ROLL-9001']);

        $this->actingAs($admin)
            ->getJson(route('students.index'))
            ->assertOk()
            ->assertJsonMissingPath('data.0.user.password')
            ->assertJsonMissingPath('data.0.user.remember_token');
    });
});

describe('store', function (): void {
    test('creates the user account and the student profile', function (): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson(route('students.store'), [
            'name' => 'New Student',
            'email' => 'new.student@school.test',
            'password' => 'secret123',
            'roll' => 'ROLL-5001',
            'class' => 'Class 8',
            'section' => 'B',
            'phone' => '01711111111',
            'address' => '45 Example Street',
        ])
            ->assertCreated()
            ->assertJsonPath('data.roll', 'ROLL-5001')
            ->assertJsonPath('data.user.email', 'new.student@school.test')
            ->assertJsonPath('data.user.role', 'student');

        $user = User::query()->where('email', 'new.student@school.test')->sole();

        expect($user->role)->toBe(UserRole::Student)
            ->and(Hash::check('secret123', $user->password))->toBeTrue();

        $this->assertDatabaseHas('students', [
            'user_id' => $user->id,
            'roll' => 'ROLL-5001',
            'class' => 'Class 8',
            'section' => 'B',
        ]);
    });

    test('returns 422 when the payload is empty', function (): void {
        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('students.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password', 'roll', 'class']);
    });

    test('returns 422 when the email or roll already exists', function (): void {
        $admin = User::factory()->admin()->create();
        $existing = Student::factory()->create(['roll' => 'ROLL-5100']);

        $this->actingAs($admin)->postJson(route('students.store'), [
            'name' => 'Duplicated Student',
            'email' => $existing->user->email,
            'password' => 'secret123',
            'roll' => 'ROLL-5100',
            'class' => 'Class 8',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'roll']);

        $this->assertDatabaseCount('students', 1);
    });

    test('returns 403 for a student', function (): void {
        $this->actingAs(User::factory()->student()->create())
            ->postJson(route('students.store'), [])
            ->assertForbidden();
    });
});

describe('show', function (): void {
    test('returns the student with the user account', function (): void {
        $admin = User::factory()->admin()->create();
        $student = Student::factory()->create(['roll' => 'ROLL-6001']);

        $this->actingAs($admin)->getJson(route('students.show', $student))
            ->assertOk()
            ->assertJsonPath('data.roll', 'ROLL-6001')
            ->assertJsonPath('data.user.id', $student->user_id);
    });

    test('returns 404 for an unknown student', function (): void {
        $this->actingAs(User::factory()->admin()->create())
            ->getJson(route('students.show', 999999))
            ->assertNotFound();
    });
});

describe('update', function (): void {
    test('updates the profile and the linked user account', function (): void {
        $admin = User::factory()->admin()->create();
        $student = Student::factory()->create(['roll' => 'ROLL-7001', 'class' => 'Class 7']);
        $student->user()->update(['name' => 'Old Name', 'email' => 'old.name@school.test']);

        $this->actingAs($admin)->putJson(route('students.update', $student), [
            'name' => 'Updated Name',
            'email' => 'updated.name@school.test',
            'class' => 'Class 11',
            'section' => 'C',
        ])
            ->assertOk()
            ->assertJsonPath('data.class', 'Class 11')
            ->assertJsonPath('data.section', 'C')
            ->assertJsonPath('data.user.name', 'Updated Name')
            ->assertJsonPath('data.user.email', 'updated.name@school.test');

        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'roll' => 'ROLL-7001',
            'class' => 'Class 11',
            'section' => 'C',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $student->user_id,
            'name' => 'Updated Name',
            'email' => 'updated.name@school.test',
        ]);
    });

    test('accepts the current email and roll number', function (): void {
        $admin = User::factory()->admin()->create();
        $student = Student::factory()->create(['roll' => 'ROLL-7002']);
        $student->user()->update(['email' => 'unchanged@school.test']);

        $this->actingAs($admin)->putJson(route('students.update', $student), [
            'email' => 'unchanged@school.test',
            'roll' => 'ROLL-7002',
        ])->assertOk();
    });

    test('returns 422 when the email belongs to another user', function (): void {
        $admin = User::factory()->admin()->create();
        $student = Student::factory()->create();
        $otherStudent = Student::factory()->create();

        $this->actingAs($admin)->putJson(route('students.update', $student), [
            'email' => $otherStudent->user->email,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    });

    test('returns 403 for a student', function (): void {
        $student = Student::factory()->create();

        $this->actingAs($student->user)
            ->putJson(route('students.update', $student), ['class' => 'Class 12'])
            ->assertForbidden();
    });
});

describe('destroy', function (): void {
    test('deletes the user account and the profile', function (): void {
        $admin = User::factory()->admin()->create();
        $student = Student::factory()->create();
        $user = $student->user;

        $this->actingAs($admin)
            ->deleteJson(route('students.destroy', $student))
            ->assertOk()
            ->assertJsonPath('message', 'Student deleted');

        $this->assertModelMissing($student);
        $this->assertModelMissing($user);
    });

    test('returns 403 for a student', function (): void {
        $student = Student::factory()->create();

        $this->actingAs($student->user)
            ->deleteJson(route('students.destroy', $student))
            ->assertForbidden();
    });
});

describe('myProfile', function (): void {
    test('returns the profile of the authenticated student', function (): void {
        $student = Student::factory()->create(['roll' => 'ROLL-8001']);
        $student->user()->update(['name' => 'Profile Owner']);
        $user = $student->user;

        $this->actingAs($user)->getJson(route('students.my-profile'))
            ->assertOk()
            ->assertJsonPath('data.roll', 'ROLL-8001')
            ->assertJsonPath('data.user.name', 'Profile Owner');
    });

    test('returns 404 when the student has no profile', function (): void {
        $this->actingAs(User::factory()->student()->create())
            ->getJson(route('students.my-profile'))
            ->assertNotFound();
    });

    test('returns only the profile owned by the authenticated student', function (): void {
        $otherStudent = Student::factory()->create(['roll' => 'ROLL-8002']);
        $student = Student::factory()->create(['roll' => 'ROLL-8003']);

        $this->actingAs($student->user)->getJson(route('students.my-profile'))
            ->assertOk()
            ->assertJsonPath('data.roll', 'ROLL-8003')
            ->assertJsonMissing(['roll' => $otherStudent->roll]);
    });

    test('returns 403 for an admin', function (): void {
        $this->actingAs(User::factory()->admin()->create())
            ->getJson(route('students.my-profile'))
            ->assertForbidden();
    });
});
