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
                LoginLog::create(['user_id'=>$user->id,'email'=>$user->email,'status'=>'Blocked','ip_address'=>$request->ip(),'user_agent'=>$request->userAgent()]);
                return back()->withErrors(['email'=>'The email or password is incorrect.'])->withInput();
            }
            if (! $user->hasVerifiedEmail()) {
                Auth::logout();
                return back()->withErrors(['email'=>'The email or password is incorrect.'])->withInput();
            }
            $request->session()->regenerate();
            $user->update(['last_login_at'=>now(),'last_login_ip'=>$request->ip()]);
            LoginLog::create(['user_id'=>$user->id,'email'=>$user->email,'status'=>'Success','ip_address'=>$request->ip(),'user_agent'=>$request->userAgent()]);
            AuditLogger::log('Login','Authentication',$user->name.' logged in', $user->id,$request);
            if ($user->requiresMfa()) {
                return redirect()->intended(route($user->mfa_confirmed_at ? 'mfa.challenge' : 'mfa.setup'));
            }
            return redirect()->intended(route('dashboard'));
        }
        try {
            LoginLog::create(['email'=>$request->input('email'),'status'=>'Failed','ip_address'=>$request->ip(),'user_agent'=>$request->userAgent()]);
        } catch (\Throwable) {
            // Login failure recording must not hide the authentication error.
        }
        return back()->withErrors(['email'=>'The email or password is incorrect.'])->withInput();
    }

    public function logout(Request $request)
    {
        AuditLogger::log('Logout','Authentication',$request->user()?->name.' logged out',$request->user()?->id,$request);
        Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken(); return redirect()->route('login');
    }
}
