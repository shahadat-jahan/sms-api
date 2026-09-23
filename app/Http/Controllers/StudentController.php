<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Http\Resources\StudentResource;
use App\Http\Services\StudentService;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class StudentController extends Controller
{
    public function __construct(
        private readonly StudentService $studentService
    ) {
    }

    /**
     * List students with search, filters and pagination.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return $this->studentService->index($request);
    }

    /**
     * Create the user account and the student profile for a new student.
     */
    public function store(StoreStudentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $student = $this->studentService->store($data);

        return (new StudentResource($student->load('user')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Display a single student.
     */
    public function show(Student $student): StudentResource
    {
        return new StudentResource($student->load('user'));
    }

    /**
     * Update the student profile and the linked user account.
     */
    public function update(UpdateStudentRequest $request, Student $student): StudentResource
    {
        $data = $request->validated();
        $student = $this->studentService->update($student, $data);

        return new StudentResource($student);
    }

    /**
     * Delete the student account. The profile row cascades with it.
     */
    public function destroy(Student $student): JsonResponse
    {
        $this->studentService->destroy($student);

        return response()->json(['message' => 'Student deleted']);
    }

    /**
     * Display the profile of the authenticated student.
     */
    public function myProfile(Request $request): StudentResource
    {
        $student = $this->studentService->getMyProfile($request->user());

        return new StudentResource($student);
    }
}
