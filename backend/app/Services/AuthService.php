<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        private readonly AuthFactory $auth,
        private readonly Hasher $hasher,
    ) {}

    /**
     * Attempt to authenticate a user and issue a Sanctum token.
     *
     * @return array{user: User, token: string, expires_at: Carbon|null}
     *
     * @throws ValidationException
     */
    public function attemptLogin(string $login, string $password): array
    {
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $user = User::where($field, $login)
            ->where('status', 'active')
            ->first();

        if ($user === null || ! $this->hasher->check($password, $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($this->hasTooManyLoginAttempts($login)) {
            $this->fireLockoutEvent($login);

            throw ValidationException::withMessages([
                'login' => ['Too many login attempts. Please try again later.'],
            ])->status(429);
        }

        $this->clearLoginAttempts($login);

        $user->forceFill(['last_login_at' => now()])->save();

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
            'expires_at' => $user->currentAccessToken()?->expires_at,
        ];
    }

    /**
     * Revoke the current access token.
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    /**
     * Issue a fresh token and revoke the current one.
     *
     * @return array{user: User, token: string, expires_at: Carbon|null}
     */
    public function refresh(User $user): array
    {
        $user->currentAccessToken()?->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
            'expires_at' => $user->currentAccessToken()?->expires_at,
        ];
    }

    protected function hasTooManyLoginAttempts(string $key): bool
    {
        return RateLimiter::tooManyAttempts($this->throttleKey($key), 5);
    }

    protected function clearLoginAttempts(string $key): void
    {
        RateLimiter::clear($this->throttleKey($key));
    }

    protected function fireLockoutEvent(string $key): void
    {
        event(new Lockout(request()));
    }

    protected function throttleKey(string $login): string
    {
        return Str::lower($login).'|'.request()->ip();
    }
}
