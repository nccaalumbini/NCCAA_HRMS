<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\RecruitmentCandidate;
use App\Services\ApplicationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ApplicationController extends Controller
{
    public function __construct(private readonly ApplicationService $applications) {}

    public function index(Request $request): JsonResponse
    {
        $applications = $this->applications->paginate(
            $request->user(),
            $request->only(['search', 'stage', 'skill_category_id', 'division', 'province_id', 'district_id', 'date_from', 'date_to', 'sort']),
            $request->integer('per_page', 15) ?: 15,
        );

        return ApiResponse::success([
            'items' => collect($applications->items())
                ->map(fn (RecruitmentCandidate $application) => $this->applications->applicationPayload($application)),
            'meta' => [
                'current_page' => $applications->currentPage(),
                'per_page' => $applications->perPage(),
                'total' => $applications->total(),
                'last_page' => $applications->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, RecruitmentCandidate $application): JsonResponse
    {
        $this->applications->show($request->user(), $application);

        return ApiResponse::success(array_merge(
            $this->applications->applicationPayload($application),
            $this->applications->detailPayload($application),
        ));
    }

    public function changeStage(Request $request, RecruitmentCandidate $application): JsonResponse
    {
        try {
            $application = $this->applications->changeStage($request->user(), $application, $request->all());

            return ApiResponse::success(
                array_merge($this->applications->applicationPayload($application), $this->applications->detailPayload($application)),
                'Application stage updated successfully.',
            );
        } catch (ValidationException $exception) {
            return ApiResponse::validationError('Stage could not be changed.', $exception->errors());
        }
    }

    public function addNote(Request $request, RecruitmentCandidate $application): JsonResponse
    {
        $note = $this->applications->addNote($request->user(), $application, $request->all());

        return ApiResponse::success([
            'id' => $note->id,
            'application_id' => $note->application_id,
            'note' => $note->note,
            'created_at' => $note->created_at,
            'user' => $note->user?->only(['id', 'name']),
        ], 'Note added successfully.', 201);
    }

    public function stats(Request $request): JsonResponse
    {
        return ApiResponse::success($this->applications->stats($request->user()));
    }
}
