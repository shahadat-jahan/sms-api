<?php

namespace App\Http\Services;

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportExportService
{
    /**
     * Columns every import file must provide.
     *
     * @var array<int, string>
     */
    private const REQUIRED_COLUMNS = ['name', 'email', 'roll', 'class'];

    /**
     * Column order of the exported file.
     *
     * @var array<int, string>
     */
    private const EXPORT_COLUMNS = ['name', 'email', 'roll', 'class', 'section', 'phone', 'address'];

    /**
     * Password given to imported students that do not provide one.
     */
    private const DEFAULT_PASSWORD = 'password123';

    /**
     * CSV dialect: comma separated, double quoted, with the proprietary
     * backslash escaping disabled (PHP 8.4 deprecates relying on its default).
     */
    private const CSV_DELIMITER = ',';

    private const CSV_ENCLOSURE = '"';

    private const CSV_ESCAPE = '';

    /**
     * Import students from an uploaded CSV file.
     */
    public function import(string $csvPath): array
    {
        $handle = fopen($csvPath, 'r');

        if ($handle === false) {
            return [
                'message' => 'The uploaded file could not be read.',
                'errors' => [],
                'imported' => 0,
            ];
        }

        try {
            $header = $this->readHeader($handle);

            if ($header === null) {
                return [
                    'message' => 'The uploaded file is empty.',
                    'errors' => [],
                    'imported' => 0,
                ];
            }

            $missingColumns = array_diff(self::REQUIRED_COLUMNS, $header);

            if ($missingColumns !== []) {
                return [
                    'message' => 'Missing required columns: '.implode(', ', $missingColumns).'.',
                    'errors' => [],
                    'imported' => 0,
                ];
            }

            [$rows, $errors] = $this->readRows($handle, $header);

            if ($errors !== []) {
                return [
                    'message' => 'The import file contains invalid rows. No students were imported.',
                    'errors' => $errors,
                    'imported' => 0,
                ];
            }

            $imported = $this->persist($rows);

            return [
                'message' => "{$imported} students imported",
                'errors' => [],
                'imported' => $imported,
            ];
        } finally {
            fclose($handle);
        }
    }

    /**
     * Stream every student as a CSV download.
     */
    public function export(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, self::EXPORT_COLUMNS, self::CSV_DELIMITER, self::CSV_ENCLOSURE, self::CSV_ESCAPE);

            Student::query()
                ->with(['user' => fn ($query) => $query->select('id', 'name', 'email')])
                ->orderBy('roll')
                ->lazy()
                ->each(function (Student $student) use ($handle): void {
                    fputcsv($handle, [
                        $student->user->name,
                        $student->user->email,
                        $student->roll,
                        $student->class,
                        $student->section,
                        $student->phone,
                        $student->address,
                    ], self::CSV_DELIMITER, self::CSV_ENCLOSURE, self::CSV_ESCAPE);
                });

            fclose($handle);
        }, 'students.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Read and normalise the header row.
     *
     * @param  resource  $handle
     * @return array<int, string>|null
     */
    private function readHeader($handle): ?array
    {
        $header = fgetcsv($handle, null, self::CSV_DELIMITER, self::CSV_ENCLOSURE, self::CSV_ESCAPE);

        if ($header === false) {
            return null;
        }

        return array_map(
            fn ($column): string => Str::lower(trim((string) $column, " \t\n\r\0\x0B\xEF\xBB\xBF")),
            $header,
        );
    }

    /**
     * Validate every data row and collect the rows that are ready to persist.
     *
     * @param  resource  $handle
     * @param  array<int, string>  $header
     * @return array{0: array<int, array<string, string|null>>, 1: array<string, array<int, string>>}
     */
    private function readRows($handle, array $header): array
    {
        $rows = [];
        $errors = [];
        $seenEmails = [];
        $seenRolls = [];
        $line = 1;

        while (($values = fgetcsv($handle, null, self::CSV_DELIMITER, self::CSV_ENCLOSURE, self::CSV_ESCAPE)) !== false) {
            $line++;

            $values = array_pad(array_slice($values, 0, count($header)), count($header), null);

            if ($this->isEmptyRow($values)) {
                continue;
            }

            $row = [];

            foreach ($header as $index => $column) {
                $value = $values[$index] ?? null;
                $row[$column] = $value === null ? null : trim($value);
            }

            $validator = Validator::make($row, [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
                'password' => ['nullable', 'string', 'min:6', 'max:255'],
                'roll' => ['required', 'string', 'max:255', Rule::unique('students', 'roll')],
                'class' => ['required', 'string', 'max:255'],
                'section' => ['nullable', 'string', 'max:255'],
                'phone' => ['nullable', 'string', 'max:32'],
                'address' => ['nullable', 'string', 'max:500'],
            ]);

            if ($validator->fails()) {
                $errors["line {$line}"] = $validator->errors()->all();

                continue;
            }

            $email = (string) $row['email'];
            $roll = (string) $row['roll'];

            if (isset($seenEmails[$email]) || isset($seenRolls[$roll])) {
                $errors["line {$line}"] = ['The email or roll number is listed twice in this file.'];

                continue;
            }

            $seenEmails[$email] = true;
            $seenRolls[$roll] = true;
            $rows[] = $row;
        }

        return [$rows, $errors];
    }

    /**
     * Create the user account and profile of every validated row.
     *
     * @param  array<int, array<string, string|null>>  $rows
     */
    private function persist(array $rows): int
    {
        return DB::transaction(function () use ($rows): int {
            foreach ($rows as $row) {
                $password = $row['password'] ?? null;

                $user = User::query()->create([
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'password' => $password === null || $password === '' ? self::DEFAULT_PASSWORD : $password,
                    'role' => UserRole::Student,
                ]);

                $user->student()->create([
                    'roll' => $row['roll'],
                    'class' => $row['class'],
                    'section' => $row['section'] ?? null,
                    'phone' => $row['phone'] ?? null,
                    'address' => $row['address'] ?? null,
                ]);
            }

            return count($rows);
        });
    }

    /**
     * Determine whether the row holds no data at all.
     *
     * @param  array<int, string|null>  $values
     */
    private function isEmptyRow(array $values): bool
    {
        foreach ($values as $value) {
            if ($value !== null && trim($value) !== '') {
                return false;
            }
        }

        return true;
    }
}
