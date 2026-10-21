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

    public function admin(AnalyticsPeriod $period, array $technical): array
    {
        $valid = Session::where('is_test_record', false)->whereNull('duplicate_of_id');
        return [
            $this->card('Registered accounts', User::count(), 'Current registered accounts', link: route('admin.users')),
            $this->card('Enabled accounts', User::where('is_active', true)->count(), 'Current enabled accounts; not online users', link: route('admin.users', ['status'=>'active'])),
            $this->card('New registrations', $period->constrain(User::query(), 'created_at')->count(), 'Account creation date · selected period'),
            $this->card('Support requests', $period->constrain(clone $valid, AdminDashboardAnalytics::SUBMISSION_DATE)->count(), 'Submitted/legacy creation date · selected period'),
            $this->card('Completed sessions', $period->constrain((clone $valid)->whereIn('session_status', ['completed','evaluated'])->whereNotNull('start_time')->whereColumn('end_time','>=','start_time'), 'end_time')->count(), 'Actual session end · selected period'),
            $this->card('Security events', $technical['security_events'], 'Recorded events · selected period', link: route('admin.audit-logs')),
        ];
    }

    public function seeker(User $user, AnalyticsPeriod $period): array
    {
        abort_unless($user->is_active && $user->role === 'seeker' && $user->helpSeeker, 403);
        $sessions = Session::where('seeker_id', $user->helpSeeker->id)->where('is_test_record', false)->whereNull('duplicate_of_id');
        $referrals = Referral::whereIn('session_id', (clone $sessions)->select('id'));
        $upcoming = ReferralAppointment::whereIn('referral_id', (clone $referrals)->where('help_seeker_consent', true)->whereNotIn('status', ['declined','closed','completed','cancelled'])->select('id'))
            ->where('status', 'scheduled')->where('seeker_response', 'confirmed')->where('starts_at', '>=', now())->count();
        $completed = (clone $sessions)->whereIn('session_status', ['completed','evaluated'])->whereNotNull('start_time')->whereColumn('end_time', '>=', 'start_time');
        return [
            $this->card('Completed sessions', $period->constrain(clone $completed, 'end_time')->count(), 'Your sessions ending in selected period', link: route('session.history')),
            $this->card('Upcoming appointments', $upcoming, 'Current confirmed appointments', link: route('seeker.referrals')),
            $this->card('Referral decisions needed', (clone $referrals)->where('status', Referral::STATUS_PENDING_CONSENT)->count(), 'Current referrals awaiting your decision', link: route('seeker.referrals')),
            $this->card('Feedback due', (clone $completed)->where('end_time', '<=', now())->whereDoesntHave('evaluation')->count(), 'All concluded sessions awaiting your feedback', link: route('session.history')),
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
