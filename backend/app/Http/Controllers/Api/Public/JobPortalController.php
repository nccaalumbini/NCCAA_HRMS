<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\Province;
use App\Services\JobApplicationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobPortalController extends Controller
{
    public function __construct(private readonly JobApplicationService $applications) {}

    public function categories(): JsonResponse
    {
        return ApiResponse::success([
            'items' => $this->applications->activeCategories(),
            'meta' => $this->applications->meta(),
        ]);
    }

    public function category(string $slug): JsonResponse
    {
        $category = $this->applications->activeCategory($slug);

        return ApiResponse::success(array_merge($category->summary(), ['fields' => $category->field_schema['fields'] ?? []]));
    }

    public function provinces(): JsonResponse
    {
        $provinces = Province::orderBy('code')->get(['id', 'name_en', 'name_ne', 'code']);

        return ApiResponse::success(['items' => $provinces]);
    }

    public function districts(string $province): JsonResponse
    {
        $districts = District::where('province_id', $province)
            ->orderBy('name_en')
            ->get(['id', 'province_id', 'name_en', 'name_ne', 'code']);

        return ApiResponse::success(['items' => $districts]);
    }

    public function trainingCenters(): JsonResponse
    {
        return ApiResponse::success(['items' => $this->applications->trainingCenters()]);
    }

    public function checkCadetNumber(Request $request): JsonResponse
    {
        $request->validate([
            'cadet_number' => ['required', 'string', 'max:50'],
        ]);

        return ApiResponse::success([
            'available' => $this->applications->cadetNumberAvailable((string) $request->string('cadet_number')),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $result = $this->applications->submit($request);

        return ApiResponse::success($result, 'Application submitted successfully.', 201);
    }
}
