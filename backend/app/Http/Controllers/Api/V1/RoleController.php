<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreRoleRequest;
use App\Http\Requests\Api\V1\UpdateRoleRequest;
use App\Models\Role;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RoleController extends Controller
{
    /**
     * List all roles with their permissions.
     */
    public function index(Request $request): JsonResponse
    {
        $roles = Role::withCount('users')
            ->with('permissions')
            ->when($request->filled('search'), fn ($query) => $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->string('search').'%')
                    ->orWhere('slug', 'like', '%'.$request->string('search').'%');
            }))
            ->orderBy('name')
            ->paginate(10);

        return ApiResponse::success([
            'items' => collect($roles->items())->map(fn (Role $role) => $this->roleArray($role)),
            'meta' => [
                'current_page' => $roles->currentPage(),
                'per_page' => $roles->perPage(),
                'total' => $roles->total(),
                'last_page' => $roles->lastPage(),
            ],
        ]);
    }

    /**
     * Show a single role with its permissions.
     */
    public function show(Role $role): JsonResponse
    {
        $role->loadMissing('permissions', 'users');

        return ApiResponse::success($this->roleArray($role));
    }

    /**
     * Create a new role and assign its permissions.
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        $data = $request->validated();

        $role = Role::create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? Str::slug($data['name']),
            'description' => $data['description'] ?? null,
        ]);

        $role->permissions()->sync($data['permission_ids'] ?? []);

        $role->loadMissing('permissions');

        return ApiResponse::success($this->roleArray($role), 'Role created successfully.', 201);
    }

    /**
     * Update a role's details and permissions.
     */
    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $data = $request->validated();

        $role->update(array_filter([
            'name' => $data['name'] ?? $role->name,
            'slug' => $data['slug'] ?? $role->slug,
            'description' => array_key_exists('description', $data) ? $data['description'] : $role->description,
        ]));

        if (array_key_exists('permission_ids', $data)) {
            $role->permissions()->sync($data['permission_ids']);
        }

        $role->loadMissing('permissions');

        return ApiResponse::success($this->roleArray($role), 'Role updated successfully.');
    }

    /**
     * Delete a role.
     */
    public function destroy(Role $role): JsonResponse
    {
        if ($role->users()->exists()) {
            throw ValidationException::withMessages([
                'role' => ['Cannot delete a role that still has users assigned.'],
            ]);
        }

        $role->permissions()->detach();
        $role->delete();

        return ApiResponse::success(null, 'Role deleted successfully.');
    }

    /**
     * Build a consistent role payload.
     *
     * @return array<string, mixed>
     */
    protected function roleArray(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'slug' => $role->slug,
            'description' => $role->description,
            'users_count' => $role->users_count ?? $role->users()->count(),
            'permissions' => $role->permissions->map(fn ($permission) => [
                'id' => $permission->id,
                'name' => $permission->name,
                'slug' => $permission->slug,
                'group' => $permission->group,
            ])->values(),
            'created_at' => $role->created_at,
            'updated_at' => $role->updated_at,
        ];
    }
}
