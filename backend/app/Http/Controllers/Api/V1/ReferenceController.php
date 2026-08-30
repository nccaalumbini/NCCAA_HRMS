<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\LocalLevel;
use App\Models\Permission;
use App\Models\Province;
use App\Models\Rank;
use App\Models\Role;
use App\Models\Ward;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferenceController extends Controller
{
    /**
     * List the assignable roles.
     */
    public function roles(): JsonResponse
    {
        $roles = Role::orderBy('name')->get(['id', 'name', 'slug']);

        return ApiResponse::success(['items' => $roles]);
    }

    /**
     * List all permissions grouped by their category.
     */
    public function permissions(): JsonResponse
    {
        $permissions = Permission::orderBy('group')->orderBy('name')->get(['id', 'name', 'slug', 'group']);

        $grouped = collect($permissions->groupBy('group'))->map(function ($group) {
            return [
                'group' => $group->first()->group,
                'items' => $group->values(),
            ];
        })->values();

        return ApiResponse::success(['items' => $grouped]);
    }

    /**
     * List the available ranks.
     */
    public function ranks(): JsonResponse
    {
        $ranks = Rank::orderBy('display_order')
            ->orderBy('name_en')
            ->get(['id', 'name_en', 'name_ne', 'short_code']);

        return ApiResponse::success(['items' => $ranks]);
    }

    /**
     * List all provinces.
     */
    public function provinces(): JsonResponse
    {
        $provinces = Province::orderBy('code')->get(['id', 'name_en', 'name_ne', 'code']);

        return ApiResponse::success(['items' => $provinces]);
    }

    /**
     * List districts, optionally filtered by province.
     */
    public function districts(Request $request): JsonResponse
    {
        $request->validate([
            'province_id' => ['nullable', 'integer', 'exists:provinces,id'],
        ]);

        $query = District::query();

        if ($request->filled('province_id')) {
            $query->where('province_id', $request->integer('province_id'));
        }

        $districts = $query->orderBy('name_en')->get(['id', 'province_id', 'name_en', 'name_ne', 'code']);

        return ApiResponse::success(['items' => $districts]);
    }

    /**
     * List local levels, optionally filtered by district.
     */
    public function localLevels(Request $request): JsonResponse
    {
        $request->validate([
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
        ]);

        $query = LocalLevel::query();

        if ($request->filled('district_id')) {
            $query->where('district_id', $request->integer('district_id'));
        }

        $localLevels = $query->orderBy('name_en')->get(['id', 'district_id', 'name_en', 'name_ne', 'type']);

        return ApiResponse::success(['items' => $localLevels]);
    }

    /**
     * List wards, optionally filtered by local level.
     */
    public function wards(Request $request): JsonResponse
    {
        $request->validate([
            'local_level_id' => ['nullable', 'integer', 'exists:local_levels,id'],
        ]);

        $query = Ward::query();

        if ($request->filled('local_level_id')) {
            $query->where('local_level_id', $request->integer('local_level_id'));
        }

        $wards = $query->orderBy('ward_number')->get(['id', 'local_level_id', 'ward_number', 'name_en', 'name_ne']);

        return ApiResponse::success(['items' => $wards]);
    }
}
