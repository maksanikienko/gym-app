<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuthRules;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const MAX_LOGIN_ATTEMPTS = 5;   // per email + IP
    private const LOCKOUT_SECONDS = 60;

    public function register(Request $request)
    {
        $this->normalize($request);
        $data = $request->validate([
            'name' => AuthRules::name(),
            'email' => [...AuthRules::email(), Rule::unique('users', 'email')],
            'password' => AuthRules::newPassword(),
            'locale' => ['nullable', Rule::in(['ru', 'en'])],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'locale' => $data['locale'] ?? app()->getLocale(),
        ]);

        Auth::guard('web')->login($user, true);
        $request->session()->regenerate();

        return response()->json($this->userPayload($user), 201);
    }

    public function login(Request $request)
    {
        $this->normalize($request);
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:'.AuthRules::EMAIL_MAX],
            'password' => ['required', 'string', 'max:'.AuthRules::PASSWORD_MAX],
        ]);

        $key = 'login:'.Str::transliterate($data['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, self::MAX_LOGIN_ATTEMPTS)) {
            event(new Lockout($request));
            throw ValidationException::withMessages([
                'email' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key)]),
            ])->status(429);
        }

        if (! Auth::guard('web')->attempt(['email' => $data['email'], 'password' => $data['password']], true)) {
            RateLimiter::hit($key, self::LOCKOUT_SECONDS);
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        return response()->json($this->userPayload($request->user()));
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function me(Request $request)
    {
        return response()->json($this->userPayload($request->user()));
    }

    public function update(Request $request)
    {
        if ($request->has('name')) {
            $request->merge(['name' => trim((string) $request->input('name'))]);
        }
        $data = $request->validate([
            'name' => ['sometimes', ...AuthRules::name()],
            'locale' => ['sometimes', Rule::in(['ru', 'en'])],
        ]);
        $request->user()->update($data);

        return response()->json($this->userPayload($request->user()));
    }

    /** Change password and sign out every other device. */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => [...AuthRules::newPassword(), 'different:current_password'],
        ]);

        $user = $request->user();
        $user->forceFill([
            'password' => $request->input('password'),
            'remember_token' => Str::random(60), // invalidates "remember me" cookies elsewhere
        ])->save();

        DB::table('sessions')->where('user_id', $user->id)
            ->where('id', '!=', $request->session()->getId())->delete();
        $request->session()->regenerate();
        Auth::guard('web')->login($user, true); // fresh remember cookie for this device

        return response()->noContent();
    }

    public function destroy(Request $request)
    {
        $request->validate(['password' => ['required', 'string', 'current_password']]);
        $user = $request->user();
        Auth::guard('web')->logout();
        $user->delete(); // cascades to all of the user's data
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    private function normalize(Request $request): void
    {
        $request->merge(array_filter([
            'email' => is_string($request->input('email')) ? mb_strtolower(trim($request->input('email'))) : null,
            'name' => is_string($request->input('name')) ? trim(preg_replace('/\s+/u', ' ', $request->input('name'))) : null,
        ], fn ($v) => $v !== null));
    }

    private function userPayload(User $user): array
    {
        return $user->only(['id', 'name', 'email', 'locale']);
    }
}
