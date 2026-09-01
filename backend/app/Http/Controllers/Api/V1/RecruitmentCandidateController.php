<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ImportRecruitmentCandidatesRequest;
use App\Http\Requests\Api\V1\PromoteRecruitmentCandidateRequest;
use App\Http\Requests\Api\V1\SendRecruitmentCommunicationRequest;
use App\Http\Requests\Api\V1\StoreRecruitmentCandidateRequest;
use App\Http\Requests\Api\V1\UpdateRecruitmentCandidateRequest;
use App\Models\RecruitmentCandidate;
use App\Services\RecruitmentCandidateService;
use App\Services\RecruitmentCommunicationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecruitmentCandidateController extends Controller
{
    public function __construct(
        private readonly RecruitmentCandidateService $candidates,
        private readonly RecruitmentCommunicationService $communications,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $candidates = $this->candidates->paginate($request->user(), $request->only(['search', 'status', 'province_id', 'district_id']));

        return ApiResponse::success([
            'items' => collect($candidates->items())->map(fn (RecruitmentCandidate $candidate) => $this->candidateArray($candidate)),
            'meta' => ['current_page' => $candidates->currentPage(), 'per_page' => $candidates->perPage(), 'total' => $candidates->total(), 'last_page' => $candidates->lastPage()],
        ]);
    }

    public function import(ImportRecruitmentCandidatesRequest $request): JsonResponse
    {
        return ApiResponse::success($this->candidates->importCsv($request->file('file')), 'Recruitment import completed.');
    }

    public function store(StoreRecruitmentCandidateRequest $request): JsonResponse
    {
        $candidate = $this->candidates->store($request->user(), $request->validated());

        return ApiResponse::success($this->candidateArray($candidate), 'Candidate created successfully.', 201);
    }

    public function checkContact(Request $request): JsonResponse
    {
        $contactNumber = (string) $request->query('contact_number', '');

        return ApiResponse::success([
            'contact_number' => $contactNumber,
            'taken' => $this->candidates->contactNumberTaken($contactNumber),
        ]);
    }

    public function show(Request $request, RecruitmentCandidate $candidate): JsonResponse
    {
        return ApiResponse::success($this->candidateArray($this->candidates->show($request->user(), $candidate)));
    }

    public function update(UpdateRecruitmentCandidateRequest $request, RecruitmentCandidate $candidate): JsonResponse
    {
        return ApiResponse::success($this->candidateArray($this->candidates->update($request->user(), $candidate, $request->validated())), 'Candidate updated successfully.');
    }

    public function destroy(Request $request, RecruitmentCandidate $candidate): JsonResponse
    {
        $this->candidates->delete($request->user(), $candidate);

        return ApiResponse::success(['id' => $candidate->id, 'deleted' => true], 'Candidate deleted successfully.');
    }

    public function promote(PromoteRecruitmentCandidateRequest $request, RecruitmentCandidate $candidate): JsonResponse
    {
        return ApiResponse::success($this->candidateArray($this->candidates->promote($request->user(), $candidate, $request->validated())), 'Candidate promoted to cadet successfully.');
    }

    public function sendCommunication(SendRecruitmentCommunicationRequest $request, RecruitmentCandidate $candidate): JsonResponse
    {
        return ApiResponse::success($this->communications->send($request->user(), $candidate, $request->validated()), 'Communication sent successfully.');
    }

    public function communicationHistory(Request $request, RecruitmentCandidate $candidate): JsonResponse
    {
        return ApiResponse::success($this->communications->history($request->user(), $candidate));
    }

    /** @return array<string, mixed> */
    private function candidateArray(RecruitmentCandidate $candidate): array
    {
        $candidate->loadMissing(['province', 'district', 'convertedUser']);

        return [
            'id' => $candidate->id, 'full_name' => $candidate->full_name, 'gender' => $candidate->gender,
            'province_id' => $candidate->province_id, 'district_id' => $candidate->district_id,
            'province' => $candidate->province?->only(['id', 'name_en', 'name_ne']), 'district' => $candidate->district?->only(['id', 'name_en', 'name_ne']),
            'local_level' => $candidate->local_level, 'ward_number' => $candidate->ward_number,
            'contact_number' => $candidate->contact_number, 'email' => $candidate->email, 'skills' => $candidate->skills ?? [],
            'recruitment_status' => $candidate->recruitment_status, 'notes' => $candidate->notes,
            'priority_score' => $candidate->priority_score, 'source' => $candidate->source,
            'outreach_sent_at' => $candidate->outreach_sent_at, 'converted_at' => $candidate->converted_at,
            'converted_user_id' => $candidate->converted_user_id, 'created_at' => $candidate->created_at,
        ];
    }
}
