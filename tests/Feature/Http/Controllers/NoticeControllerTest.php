<?php

use App\Mail\StudentNotice;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('sends the notice to every student', function (): void {
    Mail::fake();

    $admin = User::factory()->admin()->create();
    User::factory()->student()->create(['email' => 'first.student@school.test']);
    User::factory()->student()->create(['email' => 'second.student@school.test']);

    $this->actingAs($admin)
        ->postJson(route('notices.send'), [
            'subject' => 'Sports day',
            'message' => 'The sports day moves to Friday.',
        ])
        ->assertOk()
        ->assertJsonPath('sent', 2)
        ->assertJsonPath('failed', 0);

    Mail::assertSent(StudentNotice::class, 2);
    Mail::assertSent(StudentNotice::class, fn (StudentNotice $notice): bool => $notice->hasTo('first.student@school.test')
        && $notice->subjectLine === 'Sports day'
        && $notice->body === 'The sports day moves to Friday.');
});

test('does not send a notice when only an admin exists', function (): void {
    Mail::fake();

    $this->actingAs(User::factory()->admin()->create())
        ->postJson(route('notices.send'), [
            'subject' => 'Sports day',
            'message' => 'No students yet.',
        ])
        ->assertOk()
        ->assertJsonPath('sent', 0);

    Mail::assertNothingSent();
});

test('returns 422 when the payload is empty', function (): void {
    Mail::fake();

    $this->actingAs(User::factory()->admin()->create())
        ->postJson(route('notices.send'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['subject', 'message']);

    Mail::assertNothingSent();
});

test('returns 403 for a student', function (): void {
    Mail::fake();

    $this->actingAs(User::factory()->student()->create())
        ->postJson(route('notices.send'), [
            'subject' => 'Sports day',
            'message' => 'Students cannot send notices.',
        ])
        ->assertForbidden();

    Mail::assertNothingSent();
});
