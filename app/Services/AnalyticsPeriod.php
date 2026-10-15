<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Philippine calendar dates, with half-open UTC boundaries for stored events. */
class AnalyticsPeriod
{
    public const TIMEZONE = 'Asia/Manila';

    public function __construct(public CarbonImmutable $start, public CarbonImmutable $end) {}

    public static function fromRequest(Request $request): self
    {
        $today = CarbonImmutable::now(self::TIMEZONE)->toDateString();
        $data = $request->validate([
            'from' => 'nullable|required_with:to|date_format:Y-m-d|after_or_equal:1900-01-01',
            'to' => 'nullable|required_with:from|date_format:Y-m-d|after_or_equal:from|before_or_equal:'.$today,
            'period' => 'nullable|in:today,weekly,monthly,quarterly,yearly,semester,all,custom',
        ]);
        if (empty($data['from']) && ($data['period'] ?? '') === 'semester') {
            $a = config('analytics.semester_start');
            $b = config('analytics.semester_end');
            if (! $a || ! $b || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $a) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $b) || $b < $a || $a > $today) {
                throw \Illuminate\Validation\ValidationException::withMessages(['period' => 'Semester dates are not configured. Use Custom dates.']);
            }
            $data['from'] = $a;
            $data['to'] = min($b, $today);
        }
        if (empty($data['from']) && ($data['period'] ?? '') === 'all') {
            $earliest = collect([
                \App\Models\User::min('created_at'), \App\Models\Session::min('created_date'),
                \App\Models\Session::min('submitted_at'), \App\Models\Session::min('start_time'),
                \App\Models\Session::min('end_time'), \App\Models\QueueRequest::min('request_date'),
                \App\Models\HelperCompetencyHistory::min('evaluation_date'), \App\Models\Referral::min('created_at'),
            ])->filter()->map(fn ($value) => CarbonImmutable::parse($value, 'UTC')->timezone(self::TIMEZONE)->toDateString())->min();
            $data['from'] = min($earliest ?? $today, $today);
        }
        $days = ['today' => 1, 'weekly' => 7, 'monthly' => 30, 'quarterly' => 90, 'yearly' => 365, 'custom' => 30, 'semester' => 30, 'all' => 30][$data['period'] ?? 'monthly'];
        $end = CarbonImmutable::parse($data['to'] ?? $today, self::TIMEZONE)->startOfDay()->addDay();
        $start = isset($data['from']) ? CarbonImmutable::parse($data['from'], self::TIMEZONE)->startOfDay() : $end->subDays($days);

        return new self($start, $end);
    }

    public function dates(): array
    {
        return ['from' => $this->start->toDateString(), 'to' => $this->end->subDay()->toDateString()];
    }

    public function constrain(Builder $query, string $expression): Builder
    {
        return $query->where(DB::raw($expression), '>=', $this->start->utc())
            ->where(DB::raw($expression), '<', $this->end->utc())
            ->where(DB::raw($expression), '<=', CarbonImmutable::now('UTC'));
    }

    /** Aggregate in SQL; only the small bucket result is materialized in PHP. */
    public function series(Builder $query, string $expression): array
    {
        $daily = $this->start->diffInDays($this->end) <= 62;
        $yearly = $this->start->diffInYears($this->end) > 10;
        // Asia/Manila is UTC+08:00 without daylight-saving transitions.
        $bucket = match (DB::connection()->getDriverName()) {
            'pgsql' => "TO_CHAR(($expression) + INTERVAL '8 hours', '".($daily ? 'YYYY-MM-DD' : ($yearly ? 'YYYY' : 'YYYY-MM'))."')",
            'mysql' => "DATE_FORMAT(DATE_ADD(($expression), INTERVAL 8 HOUR), '".($daily ? '%Y-%m-%d' : ($yearly ? '%Y' : '%Y-%m'))."')",
            default => "strftime('".($daily ? '%Y-%m-%d' : ($yearly ? '%Y' : '%Y-%m'))."', ($expression), '+8 hours')",
        };
        $counts = $this->constrain(clone $query, $expression)->reorder()
            ->selectRaw("$bucket AS bucket, COUNT(*) AS total")->groupByRaw($bucket)->pluck('total', 'bucket');
        $labels = $values = [];
        for ($date = $daily ? $this->start : ($yearly ? $this->start->startOfYear() : $this->start->startOfMonth()); $date->lt($this->end); $date = $daily ? $date->addDay() : ($yearly ? $date->addYear() : $date->addMonth())) {
            $labels[] = $date->format($daily ? 'M j, Y' : ($yearly ? 'Y' : 'M Y'));
            $values[] = (int) ($counts[$date->format($daily ? 'Y-m-d' : ($yearly ? 'Y' : 'Y-m'))] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values, 'total' => array_sum($values), 'interval' => $daily ? 'Daily' : ($yearly ? 'Yearly' : 'Monthly')];
    }
}
