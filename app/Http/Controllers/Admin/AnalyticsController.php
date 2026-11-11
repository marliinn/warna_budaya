<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AnalyticsController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        [$start, $end, $period] = $this->filters($request);
        $views = $this->viewsBetween($start, $end);
        $newVisitorHashes = DB::table('analytics_visitors')
            ->whereBetween('first_seen_at', [$start, $end])
            ->select('visitor_hash');
        $newVisitors = (clone $newVisitorHashes)->count();
        $uniqueVisitors = (clone $views)->distinct()->count('visitor_hash');
        $returningVisitors = (clone $views)
            ->whereNotIn('visitor_hash', $newVisitorHashes)
            ->distinct()
            ->count('visitor_hash');
        $today = now()->startOfDay();
        $todayVisitors = DB::table('public_page_views')
            ->whereBetween('viewed_at', [$today, $today->copy()->endOfDay()])
            ->distinct()
            ->count('visitor_hash');

        return $this->respond([
            'unique_visitors' => $uniqueVisitors,
            'sessions' => (clone $views)->distinct()->count('session_hash'),
            'page_views' => (clone $views)->count(),
            'today_visitors' => $todayVisitors,
            'new_visitors' => $newVisitors,
            'returning_visitors' => $returningVisitors,
        ], $start, $end, $period);
    }

    public function traffic(Request $request): JsonResponse
    {
        [$start, $end, $period] = $this->filters($request);
        $driver = DB::connection()->getDriverName();
        $bucket = match ([$period, $driver]) {
            ['daily', 'sqlite'] => "strftime('%Y-%m-%d', viewed_at)",
            ['weekly', 'sqlite'] => "date(viewed_at, '-' || ((cast(strftime('%w', viewed_at) as integer) + 6) % 7) || ' days')",
            ['monthly', 'sqlite'] => "strftime('%Y-%m-01', viewed_at)",
            ['daily', 'mysql'] => 'DATE(viewed_at)',
            ['weekly', 'mysql'] => 'DATE_SUB(DATE(viewed_at), INTERVAL WEEKDAY(viewed_at) DAY)',
            ['monthly', 'mysql'] => "DATE_FORMAT(viewed_at, '%Y-%m-01')",
            default => 'DATE(viewed_at)',
        };

        $rows = $this->viewsBetween($start, $end)
            ->selectRaw("$bucket AS period_start")
            ->selectRaw('COUNT(*) AS page_views')
            ->selectRaw('COUNT(DISTINCT visitor_hash) AS unique_visitors')
            ->selectRaw('COUNT(DISTINCT session_hash) AS sessions')
            ->groupBy('period_start')
            ->orderBy('period_start')
            ->get();

        return $this->respond($rows, $start, $end, $period);
    }

    public function devices(Request $request): JsonResponse
    {
        [$start, $end, $period] = $this->filters($request);

        $data = [
            'devices' => $this->groupedCount($start, $end, 'device_type'),
            'browsers' => $this->groupedCount($start, $end, 'browser'),
            'operating_systems' => $this->groupedCount($start, $end, 'operating_system'),
        ];

        return $this->respond($data, $start, $end, $period);
    }

    public function pages(Request $request): JsonResponse
    {
        $request->mergeIfMissing(['limit' => 10]);
        $request->validate(['limit' => ['integer', 'min:1', 'max:50']]);
        [$start, $end, $period] = $this->filters($request);

        $data = $this->viewsBetween($start, $end)
            ->select('path')
            ->selectRaw('COUNT(*) AS page_views')
            ->selectRaw('COUNT(DISTINCT visitor_hash) AS unique_visitors')
            ->groupBy('path')
            ->orderByDesc('page_views')
            ->limit((int) $request->input('limit'))
            ->get();

        return $this->respond($data, $start, $end, $period);
    }

    public function geography(Request $request): JsonResponse
    {
        [$start, $end, $period] = $this->filters($request);

        $data = [
            'available' => DB::table('public_page_views')
                ->whereBetween('viewed_at', [$start, $end])
                ->where(function (Builder $query): void {
                    $query->whereNotNull('country_code')
                        ->orWhereNotNull('province')
                        ->orWhereNotNull('city');
                })
                ->exists(),
            'countries' => $this->groupedCount($start, $end, 'country_code'),
            'provinces' => $this->groupedCount($start, $end, 'province'),
            'cities' => $this->groupedCount($start, $end, 'city'),
            'location_accuracy' => 'approximate',
        ];

        return $this->respond($data, $start, $end, $period);
    }

    private function filters(Request $request): array
    {
        if (! Schema::hasTable('analytics_visitors') || ! Schema::hasTable('public_page_views')) {
            abort(JsonResponse::HTTP_SERVICE_UNAVAILABLE, 'Migration analitik belum diterapkan.');
        }

        $filters = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'period' => ['nullable', 'in:daily,weekly,monthly'],
        ]);
        $end = isset($filters['date_to'])
            ? CarbonImmutable::createFromFormat('Y-m-d', $filters['date_to'])->endOfDay()
            : now()->toImmutable()->endOfDay();
        $start = isset($filters['date_from'])
            ? CarbonImmutable::createFromFormat('Y-m-d', $filters['date_from'])->startOfDay()
            : $end->startOfDay()->subDays(max(0, (int) config('analytics.max_range_days', 90) - 1));

        if ($start->greaterThan($end)) {
            throw ValidationException::withMessages([
                'date_from' => ['Tanggal mulai harus sama dengan atau sebelum tanggal akhir.'],
            ]);
        }

        if ($start->diffInDays($end) + 1 > max(1, (int) config('analytics.max_range_days', 90))) {
            throw ValidationException::withMessages([
                'date_from' => ['Rentang tanggal melampaui batas maksimum analitik.'],
            ]);
        }

        return [$start, $end, $filters['period'] ?? 'daily'];
    }

    private function viewsBetween(CarbonImmutable $start, CarbonImmutable $end): Builder
    {
        return DB::table('public_page_views')->whereBetween('viewed_at', [$start, $end]);
    }

    private function groupedCount(
        CarbonImmutable $start,
        CarbonImmutable $end,
        string $column
    ): array {
        return $this->viewsBetween($start, $end)
            ->whereNotNull($column)
            ->select($column)
            ->selectRaw('COUNT(*) AS page_views')
            ->selectRaw('COUNT(DISTINCT visitor_hash) AS unique_visitors')
            ->groupBy($column)
            ->orderByDesc('page_views')
            ->get()
            ->all();
    }

    private function respond(mixed $data, CarbonImmutable $start, CarbonImmutable $end, string $period): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => [
                'date_from' => $start->toDateString(),
                'date_to' => $end->toDateString(),
                'period' => $period,
                'enabled' => (bool) config('analytics.enabled'),
                'retention_days' => (int) config('analytics.retention_days', 90),
            ],
        ]);
    }
}
