<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required']]);
        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            if (($user->status ?? 'Active') !== 'Active') {
                Auth::logout();
                $this->recordLoginAttempt($request, 'Failed Login', $user->id, $user->email);

                return back()->withErrors(['email' => 'The email or password is incorrect.'])->withInput();
            }
            if (! $user->hasVerifiedEmail()) {
                Auth::logout();
                $this->recordLoginAttempt($request, 'Failed Login', $user->id, $user->email);

                return back()->withErrors(['email' => 'The email or password is incorrect.'])->withInput();
            }
            $request->session()->regenerate();
            $user->update(['last_login_at' => now(), 'last_login_ip' => $request->ip()]);
            $this->recordLoginAttempt($request, 'Login', $user->id, $user->email);
            if ($user->requiresMfa()) {
                return redirect()->intended(route($user->mfa_confirmed_at ? 'mfa.challenge' : 'mfa.setup'));
            }

            return redirect()->intended(route('dashboard'));
        }
        $this->recordLoginAttempt($request, 'Failed Login', null, $request->input('email'));

        return back()->withErrors(['email' => 'The email or password is incorrect.'])->withInput();
    }

    private function recordLoginAttempt(Request $request, string $status, ?int $userId, ?string $email): void
    {
        try {
            LoginLog::create([
                'user_id' => $userId,
                'email' => $email,
                'status' => $status,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        $this->recordLoginAttempt($request, 'Logout', $user?->id, $user?->email);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
