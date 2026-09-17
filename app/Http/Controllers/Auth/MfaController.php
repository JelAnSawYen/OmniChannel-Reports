<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\TotpService;
use App\Services\Logs\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class MfaController extends Controller
{
    public function setup(Request $request)
    {
        $user = $request->user();
        if (! $user->requiresMfa()) {
            return redirect()->route('dashboard');
        }
        if ($user->mfa_confirmed_at) {
            return redirect()->route('mfa.challenge');
        }

        if (! $request->session()->has('mfa_setup_secret')) {
            $request->session()->put('mfa_setup_secret', TotpService::generateSecret());
        }

        $secret = $request->session()->get('mfa_setup_secret');

        return view('auth.mfa-setup', [
            'secret' => $secret,
            'otpauth' => TotpService::otpauthUri($user->email, $secret),
        ]);
    }

    public function confirmSetup(Request $request)
    {
        $request->validate(['code' => ['required', 'string']]);
        $secret = $request->session()->get('mfa_setup_secret');
        if (! $secret || ! TotpService::verify($secret, $request->input('code'))) {
            return back()->withErrors(['code' => 'The authentication code is invalid.']);
        }

        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4)));
        }

        $user = $request->user();
        $user->mfa_secret = $secret;
        $user->mfa_confirmed_at = now();
        $user->mfa_recovery_codes = array_map(fn ($code) => Hash::make($code), $codes);
        $user->save();

        $request->session()->forget('mfa_setup_secret');
        $request->session()->put('mfa_passed', $user->id);
        $request->session()->put('mfa_recovery_plain', $codes);

        AuditLogger::log('MFA Enabled', 'Authentication', $user->email.' enabled two-factor authentication', $user->id, $request);

        return redirect()->route('mfa.recovery');
    }

    public function recovery(Request $request)
    {
        $codes = $request->session()->get('mfa_recovery_plain');
        if (! $codes) {
            return redirect()->route('dashboard');
        }

        return view('auth.mfa-recovery', ['codes' => $codes]);
    }

    public function acknowledgeRecovery(Request $request)
    {
        $request->session()->forget('mfa_recovery_plain');

        return redirect()->intended(route('dashboard'));
    }

    public function challenge()
    {
        $user = request()->user();
        if (! $user?->mfa_confirmed_at) {
            return redirect()->route('mfa.setup');
        }
        if (session('mfa_passed') === $user->id) {
            return redirect()->route('dashboard');
        }

        return view('auth.mfa-challenge');
    }

    public function verify(Request $request)
    {
        $request->validate(['code' => ['required', 'string']]);
        $user = $request->user();
        $code = trim((string) $request->input('code'));

        $valid = $user->mfa_secret && TotpService::verify($user->mfa_secret, $code);
        if (! $valid) {
            $valid = $this->consumeRecoveryCode($user, $code);
        }

        if (! $valid) {
            AuditLogger::log('MFA Failed', 'Authentication', 'Failed MFA challenge for '.$user->email, $user->id, $request);

            return back()->withErrors(['code' => 'The authentication code is invalid.']);
        }

        $request->session()->put('mfa_passed', $user->id);

        return redirect()->intended(route('dashboard'));
    }

    private function consumeRecoveryCode($user, string $code): bool
    {
        $stored = $user->mfa_recovery_codes ?? [];
        foreach ($stored as $index => $hash) {
            if (Hash::check($code, $hash)) {
                unset($stored[$index]);
                $user->mfa_recovery_codes = array_values($stored);
                $user->save();

                return true;
            }
        }

        return false;
    }
}
