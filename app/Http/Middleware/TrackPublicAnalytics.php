<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

class TrackPublicAnalytics
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldTrack($request, $response)) {
            return $response;
        }

        if (! Schema::hasTable('analytics_visitors') || ! Schema::hasTable('public_page_views')) {
            Log::error('Public analytics is enabled but its database tables are unavailable.');

            return $response;
        }

        $cookieName = (string) config('analytics.cookie_name');
        $visitorToken = $request->cookie($cookieName);
        $newVisitorToken = ! is_string($visitorToken)
            || preg_match('/^[a-f0-9]{64}$/', $visitorToken) !== 1;

        if ($newVisitorToken) {
            $visitorToken = bin2hex(random_bytes(32));
        }

        $visitorHash = hash_hmac('sha256', $visitorToken, (string) config('app.key'));
        $sessionHash = hash_hmac(
            'sha256',
            (string) $request->session()->getId(),
            (string) config('app.key')
        );
        $userAgent = (string) $request->userAgent();
        $now = now();
        $retentionDays = max(1, (int) config('analytics.retention_days', 90));

        DB::transaction(function () use (
            $request,
            $visitorHash,
            $sessionHash,
            $userAgent,
            $now,
            $retentionDays
        ): void {
            DB::table('analytics_visitors')->insertOrIgnore([
                'visitor_hash' => $visitorHash,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
                'expires_at' => $now->copy()->addDays($retentionDays),
            ]);

            DB::table('analytics_visitors')
                ->where('visitor_hash', $visitorHash)
                ->lockForUpdate()
                ->first();

            DB::table('analytics_visitors')
                ->where('visitor_hash', $visitorHash)
                ->update([
                    'last_seen_at' => $now,
                    'expires_at' => $now->copy()->addDays($retentionDays),
                ]);

            $path = '/'.ltrim($request->path(), '/');
            $path = substr($path, 0, 255);
            $dedupeSince = $now->copy()->subSeconds(
                max(0, (int) config('analytics.dedupe_seconds', 5))
            );
            $alreadyRecorded = DB::table('public_page_views')
                ->where('session_hash', $sessionHash)
                ->where('path', $path)
                ->where('viewed_at', '>=', $dedupeSince)
                ->exists();

            if ($alreadyRecorded) {
                return;
            }

            [$device, $browser, $operatingSystem] = $this->classifyUserAgent($userAgent);

            DB::table('public_page_views')->insert([
                'visitor_hash' => $visitorHash,
                'session_hash' => $sessionHash,
                'path' => $path,
                'device_type' => $device,
                'browser' => $browser,
                'operating_system' => $operatingSystem,
                'country_code' => null,
                'province' => null,
                'city' => null,
                'viewed_at' => $now,
            ]);
        });

        if ($newVisitorToken) {
            $response->headers->setCookie(Cookie::create(
                (string) config('analytics.cookie_name'),
                $visitorToken,
                now()->addDays(max(1, (int) config('analytics.cookie_days', 365))),
                '/',
                null,
                $request->isSecure(),
                true,
                false,
                Cookie::SAMESITE_LAX
            ));
        }

        return $response;
    }

    private function shouldTrack(Request $request, Response $response): bool
    {
        if (! config('analytics.enabled')
            || ! $request->isMethod('GET')
            || $response->getStatusCode() < 200
            || $response->getStatusCode() >= 300
            || $request->expectsJson()
            || $request->is('admin', 'admin/*', 'api', 'api/*', 'up')) {
            return false;
        }

        $routeName = (string) optional($request->route())->getName();
        if (str_starts_with($routeName, 'admin.')) {
            return false;
        }

        $contentType = (string) $response->headers->get('Content-Type');
        if (! str_starts_with(strtolower($contentType), 'text/html')) {
            return false;
        }

        return preg_match('/\.(?:css|js|mjs|map|png|jpe?g|gif|webp|svg|ico|woff2?|ttf|eot|pdf|xml|json)$/i', $request->path()) !== 1;
    }

    private function classifyUserAgent(string $userAgent): array
    {
        $device = match (true) {
            preg_match('/ipad|tablet|kindle|silk/i', $userAgent) === 1 => 'tablet',
            preg_match('/mobile|iphone|ipod|android/i', $userAgent) === 1 => 'mobile',
            default => 'desktop',
        };

        $browser = match (true) {
            preg_match('/edg(?:e|ios|a)?\//i', $userAgent) === 1 => 'Edge',
            preg_match('/opr\//i', $userAgent) === 1 => 'Opera',
            preg_match('/samsungbrowser\//i', $userAgent) === 1 => 'Samsung Internet',
            preg_match('/firefox\//i', $userAgent) === 1 => 'Firefox',
            preg_match('/chrome\//i', $userAgent) === 1 => 'Chrome',
            preg_match('/safari\//i', $userAgent) === 1 => 'Safari',
            default => 'Other',
        };

        $operatingSystem = match (true) {
            preg_match('/windows/i', $userAgent) === 1 => 'Windows',
            preg_match('/android/i', $userAgent) === 1 => 'Android',
            preg_match('/iphone|ipad|ipod/i', $userAgent) === 1 => 'iOS',
            preg_match('/macintosh|mac os/i', $userAgent) === 1 => 'macOS',
            preg_match('/linux/i', $userAgent) === 1 => 'Linux',
            default => 'Other',
        };

        return [$device, $browser, $operatingSystem];
    }
}
