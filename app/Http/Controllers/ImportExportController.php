<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportStudentsRequest;
use App\Http\Services\ImportExportService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportExportController extends Controller
{
    public function __construct(
        private readonly ImportExportService $importExportService
    ) {
    }

    /**
     * Import students from an uploaded CSV file.
     */
    public function import(ImportStudentsRequest $request): JsonResponse
    {
        $result = $this->importExportService->import($request->csvPath());

        if ($result['imported'] === 0) {
            return response()->json($result, 422);
        }

        return response()->json([
            'message' => $result['message'],
            'imported' => $result['imported'],
        ]);
    }

    /**
     * Stream every student as a CSV download.
     */
    public function export(): StreamedResponse
    {
        return $this->importExportService->export();
    }
}
