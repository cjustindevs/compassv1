<?php
namespace App\Services;

use App\Models\{Referral, ReferralAppointment, Session, User};

/** Presentation-only summaries. Never changes eligibility, consent, or workflow state. */
class DashboardIndicators
{
    public function card(string $label, ?float $value, string $detail, string $unit = '', ?int $samples = null, ?string $link = null, ?string $id = null): array
    {
        return ['label'=>$label, 'raw'=>$value, 'unit'=>$unit, 'value'=>SessionPerformance::number($value, $unit),
            'detail'=>$detail, 'samples'=>$samples, 'link'=>$link, 'id'=>$id];
    }

    /** A card whose value is a word (a status), not a number. */
    public function textCard(string $label, string $value, string $detail, ?string $link = null): array
    {
        return ['label'=>$label, 'raw'=>null, 'unit'=>'', 'value'=>$value, 'detail'=>$detail, 'samples'=>null, 'link'=>$link, 'id'=>null];
    }

    /** QA's four Admin cards: Total users, Active accounts, Inactive accounts, System status. */
    public function admin(AnalyticsPeriod $period, array $technical): array
    {
        return [
            $this->card('Total users', User::count(), 'Registered accounts, all roles', link: route('admin.users')) + ['icon'=>'users'],
            $this->card('Active accounts', User::where('is_active', true)->count(), 'Enabled accounts; not online users', link: route('admin.users', ['status'=>'active'])) + ['icon'=>'user-check'],
            $this->card('Inactive accounts', User::where('is_active', false)->count(), 'Disabled accounts', link: route('admin.users', ['status'=>'inactive'])) + ['icon'=>'user-minus'],
            $this->textCard('System status', $technical['health'], 'From the monitoring integration; not an uptime measurement') + ['icon'=>'activity'],
        ];
    }

    /**
     * QA's three Seeker cards (Active request, Queue position, Completed sessions),
     * then the things the Seeker may need to act on, marked 'task' so the view
     * shows them as links only when there is something to do.
     */
    public function seeker(User $user, AnalyticsPeriod $period): array
    {
        abort_unless($user->is_active && $user->role === 'seeker' && $user->helpSeeker, 403);
        $sessions = Session::where('seeker_id', $user->helpSeeker->id)->where('is_test_record', false)->whereNull('duplicate_of_id');
        $referrals = Referral::whereIn('session_id', (clone $sessions)->select('id'));
        $upcoming = ReferralAppointment::whereIn('referral_id', (clone $referrals)->where('help_seeker_consent', true)->whereNotIn('status', ['declined','closed','completed','cancelled'])->select('id'))
            ->where('status', 'scheduled')->where('seeker_response', 'confirmed')->where('starts_at', '>=', now())->count();
        $completed = (clone $sessions)->whereIn('session_status', ['completed','evaluated'])->whereNotNull('start_time')->whereColumn('end_time', '>=', 'start_time');
        $current = (clone $sessions)->where('session_status', Session::STATUS_ACTIVE)->latest('start_time')->first()
            ?? Session::pendingForSeeker($user->helpSeeker->id)->where('is_test_record', false)->whereNull('duplicate_of_id')->with('queue')->first();
        $position = $current?->queue?->request_status === 'waiting' ? app(QueueManagementService::class)->position($current->queue) : null;
        return [
            $this->textCard('Active request', $current?->status_label ?? '—', $current ? 'Your current support request' : 'You have no open request', $current ? route('seeker.requests.show', $current) : null) + ['icon'=>'message'],
            $this->textCard('Queue position', $position ? '#'.$position : '—', $position ? 'Follows priority and request time; it may change' : 'Shown while your request is waiting') + ['icon'=>'hourglass'],
            $this->card('Completed sessions', $period->constrain(clone $completed, 'end_time')->count(), 'Your sessions ending in selected period', link: route('session.history')) + ['icon'=>'check-circle'],
            $this->card('Feedback due', (clone $completed)->where('end_time', '<=', now())->whereDoesntHave('evaluation')->count(), 'All concluded sessions awaiting your feedback', link: route('session.history')) + ['task'=>true],
            $this->card('Referral decisions needed', (clone $referrals)->where('status', Referral::STATUS_PENDING_CONSENT)->count(), 'Current referrals awaiting your decision', link: route('seeker.referrals')) + ['task'=>true],
            $this->card('Upcoming appointments', $upcoming, 'Current confirmed appointments', link: route('seeker.referrals')) + ['task'=>true],
        ];
    }

    public function professional(User $user, ?array $analytics): array
    {
        abort_unless($user->is_active && $user->role === 'professional' && $user->psychologyProfessional, 403);
        $base = app(ProfessionalAnalytics::class)->base($user->psychologyProfessional->id);
        $upcoming = ReferralAppointment::whereIn('referral_id', (clone $base)->whereNotIn('status', ['declined','closed','completed','cancelled'])->select('id'))
            ->where('status', 'scheduled')->where('starts_at', '>=', now())->count();
        return [
            $this->card('Pending Referrals', $analytics['pending_now'] ?? null, 'Live · awaiting your decision', link: route('professional.referrals'), id: 'statPending'),
            $this->card('Active cases', $analytics['active_now'] ?? null, 'Live · accepted or ongoing', link: route('professional.cases'), id: 'statActive'),
            $this->card('Upcoming appointments', $upcoming, 'Live · scheduled appointments'),
            $this->card('Awaiting Adviser Review', $analytics['awaiting_review_now'] ?? null, 'Live · completion submitted; not yet confirmed', id: 'statAwaitingReview'),
            $this->card('Confirmed completions', $analytics['confirmed_completions'] ?? null, 'Adviser confirmation date · selected period', link: route('professional.reports')),
            $this->card('Average acceptance time', $analytics['response_minutes'] ?? null, 'Notification to acceptance · selected period', ' min', $analytics['response_samples'] ?? 0),
        ];
    }
}
