<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class AdminAuthController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::check()) {
            return Auth::user()->can('admin-access')
                ? redirect()->route('admin.home')
                : abort(Response::HTTP_FORBIDDEN);
        }

        return view('admin.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        if (! Auth::attempt($credentials)) {
            return back()
                ->withErrors(['email' => 'Email atau password tidak valid.'])
                ->onlyInput('email');
        }

        if ($request->user()->is_active === false) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withErrors(['email' => 'Email atau password tidak valid.'])
                ->onlyInput('email');
        }

        if (! $request->user()->can('admin-access')) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withErrors(['email' => 'Akun ini tidak memiliki akses pengelola.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        $intendedPath = $this->safeAdminIntendedPath(
            $request->session()->pull('url.intended'),
            $request
        );

        return $intendedPath !== null
            ? redirect()->to($intendedPath)
            : redirect()->route('admin.home');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    private function safeAdminIntendedPath(mixed $intendedUrl, Request $request): ?string
    {
        if (! is_string($intendedUrl) || $intendedUrl === '' || strlen($intendedUrl) > 2048) {
            return null;
        }

        $parts = parse_url($intendedUrl);
        if ($parts === false || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        if (isset($parts['host'])) {
            if (
                ! isset($parts['scheme'])
                || strtolower($parts['host']) !== strtolower($request->getHost())
                || strtolower($parts['scheme']) !== $request->getScheme()
                || (isset($parts['port']) && $parts['port'] !== $request->getPort())
            ) {
                return null;
            }
        } elseif (isset($parts['scheme'])) {
            return null;
        }

        $path = $parts['path'] ?? '';
        $adminPath = rtrim($request->getBaseUrl(), '/').'/admin';
        if ($path !== $adminPath && ! str_starts_with($path, $adminPath.'/')) {
            return null;
        }

        return $path.(isset($parts['query']) ? '?'.$parts['query'] : '');
    }
}
