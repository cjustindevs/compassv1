<?php

namespace App\Services;

use App\Models\Referral;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProfessionalAnalytics
{
    public const RECEIVED_DATE = 'COALESCE(professional_notified_at, created_at)';
    public const COMPLETED_DATE = "COALESCE(completed_at, CASE WHEN status = 'completed' THEN closed_date END)";
    public const STATUSES = [Referral::STATUS_PENDING_PROFESSIONAL, Referral::STATUS_ACCEPTED, Referral::STATUS_IN_PROGRESS, Referral::STATUS_COMPLETED, Referral::STATUS_CLOSED];

    public function base(int $professionalId): Builder
    {
        return Referral::professionalAuthorized()->where('professional_id', $professionalId);
    }

    public function filters(Request $request): array
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'activity' => 'nullable|in:received,completed,closed',
        ]);
        $data['activity'] = $data['activity'] ?? 'received';
        return $data;
    }

    public function records(int $professionalId, AnalyticsPeriod $period, array $filters): Builder
    {
        $query = $this->base($professionalId)->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status));
        $date = match ($filters['activity']) {
            'completed' => self::COMPLETED_DATE,
            'closed' => 'closed_date',
            default => self::RECEIVED_DATE,
        };
        if ($filters['activity'] === 'completed') $query->whereIn('status', Referral::COMPLETED_STATUSES);
        if ($filters['activity'] === 'closed') $query->where('status', Referral::STATUS_CLOSED);

        return $period->constrain($query, $date)->orderByDesc(DB::raw($date))->orderByDesc('id');
    }

    public function data(int $professionalId, AnalyticsPeriod $period, ?string $status = null): array
    {
        $base = $this->base($professionalId)->when($status, fn ($q, $value) => $q->where('status', $value));
        $received = $period->constrain(clone $base, self::RECEIVED_DATE);
        $statuses = (clone $received)->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $current = (clone $base)->whereHas('session', fn ($q) => $q->whereNull('archived_at'));
        $reply = $period->constrain(clone $base, 'accepted_at')->whereIn('status', [...Referral::ACTIVE_STATUSES, ...Referral::COMPLETED_STATUSES])->whereNotNull('professional_notified_at')
            ->whereColumn('accepted_at', '>=', 'professional_notified_at');
        $samples = (clone $reply)->count();
        $hours = $samples ? round((float) $reply->selectRaw('AVG('.\App\Support\DatabaseHelper::secondsBetween('accepted_at', 'professional_notified_at').') / 3600 AS hours')->value('hours'), 1) : null;
        $completed = $period->series((clone $base)->whereIn('status', Referral::COMPLETED_STATUSES), self::COMPLETED_DATE);
        $accepted = (clone $received)->whereNotNull('accepted_at')->whereColumn('accepted_at', '>=', DB::raw(self::RECEIVED_DATE))->where('accepted_at', '<=', now('UTC'))->count();
        $total = (clone $received)->count();
        $awaitingReview = (clone $current)->whereIn('status', Referral::ACTIVE_STATUSES)->get()
            ->filter(fn ($referral) => $referral->hasPendingCompletion())->count();

        return $period->dates() + [
            'received' => $period->series(clone $base, self::RECEIVED_DATE), 'completed' => $completed,
            'closed' => $period->constrain((clone $base)->where('status', Referral::STATUS_CLOSED), 'closed_date')->count(),
            'pending_now' => (clone $current)->where('status', Referral::STATUS_PENDING_PROFESSIONAL)->count(),
            'active_now' => (clone $current)->whereIn('status', Referral::ACTIVE_STATUSES)->count(),
            'response_hours' => $hours, 'response_samples' => $samples,
            'awaiting_review_now' => $awaitingReview,
            'outcomes' => $period->constrain((clone $base)->where('status', Referral::STATUS_COMPLETED), self::COMPLETED_DATE)
                ->selectRaw('outcome, COUNT(*) AS total')->groupBy('outcome')->pluck('total', 'outcome')->all(),
            'accepted' => $accepted, 'acceptance_rate' => $total ? round(100 * $accepted / $total, 1) : null,
            'statuses' => ['labels' => array_map(fn ($state) => ucwords(str_replace('_', ' ', $state)), self::STATUSES), 'values' => array_map(fn ($state) => (int) ($statuses[$state] ?? 0), self::STATUSES)],
            'legacy_received' => (clone $received)->whereNull('professional_notified_at')->count(),
            'missing_completion_dates' => (clone $base)->where('status', Referral::STATUS_COMPLETED)->whereNull(DB::raw(self::COMPLETED_DATE))->count(),
        ];
    }
}
