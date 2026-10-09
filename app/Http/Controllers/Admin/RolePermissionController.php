<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class RolePermissionController extends Controller
{
    public function __invoke(Request $request): View
    {
        $validated = $request->validate(['role' => 'nullable|in:'.implode(',', array_keys(User::ROLE_LABELS))]);
        $selectedRole = $validated['role'] ?? 'admin';
        // These are descriptions of existing operations, not editable grants.
        // Access comes from the registered route's role middleware. Record-level
        // authorization, readiness, consent, and workflow checks still apply.
        $definitions = [
            'Account administration' => [
                ['View user accounts', ['admin.users'], 'Administrator account directory.'],
                ['Create user accounts', ['admin.users.store'], 'Validated account creation and role-specific profiles.'],
                ['Deactivate accounts', ['admin.users.deactivate'], 'Confirmation required; self-deactivation, the final administrator, and protected Helper obligations are blocked.'],
                ['View Roles & Permissions', ['admin.roles-permissions'], 'Read-only access summary; this interface does not grant permissions.'],
                ['View audit logs', ['admin.audit-logs'], 'Immutable system events with protected clinical narratives.'],
                ['View system health and backups', ['admin.system-health', 'admin.backup-restore'], 'Existing administration views; only configured operations are available.'],
            ],
            'Support and coordination' => [
                ['Request peer support', ['request.screening.process'], 'The Seeker submits their own screening and request.'],
                ['Accept assigned peer-support cases', ['helper.cases.accept'], 'Assigned Helper only, subject to existing readiness, duty, and availability rules.'],
                ['Complete readiness checks', ['helper.readiness.store'], 'Helper records their own readiness check.'],
                ['Assign waiting requests', ['moderator.queue.assign'], 'Moderator controls assignment and existing Helper eligibility checks.'],
                ['Schedule Helper duty', ['moderator.schedules.store', 'adviser.schedule.update'], 'Moderator scheduling or Adviser scheduling within their supervised scope.'],
                ['Evaluate Helper sessions', ['adviser.evaluate.store'], 'Adviser reviews authorized sessions within their scope.'],
            ],
            'Emergency and referral operations' => [
                ['Monitor emergency cases', ['moderator.emergency', 'adviser.emergencies'], 'Moderator operations or Adviser case scope; identity disclosure remains separately restricted.'],
                ['Resolve emergency cases', ['adviser.emergencies.resolve'], 'Responsible Adviser only. Moderators monitor and coordinate; they cannot resolve emergencies.'],
                ['Review and assign professional referrals', ['adviser.referral.approve', 'adviser.referral.assign'], 'Adviser review, case scope, consent, and referral workflow apply.'],
                ['Coordinate referrals without an Adviser', ['moderator.referrals.unassigned'], 'Existing Moderator and Administrator fallback coordination.'],
                ['Accept professional referrals', ['professional.referral.accept'], 'Assigned Psychology Professional with required appointment and referral checks.'],
            ],
            'Reports and records' => [
                ['View role-based reports', ['admin.reports', 'moderator.reports', 'adviser.reports', 'helper.reports', 'professional.reports'], 'System-level summaries for Administrators; other roles see their existing authorized service or case scope.'],
                ['Export role-based reports', ['moderator.reports.export', 'adviser.reports.export', 'helper.reports.export', 'professional.reports.export'], 'Only existing export endpoints and supported formats; Administrator catalog exports are not configured.'],
                ['Access supervised transcripts', ['adviser.transcripts'], 'Adviser scope and transcript authorization apply; unrelated private conversations remain restricted.'],
                ['View personal support history', ['session.history'], 'Seeker access to their own history.'],
            ],
        ];
        $groups = [];
        foreach ($definitions as $group => $operations) {
            $groups[$group] = array_map(function ($operation) use ($selectedRole) {
                [$label, $names, $description] = $operation;
                $allowed = false;
                foreach ($names as $name) {
                    $route = Route::getRoutes()->getByName($name);
                    if (!$route) continue;
                    $roleRules = collect($route->gatherMiddleware())->filter(fn ($rule) => is_string($rule) && str_starts_with($rule, 'role:'));
                    // Do not infer universal access from an auth-only route.
                    if ($roleRules->isNotEmpty() && $roleRules->every(fn ($rule) => in_array($selectedRole, explode(',', substr($rule, 5)), true))) {
                        $allowed = true;
                    }
                }
                return compact('label', 'description', 'allowed');
            }, $operations);
        }
        return view('admin.roles-permissions.index', [
            'admin' => $request->user(), 'roles' => User::ROLE_LABELS, 'selectedRole' => $selectedRole, 'groups' => $groups,
            'responsibilities' => [
                'seeker' => 'Requests support, manages consent, and views their own sessions and referrals.',
                'helper' => 'Provides assigned peer support, completes readiness and documentation, and views personal feedback and reports.',
                'moderator' => 'Coordinates the incoming queue, eligible Helpers, duty schedules, connections, and emergency operations.',
                'adviser' => 'Supervises Helpers and authorized cases, reviews screening and evaluations, and coordinates professional referrals.',
                'professional' => 'Manages assigned professional referrals, appointments, and authorized case records.',
                'admin' => 'Administers accounts and reviews system audit records, role access, configuration, and reporting.',
            ],
        ]);
    }
}
