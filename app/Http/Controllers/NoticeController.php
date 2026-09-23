<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\SendNoticeRequest;
use App\Mail\StudentNotice;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NoticeController extends Controller
{
    /**
     * Email a notice to every student.
     */
    public function send(SendNoticeRequest $request): JsonResponse
    {
        $subject = $request->string('subject')->toString();
        $message = $request->string('message')->toString();

        $sent = 0;
        $failed = 0;

        User::query()
            ->where('role', UserRole::Student)
            ->lazy()
            ->each(function (User $student) use ($subject, $message, &$sent, &$failed): void {
                try {
                    Mail::to($student->email, $student->name)->send(new StudentNotice($subject, $message));

                    $sent++;
                } catch (Throwable $exception) {
                    report($exception);

                    $failed++;
                }
            });

        return response()->json([
            'message' => "Notice sent to {$sent} students",
            'sent' => $sent,
            'failed' => $failed,
        ]);
    }
}
