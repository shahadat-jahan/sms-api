<?php

namespace App\Http\Services;

use App\Enums\UserRole;
use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Http\Resources\StudentResource;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class StudentService
{
    /**
     * List students with search, filters and pagination.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $search = $request->string('search')->trim()->toString();
        $class = $request->string('class')->trim()->toString();
        $section = $request->string('section')->trim()->toString();
        $perPage = min(max($request->integer('per_page', 10), 1), 100);

        $students = Student::query()
            ->with(['user' => fn ($query) => $query->select('id', 'name', 'email')])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->whereHas('user', function (Builder $userQuery) use ($search): void {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })->orWhere('roll', 'like', "%{$search}%");
                });
            })
            ->when($class !== '', fn (Builder $query): Builder => $query->where('class', $class))
            ->when($section !== '', fn (Builder $query): Builder => $query->where('section', $section))
            ->orderBy('roll')
            ->paginate($perPage)
            ->withQueryString();

        return StudentResource::collection($students);
    }

    /**
     * Create the user account and the student profile for a new student.
     */
    public function store(array $data): Student
    {
        return DB::transaction(function () use ($data): Student {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => UserRole::Student,
            ]);

            return $user->student()->create([
                'roll' => $data['roll'],
                'class' => $data['class'],
                'section' => $data['section'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
            ]);
        });
    }

    /**
     * Update the student profile and the linked user account.
     */
    public function update(Student $student, array $data): Student
    {
        DB::transaction(function () use ($data, $student): void {
            $student->update(Arr::only($data, ['roll', 'class', 'section', 'phone', 'address']));

            $userAttributes = Arr::only($data, ['name', 'email']);

            if ($userAttributes !== []) {
                $student->user()->update($userAttributes);
            }
        });

        return $student->load('user');
    }

    /**
     * Delete the student account. The profile row cascades with it.
     */
    public function destroy(Student $student): void
    {
        $student->user()->delete();
    }

    /**
     * Display the profile of the authenticated student.
     */
    public function getMyProfile(User $user): Student
    {
        return Student::query()
            ->with('user')
            ->whereBelongsTo($user)
            ->firstOrFail();
    }
}
