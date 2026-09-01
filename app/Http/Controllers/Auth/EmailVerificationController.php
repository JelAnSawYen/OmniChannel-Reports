<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\MailFailure;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    public function notice()
    {
        if ($requestUser = request()->user()) {
            if ($requestUser->hasVerifiedEmail()) {
                return redirect()->route('dashboard');
            }
        }

        return view('auth.verify-email');
    }

    public function verify(Request $request, string $id, string $hash)
    {
        $user = User::findOrFail($id);

        if (! hash_equals(sha1($user->getEmailForVerification()), (string) $hash)) {
            abort(403);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
            AuditLogger::log('Email Verified', 'Authentication', $user->email.' verified their email', $user->id, $request);
        }

        if ($request->user()) {
            return redirect()->intended(route('dashboard'))->with('success', 'Your email address has been verified.');
        }

        return redirect()->route('login')->with('status', 'Your email address has been verified. You can now log in.');
    }

    public function send(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        try {
            $request->user()->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            return back()->with('error', MailFailure::message($e));
        }

        return back()->with('status', 'A new verification link was sent to '.$request->user()->email.'.');
    }
}
