<?php

namespace App\Http\Controllers;

use App\Models\LoginLog;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLogin(){ return view('auth.login'); }

    public function login(Request $request)
    {
        $credentials=$request->validate(['email'=>['required','email'],'password'=>['required']]);
        if (Auth::attempt($credentials)) {
            $user=Auth::user();
            if (($user->status ?? 'Active') !== 'Active') {
                Auth::logout();
                $this->recordLoginAttempt($request, 'Blocked', $user->id, $user->email);
                return back()->withErrors(['email'=>'The email or password is incorrect.'])->withInput();
            }
            if (! $user->hasVerifiedEmail()) {
                Auth::logout();
                return back()->withErrors(['email'=>'The email or password is incorrect.'])->withInput();
            }
            $request->session()->regenerate();
            $user->update(['last_login_at'=>now(),'last_login_ip'=>$request->ip()]);
            $this->recordLoginAttempt($request, 'Success', $user->id, $user->email);
            AuditLogger::log('Login','Authentication',$user->name.' logged in', $user->id,$request);
            if ($user->requiresMfa()) {
                return redirect()->intended(route($user->mfa_confirmed_at ? 'mfa.challenge' : 'mfa.setup'));
            }
            return redirect()->intended(route('dashboard'));
        }
        $this->recordLoginAttempt($request, 'Failed', null, $request->input('email'));
        return back()->withErrors(['email'=>'The email or password is incorrect.'])->withInput();
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
        AuditLogger::log('Logout','Authentication',$request->user()?->name.' logged out',$request->user()?->id,$request);
        Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken(); return redirect()->route('login');
    }
}
