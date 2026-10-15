<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ConcernCategory;
use App\Models\ConsentRecord;
use App\Models\EmergencyAlert;
use App\Models\Helper;
use App\Models\HelpSeeker;
use App\Models\HelpSeekerEvaluation;
use App\Models\Message;
use App\Models\ProfessionalNote;
use App\Models\PsychologyProfessional;
use App\Models\Referral;
use App\Models\ReferralAppointment;
use App\Models\ReferralCompletionReview;
use App\Models\Session;
use App\Models\SessionReport;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** An additive, silent historical dataset. No queues, users or current eligibility are changed. */
class DefenseDemoData
{
    public const BATCH = 'defense-2026-10-16-v1';

    public const ACTION = 'defense_demo_imported';

    public const LABEL = '[DEMO '.self::BATCH.']';

    public function existing(): ?AuditLog
    {
        return AuditLog::where('action', self::ACTION)->where('description', self::BATCH)->first();
    }

    public function import(): array
    {
        return DB::transaction(function () {
            // Concurrent Render starts must not both insert the same batch.
            if (DB::getDriverName() === 'pgsql') {
                DB::select('SELECT pg_advisory_xact_lock(20261016, 1)');
            }
            if ($batch = $this->existing()) {
                return ['created' => false, 'counts' => $batch->metadata['counts']];
            }

            $helpers = Helper::with('adviser')->whereHas('user', fn ($q) => $q->where('role', 'helper')->where('is_active', true))
                ->whereHas('adviser.user', fn ($q) => $q->where('role', 'adviser')->where('is_active', true))->orderBy('id')->limit(6)->get();
            $seekers = HelpSeeker::whereHas('user', fn ($q) => $q->where('role', 'seeker')->where('is_active', true))->orderBy('id')->limit(20)->get();
            $professionals = PsychologyProfessional::whereHas('user', fn ($q) => $q->where('role', 'professional')->where('is_active', true))->orderBy('id')->limit(2)->get();
            $concerns = ConcernCategory::where('is_active', true)->orderBy('id')->get();
            if ($helpers->isEmpty() || $seekers->isEmpty() || $professionals->isEmpty() || $concerns->isEmpty()) {
                throw new RuntimeException('Existing active Seeker, supervised Helper, Adviser, Professional and concern category are required.');
            }

            return Model::withoutEvents(function () use ($helpers, $seekers, $professionals, $concerns) {
                $manifest = [];
                $put = function (string $class, array $data, CarbonImmutable $time) use (&$manifest): Model {
                    $row = $class::forceCreate($data + ['created_at' => $time, 'updated_at' => $time]);
                    $manifest[$row->getTable()][] = $row->getKey();

                    return $row;
                };
                for ($i = 0; $i < 40; $i++) {
                    $helper = $helpers[$i % $helpers->count()];
                    $seeker = $seekers[$i % $seekers->count()];
                    $concern = $concerns[$i % $concerns->count()];
                    // Never create future or still-running cases. Spread records over 29 past days.
                    $start = CarbonImmutable::now('Asia/Manila')->startOfDay()->subDays(1 + (($i * 7) % 29))->setTime(9 + ($i % 8), ($i * 11) % 60)->utc();
                    $minutes = 20 + (($i * 7) % 31);
                    $end = $start->addMinutes($minutes);
                    $referred = $i >= 30;
                    $emergency = $i >= 36;
                    $risk = $emergency ? 'emergency' : ['low', 'moderate', 'low', 'high'][$i % 4];
                    $session = $put(Session::class, [
                        'seeker_id' => $seeker->id, 'helper_id' => $helper->id, 'review_adviser_id' => $helper->adviser_id,
                        'concern_id' => $concern->id, 'concern_category' => $concern->concern_name, 'session_type' => 'chat',
                        'session_status' => 'evaluated', 'completion_status' => 'completed', 'completion_reason' => 'completed',
                        'risk_level' => $risk, 'start_time' => $start, 'end_time' => $end, 'duration' => $minutes,
                        'created_date' => $start->subMinutes(10), 'submitted_at' => $start->subMinutes(10),
                        'helper_accepted_at' => $start, 'seeker_evaluation_submitted' => true,
                        'matching_details' => ['demo_batch' => self::BATCH],
                        'documentation_status' => 'submitted',
                    ], $start->subMinutes(10));
                    foreach ([['seeker', $seeker->user_account_id, 'I would like to talk about balancing studies and daily responsibilities.'],
                        ['helper', $helper->user_account_id, 'Thank you for sharing. What would feel most helpful to discuss today?']] as $n => [$sender, $userId, $text]) {
                        $put(Message::class, ['session_id' => $session->id, 'sender_id' => $userId, 'sender' => $sender,
                            'message_text' => self::LABEL.' '.$text, 'sent_datetime' => $start->addMinutes($n)], $start->addMinutes($n));
                    }
                    $put(SessionReport::class, ['session_id' => $session->id,
                        'session_summary' => self::LABEL.' Fictional completed peer-support conversation about '.$concern->concern_name.'.',
                        'personal_reflection' => self::LABEL.' Practised listening, reflection and summarizing; historical demonstration only.',
                        'help_seeker_condition' => self::LABEL.' Fictional scenario; no real clinical assessment.',
                        'referral_recommended' => $referred, 'adviser_reviewed' => false,
                        'skills_applied' => ['active_listening', 'empathy', 'reflection'], 'created_date' => $end,
                        'summary_submitted_at' => $end, 'reflection_submitted_at' => $end, 'documented_at' => $end,
                    ], $end);
                    $answers = [];
                    foreach (EvaluationInstrument::OPTIONS as $field => $options) {
                        $answers[$field] = $options[($i + strlen($field)) % count($options)];
                    }
                    $scores = EvaluationInstrument::scores($answers);
                    $put(HelpSeekerEvaluation::class, $scores + ['session_id' => $session->id, 'answers' => $answers,
                        'instrument_version' => 'compass-v4-categorical-1', 'submitted_at' => $end->addMinutes(1),
                        'overall_score' => round(array_sum($scores) / count($scores), 1),
                        'comments' => self::LABEL.' Sample experience rating for dashboard demonstration.'], $end->addMinutes(1));
                    if (! $referred) {
                        continue;
                    }
                    $professional = $professionals[$i % $professionals->count()];
                    $referral = $put(Referral::class, [
                        'session_id' => $session->id, 'helper_id' => $helper->id, 'adviser_id' => $helper->adviser_id,
                        'professional_id' => $professional->id, 'priority_level' => $risk,
                        'help_seeker_consent' => true, 'identity_disclosed' => false,
                        'referral_reason' => self::LABEL.' Fictional historical referral for additional support.',
                        'referral_date' => $start, 'approved_at' => $start->addMinutes(2), 'reviewed_at' => $start->addMinutes(2),
                        'review_notes' => self::LABEL.' Simulated historical approval, not a real staff decision.',
                        'consent_obtained_at' => $start->addMinutes(3), 'accepted_at' => $start->addMinutes(4),
                        'status' => 'completed', 'completed_at' => $end->addDays(2), 'closed_date' => $end->addDays(2),
                        'outcome' => self::LABEL.' Sample completed referral; see simulated completion report.',
                    ], $start);
                    // These are referral-specific synthetic events; general account consents are untouched.
                    $put(ConsentRecord::class, ['seeker_id' => $seeker->id, 'session_id' => $session->id, 'referral_id' => $referral->id,
                        'document_type' => 'informed_consent', 'purpose' => 'referral', 'scope' => self::LABEL.' Synthetic historical consent event only.',
                        'version' => ConsentService::VERSION, 'decision' => 'accepted', 'consent_given' => true,
                        'consent_date' => $start->addMinutes(3), 'withdrawn' => false, 'user_agent' => self::BATCH], $start->addMinutes(3));
                    $appointmentEnd = $end->addHours(1);
                    $put(ReferralAppointment::class, ['referral_id' => $referral->id, 'professional_id' => $professional->id,
                        'created_by' => $professional->user_account_id, 'starts_at' => $end->addMinutes(10), 'ends_at' => $appointmentEnd,
                        'meeting_format' => 'in_person', 'meeting_details' => self::LABEL.' Fictional past appointment; do not attend.',
                        'status' => 'completed', 'seeker_response' => 'confirmed', 'responded_at' => $end], $end);
                    $put(ProfessionalNote::class, ['referral_id' => $referral->id, 'professional_id' => $professional->id,
                        'session_id' => $session->id, 'intervention_type' => 'counseling_session',
                        'notes' => self::LABEL.' Fictional completed support; not a real clinical record.'], $appointmentEnd);
                    $put(ReferralCompletionReview::class, ['referral_id' => $referral->id, 'professional_id' => $professional->id,
                        'submitted_by' => $professional->user_account_id, 'summary' => self::LABEL.' Simulated outcome for a completed historical referral.',
                        'follow_up_required' => false, 'review_status' => 'confirmed', 'reviewed_by' => $helper->adviser->user_account_id,
                        'review_notes' => self::LABEL.' Simulated completion decision; no real staff decision was made.',
                        'reviewed_at' => $end->addDays(2)], $appointmentEnd);
                    if ($emergency) {
                        $put(EmergencyAlert::class, ['session_id' => $session->id, 'seeker_id' => $seeker->id,
                            'adviser_id' => $helper->adviser_id, 'referral_id' => $referral->id,
                            'trigger_reason' => self::LABEL.' Fictional resolved emergency for historical reporting.',
                            'triggered_at' => $start, 'status' => 'resolved', 'professional_referred' => true,
                            'professional_referred_at' => $start->addMinutes(4), 'resolved_at' => $end->addDays(2),
                            'resolution_notes' => self::LABEL.' Simulated historical resolution. No current emergency.'], $start);
                    }
                }
                $counts = array_map('count', $manifest);
                AuditLog::create(['action' => self::ACTION, 'module' => 'demo_data', 'description' => self::BATCH,
                    'outcome' => 'success', 'metadata' => ['batch' => self::BATCH, 'counts' => $counts, 'record_ids' => $manifest,
                        'existing_records_modified' => false, 'notifications_sent' => false, 'source' => 'synthetic historical demonstration']]);

                return ['created' => true, 'counts' => $counts];
            });
        }, 3);
    }
}
