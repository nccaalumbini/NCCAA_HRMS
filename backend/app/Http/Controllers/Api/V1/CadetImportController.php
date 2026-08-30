<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CadetImportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CadetImportController extends Controller
{
    public function __construct(private readonly CadetImportService $importService) {}

    /**
     * Inspect an uploaded spreadsheet and return detected headers and sample rows.
     */
    public function inspect(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
        ]);

        $data = $this->importService->inspect($request->file('file'));

        return ApiResponse::success($data, 'Spreadsheet inspected successfully.');
    }

    /**
     * Parse spreadsheet, map columns, validate rows, and return complete preview.
     */
    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
            'mapping' => ['sometimes', 'array'],
        ]);

        $mapping = $request->input('mapping', []);
        if (is_string($mapping)) {
            $mapping = json_decode($mapping, true) ?? [];
        }

        $data = $this->importService->preview(
            $request->file('file'),
            $mapping,
            $request->user(),
        );

        return ApiResponse::success($data, 'Spreadsheet parsed and validated.');
    }

    /**
     * Commit approved validated rows into database.
     */
    public function commit(Request $request): JsonResponse
    {
        $request->validate([
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.cadet_number' => ['required', 'string'],
            'rows.*.name' => ['required', 'string'],
            'rows.*.email' => ['nullable', 'string'],
            'rows.*.phone' => ['nullable', 'string'],
            'rows.*.rank_id' => ['nullable', 'integer'],
            'rows.*.province_id' => ['nullable', 'integer'],
            'rows.*.district_id' => ['nullable', 'integer'],
            'rows.*.local_level' => ['nullable', 'string'],
            'rows.*.ward_number' => ['nullable', 'integer'],
        ]);

        $results = $this->importService->commit(
            $request->user(),
            $request->input('rows'),
        );

        return ApiResponse::success($results, "Successfully imported {$results['imported']} cadets.");
    }
}
