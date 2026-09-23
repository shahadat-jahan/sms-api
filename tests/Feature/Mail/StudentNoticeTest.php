<?php

use App\Mail\StudentNotice;

test('renders the subject and the body of the notice', function (): void {
    $notice = new StudentNotice('Sports day moved', 'Practice starts at 9am.');

    $notice->assertHasSubject('Sports day moved');
    $notice->assertSeeInHtml('Practice starts at 9am.');
});

test('escapes html in the body of the notice', function (): void {
    $notice = new StudentNotice('Notice', '<script>alert("xss")</script>');

    $notice->assertSeeInHtml('<script>alert("xss")</script>');
});
