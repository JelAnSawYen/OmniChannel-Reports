<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\MailSetting;
use App\Services\Logs\AuditLogger;
use App\Support\MailFailure;
use App\Support\PasswordRules;
use App\Support\SessionInvalidator;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function requestForm()
    {
        return view('auth.forgot-password');
    }

    public function sendLink(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        if (! MailSetting::isConfigured()) {
            return back()->with('error', 'Outgoing email is not configured. An Administrator must save the sending mailbox in Maintenance → Email Delivery.')->withInput();
        }

        try {
            Password::sendResetLink($request->only('email'));
        } catch (\Throwable $e) {
            return back()->with('error', MailFailure::message($e))->withInput();
        }

        AuditLogger::log('Password Reset Requested', 'Authentication', 'Password reset requested for '.$request->input('email'), null, $request);

        return back()->with('status', 'If that email exists in the system, a reset link has been sent to it.');
    }

    public function resetForm(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => PasswordRules::required(),
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request) {
                $user->forceFill([
                    'password' => $request->input('password'),
                    'remember_token' => Str::random(60),
                ])->save();

                SessionInvalidator::forgetUser((int) $user->id);
                event(new PasswordReset($user));
                AuditLogger::log('Password Reset', 'Authentication', 'Password was reset for '.$user->email, $user->id, $request);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => 'This password reset link is invalid or has expired.']);
        }

        return redirect()->route('login')->with('status', 'Your password has been reset. You can now log in.');
    }
}
