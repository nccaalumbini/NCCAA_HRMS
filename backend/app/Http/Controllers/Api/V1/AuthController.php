<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    /**
     * Authenticate the user and return a token.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        try {
            $result = $this->auth->attemptLogin($credentials['login'], $credentials['password']);

            return ApiResponse::success($result, 'Logged in successfully.');
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Login failed.', $e->errors());
        }
    }

    /**
     * Revoke the current token.
     */
    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout($request->user());

        return ApiResponse::success(null, 'Logged out successfully.');
    }

    /**
     * Return the current authenticated user with roles and permissions.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $permissions = $user->roles()
            ->with('permissions')
            ->get()
            ->pluck('permissions')
            ->flatten()
            ->pluck('slug')
            ->unique()
            ->values();

        return ApiResponse::success([
            'id' => $user->id,
            'uuid' => $user->uuid,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'roles' => $user->roles->pluck('slug'),
            'permissions' => $permissions,
            'province_id' => $user->province_id,
            'district_id' => $user->district_id,
        ]);
    }

    /**
     * Issue a fresh token for the current user.
     */
    public function refresh(Request $request): JsonResponse
    {
        $result = $this->auth->refresh($request->user());

        return ApiResponse::success($result, 'Token refreshed successfully.');
    }

    /**
     * Send a password reset link.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT) {
            return ApiResponse::success(null, 'Password reset link sent.');
        }

        return ApiResponse::error('Unable to send reset link.', 400);
    }

    /**
     * Reset the user password with the token received by email.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', 'min:12'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill(['password' => $password])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return ApiResponse::success(null, 'Password reset successfully.');
        }

        return ApiResponse::error('Unable to reset password.', 400);
    }
}
