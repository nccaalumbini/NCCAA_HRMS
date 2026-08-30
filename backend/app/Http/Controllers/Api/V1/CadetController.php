<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCadetRequest;
use App\Http\Requests\Api\V1\UpdateCadetRequest;
use App\Models\Cadet;
use App\Services\CadetService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CadetController extends Controller
{
    public function __construct(private readonly CadetService $cadets) {}

    /**
     * List cadets within the caller's scope.
     */
    public function index(Request $request): JsonResponse
    {
        $cadets = $this->cadets->paginate($request->user(), $request->only([
            'search', 'status', 'rank_id', 'province_id', 'district_id',
        ]));

        return ApiResponse::success([
            'items' => collect($cadets->items())->map(fn (Cadet $cadet) => $this->cadetArray($cadet)),
            'meta' => [
                'current_page' => $cadets->currentPage(),
                'per_page' => $cadets->perPage(),
                'total' => $cadets->total(),
                'last_page' => $cadets->lastPage(),
            ],
        ]);
    }

    /**
     * Create a new cadet.
     */
    public function store(StoreCadetRequest $request): JsonResponse
    {
        $cadet = $this->cadets->create($request->user(), $request->validated());

        return ApiResponse::success($this->cadetArray($cadet), 'Cadet created successfully.', 201);
    }

    /**
     * Show a single cadet.
     */
    public function show(Request $request, Cadet $cadet): JsonResponse
    {
        $cadet = $this->cadets->show($request->user(), $cadet);

        return ApiResponse::success($this->cadetArray($cadet));
    }

    /**
     * Update a cadet's details.
     */
    public function update(UpdateCadetRequest $request, Cadet $cadet): JsonResponse
    {
        $cadet = $this->cadets->update($request->user(), $cadet, $request->validated());

        return ApiResponse::success($this->cadetArray($cadet), 'Cadet updated successfully.');
    }

    /**
     * Delete a cadet.
     */
    public function destroy(Request $request, Cadet $cadet): JsonResponse
    {
        $this->cadets->delete($request->user(), $cadet);

        return ApiResponse::success(null, 'Cadet deleted successfully.');
    }

    /**
     * Build a consistent cadet payload.
     *
     * @return array<string, mixed>
     */
    protected function cadetArray(Cadet $cadet): array
    {
        $cadet->loadMissing(['rank', 'province', 'district', 'localLevel', 'ward', 'profile']);

        return [
            'id' => $cadet->id,
            'uuid' => $cadet->uuid,
            'cadet_number' => $cadet->cadet_number,
            'name' => $cadet->name,
            'rank' => $cadet->rank ? [
                'id' => $cadet->rank->id,
                'name_en' => $cadet->rank->name_en,
                'name_ne' => $cadet->rank->name_ne,
                'short_code' => $cadet->rank->short_code,
            ] : null,
            'province_id' => $cadet->province_id,
            'district_id' => $cadet->district_id,
            'local_level_id' => $cadet->local_level_id,
            'ward_id' => $cadet->ward_id,
            'province' => $cadet->province?->only(['id', 'name_en', 'name_ne']),
            'district' => $cadet->district?->only(['id', 'name_en', 'name_ne']),
            'email' => $cadet->email,
            'phone' => $cadet->phone,
            'status' => $cadet->status,
            'user_id' => $cadet->user_id,
            'profile' => $cadet->profile?->makeHidden(['cadet_id', 'id', 'created_at', 'updated_at']),
            'created_at' => $cadet->created_at,
            'updated_at' => $cadet->updated_at,
        ];
    }
}
