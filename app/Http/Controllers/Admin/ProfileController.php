<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Logs\AuditLogger;
use App\Support\PasswordRules;
use App\Support\SessionInvalidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        return view('profile.index', [
            'user' => $request->user(),
            'profileReturnUrl' => $this->rememberProfileReturnUrl($request),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id]]);
        $emailChanged = $user->email !== $data['email'];
        if ($emailChanged) {
            $request->validate(['current_password' => ['required', 'current_password']]);
        }
        $user->fill($data);
        if ($emailChanged) {
            $user->email_verified_at = null;
        }
        $user->save();
        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }
        AuditLogger::log('Updated', 'Profile', 'Updated own profile', $user->id, $request);

        return back()->with('success', $emailChanged ? 'Profile updated. Please verify the new email address.' : 'Profile updated successfully.');
    }

    public function password(Request $request)
    {
        $data = $request->validate(['current_password' => ['required'], 'password' => PasswordRules::required()]);
        if (! Hash::check($data['current_password'], $request->user()->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }
        $request->user()->update(['password' => $data['password']]);
        SessionInvalidator::forgetUser((int) $request->user()->id, $request->session()->getId());
        AuditLogger::log('Changed Password', 'Profile', 'Changed account password', $request->user()->id, $request);

        return back()->with('success', 'Password changed successfully.');
    }

    private function rememberProfileReturnUrl(Request $request): string
    {
        $fromReferer = $this->safeReturnUrl($request, $request->headers->get('referer'));
        if ($fromReferer) {
            $request->session()->put('profile.return_to', $fromReferer);

            return $fromReferer;
        }

        return $this->safeReturnUrl($request, $request->session()->get('profile.return_to'))
            ?? route('dashboard');
    }

    private function safeReturnUrl(Request $request, mixed $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            $url = $request->root().$url;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || ($parts['host'] ?? null) !== parse_url($request->root(), PHP_URL_HOST)) {
            return null;
        }

        $path = $parts['path'] ?? '/';
        if ($path === '/profile' || str_starts_with($path, '/profile/') || in_array($path, ['/login', '/logout'], true)) {
            return null;
        }

        return $request->root().$path.(isset($parts['query']) ? '?'.$parts['query'] : '').(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
    }
}
