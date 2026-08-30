<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AssignRolesRequest;
use App\Http\Requests\Api\V1\ResetUserPasswordRequest;
use App\Http\Requests\Api\V1\StoreUserRequest;
use App\Http\Requests\Api\V1\UpdateUserRequest;
use App\Models\User;
use App\Services\UserService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    /**
     * List users within the caller's scope.
     */
    public function index(Request $request): JsonResponse
    {
        $users = $this->users->paginate($request->user(), $request->only([
            'search', 'status', 'role', 'province_id', 'district_id',
        ]));

        return ApiResponse::success([
            'items' => collect($users->items())->map(fn (User $user) => $this->userArray($user)),
            'meta' => [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }

    /**
     * Create a new user.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->users->create($request->user(), $request->validated());

        return ApiResponse::success($this->userArray($user), 'User created successfully.', 201);
    }

    /**
     * Show a single user.
     */
    public function show(Request $request, User $user): JsonResponse
    {
        try {
            $this->users->show($request->user(), $user);

            return ApiResponse::success($this->userArray($user));
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Unable to view user.', $e->errors());
        }
    }

    /**
     * Update a user's basic details.
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $user = $this->users->update($request->user(), $user, $request->validated());

        return ApiResponse::success($this->userArray($user), 'User updated successfully.');
    }

    /**
     * Disable a user's account.
     */
    public function disable(Request $request, User $user): JsonResponse
    {
        $user = $this->users->disable($request->user(), $user);

        return ApiResponse::success($this->userArray($user), 'User disabled successfully.');
    }

    /**
     * Re-enable a user's account.
     */
    public function activate(Request $request, User $user): JsonResponse
    {
        $user = $this->users->activate($request->user(), $user);

        return ApiResponse::success($this->userArray($user), 'User activated successfully.');
    }

    /**
     * Reset a user's password.
     */
    public function resetPassword(ResetUserPasswordRequest $request, User $user): JsonResponse
    {
        $this->users->resetPassword($request->user(), $user, $request->validated('password'));

        return ApiResponse::success(null, 'Password reset successfully.');
    }

    /**
     * Assign roles to a user.
     */
    public function assignRoles(AssignRolesRequest $request, User $user): JsonResponse
    {
        $user = $this->users->assignRoles($request->user(), $user, $request->validated('role_ids'));

        return ApiResponse::success($this->userArray($user), 'Roles assigned successfully.');
    }

    /**
     * Assign a geographic scope to a user.
     */
    public function assignGeography(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'province_id' => ['nullable', 'integer', 'exists:provinces,id'],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'role_ids' => ['sometimes', 'array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ]);

        $user = $this->users->assignGeography(
            $request->user(),
            $user,
            $data['province_id'] ?? null,
            $data['district_id'] ?? null,
            $data['role_ids'] ?? [],
        );

        return ApiResponse::success($this->userArray($user), 'Geography assigned successfully.');
    }

    /**
     * Build a consistent user payload.
     *
     * @return array<string, mixed>
     */
    protected function userArray(User $user): array
    {
        $user->loadMissing(['roles', 'province', 'district']);

        return [
            'id' => $user->id,
            'uuid' => $user->uuid,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => $user->status,
            'roles' => $user->roles->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
            ])->values(),
            'permissions' => $user->roles
                ->flatMap(fn ($role) => $role->permissions)
                ->pluck('slug')
                ->unique()
                ->values(),
            'province_id' => $user->province_id,
            'district_id' => $user->district_id,
            'last_login_at' => $user->last_login_at,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }
}
