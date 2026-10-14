<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Models\Adviser;
use App\Models\EmergencyAlert;
use App\Models\Helper;
use App\Models\HelpSeeker;
use App\Models\Moderator;
use App\Models\Notification;
use App\Models\PsychologyProfessional;
use App\Models\Referral;
use App\Models\SystemAdministrator;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\StaffMailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:120',
            'role' => 'nullable|in:'.implode(',', array_keys(User::ROLE_LABELS)),
            'status' => 'nullable|in:active,inactive',
        ]);
        $users = User::query()
            ->when($filters['q'] ?? null, fn ($query, $search) => $query->where(fn ($q) => $q
                ->whereRaw('LOWER(name) LIKE ?', ['%'.Str::lower(trim($search)).'%'])
                ->orWhereRaw('LOWER(email) LIKE ?', ['%'.Str::lower(trim($search)).'%'])))
            ->when($filters['role'] ?? null, fn ($q, $role) => $q->where('role', $role))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('is_active', $status === 'active'))
            ->latest()
            ->orderByDesc('id')
            ->paginate(25)->withQueryString()
            ->through(fn (User $user) => $this->toDirectoryUser($user));

        return view('admin.users.index', [
            'admin' => $request->user(),
            'users' => $users,
            'roleOptions' => User::ROLE_LABELS,
            'filters' => $filters,
            'userSummary' => [
                'registered' => User::count(),
                'unverified' => User::whereNull('email_verified_at')->count(),
            ],
        ]);
    }

    public function store(StoreUserRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($auditLogger, $request, $validated): void {
            $user = User::create([
                'name' => trim($validated['first_name'].' '.$validated['last_name']),
                'email' => $validated['email'],
                'password' => $validated['password'],
                'role' => $validated['role'],
                'is_active' => $validated['account_status'] === 'active',
                'email_verified_at' => $validated['account_status'] === 'active' ? now() : null,
            ]);

            $profile = ['user_account_id' => $user->id, 'first_name' => $validated['first_name'], 'last_name' => $validated['last_name'], 'email' => $user->email];
            $model = match ($user->role) {
                'adviser' => Adviser::class, 'helper' => Helper::class,
                'moderator' => Moderator::class, 'professional' => PsychologyProfessional::class,
                'admin' => SystemAdministrator::class, default => null,
            };
            if ($model) {
                $model::create($profile);
            } else {
                $alias = 'Seeker'.Str::upper(Str::random(12));
                $user->update(['name' => $alias]);
                HelpSeeker::create(['user_account_id' => $user->id, 'generated_alias' => $alias, 'pseudo_id' => (string) Str::uuid(), 'account_created' => now()]);
            }

            $role = User::ROLE_LABELS[$user->role] ?? Str::headline($user->role);
            app(StaffMailService::class)->enqueue($user, 'account-created-'.$user->id, 'COMPASS - Account created',
                'Your '.$role.' account has been created. '.($user->is_active ? 'You can sign in to COMPASS.' : 'Your account is pending activation by an administrator.'));
            if ($user->role === 'helper') {
                foreach (User::where('role', 'moderator')->where('is_active', true)->get() as $moderator) {
                    Notification::create(['user_account_id' => $moderator->id, 'title' => 'New Helper needs assignment', 'message' => 'A new Helper account needs an Adviser assignment.', 'notification_type' => 'assignment', 'link' => '/moderator/manage']);
                }
            }

            $auditLogger->record(
                $request->user(),
                AuditLogger::USER_CREATED,
                'users',
                $role.': '.$user->name,
                $request
            );
        });

        return redirect()->route('admin.users')->with(
            'success',
            'User account created. The user can set a password through the Forgot Password flow.'
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function deactivate(Request $request, User $user, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($request->user()?->role === 'admin' && $request->user()->is_active, 403);
        abort_if($request->user()->id === $user->id, 403, 'You cannot deactivate your own account.');
        $request->validate(['confirm_deactivation' => 'required|accepted']);
        DB::transaction(function () use ($user, $request, $auditLogger) {
            // Lock administrators in a consistent order so concurrent requests
            // cannot remove the final active administrator or act as an inactive one.
            $administrators = User::where('role', 'admin')->orderBy('id')->lockForUpdate()->get();
            abort_unless($administrators->firstWhere('id', $request->user()->id)?->is_active, 403);
            $user = User::lockForUpdate()->findOrFail($user->id);
            if (! $user->is_active) {
                return;
            }
            if ($user->role === 'admin' && $administrators->where('is_active', true)->count() <= 1) {
                throw ValidationException::withMessages(['account' => 'The last active administrator cannot be deactivated.']);
            }
            if ($helper = $user->helper) {
                $helper = Helper::lockForUpdate()->findOrFail($helper->id);
                $pendingDocumentation = $helper->sessions()->whereNotNull('start_time')->whereIn('session_status', ['completed', 'evaluated', 'cancelled', 'no_show'])->where(fn ($q) => $q->whereNull('documentation_status')->orWhere('documentation_status', '!=', 'submitted'))->exists();
                $pendingReferral = Referral::where('helper_id', $helper->id)->whereNotIn('status', ['completed', 'closed', 'cancelled', 'declined'])->exists();
                $pendingEmergency = EmergencyAlert::whereHas('session', fn ($q) => $q->where('helper_id', $helper->id))->whereNotIn('status', ['resolved', 'closed'])->exists();
                if ($helper->activeSessions()->exists() || $pendingDocumentation || $pendingReferral || $pendingEmergency) {
                    throw ValidationException::withMessages(['account' => 'This Helper has an active assignment or pending documentation, referral, or emergency obligation. Complete or authorize a handoff before deactivation.']);
                }
            }
            $user->update(['is_active' => false]);
            $auditLogger->record($request->user(), 'account_deactivated', 'users', 'Account deactivated: '.$user->name, $request, $user);
        });

        return back()->with('success', 'Account deactivated.');
    }

    private function toDirectoryUser(User $user): array
    {
        $status = $user->is_active ? 'active' : 'inactive';

        return [
            'id' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'roleLabel' => User::ROLE_LABELS[$user->role] ?? Str::headline($user->role),
            'status' => $status,
            'statusLabel' => Str::headline($status),
            'joined' => $user->created_at?->format('M Y') ?? '—',
            'avatarUrl' => $user->avatar_path ? asset($user->avatar_path) : null,
            'initials' => $user->initials(),
        ];
    }

    public function edit(User $user): View
    {
        $profile = $user->role === 'seeker' ? $user->helpSeeker : DB::table($this->profileTable($user))->where('user_account_id', $user->id)->first();

        return view('admin.users.edit', ['admin' => auth()->user(), 'account' => $user, 'profile' => $profile]);
    }

    public function update(Request $request, User $user, AuditLogger $auditLogger): RedirectResponse
    {
        if ($user->role !== 'seeker' && is_string($request->input('email'))) {
            $request->merge(['email' => mb_strtolower(trim($request->input('email')))]);
        }
        $rules = ['role' => 'prohibited', 'is_active' => 'prohibited', 'password' => 'prohibited'];
        if ($user->role === 'seeker') {
            $rules += ['age' => 'nullable|integer|between:1,120', 'gender' => 'nullable|in:male,female,non-binary,prefer-not-to-say,other,prefer_not_to_say'];
        } else {
            $rules += ['first_name' => 'required|string|max:100', 'last_name' => 'required|string|max:100', 'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)]];
        }
        $data = $request->validate($rules);
        DB::transaction(function () use ($user, $data, $request, $auditLogger) {
            $user = User::lockForUpdate()->findOrFail($user->id);
            if ($user->role === 'seeker') {
                $user->helpSeeker()->update($data);
            } else {
                $email = mb_strtolower(trim($data['email']));
                $user->update(['name' => trim($data['first_name']).' '.trim($data['last_name']), 'email' => $email,
                    'email_verified_at' => $email === $user->email ? $user->email_verified_at : null]);
                DB::table($this->profileTable($user))->where('user_account_id', $user->id)->update(['first_name' => trim($data['first_name']), 'last_name' => trim($data['last_name']), 'email' => $email]);
            }
            $auditLogger->record($request->user(), 'account_profile_updated', 'users', 'Profile updated; role and account status unchanged.', $request, $user);
        });

        return back()->with('success', 'Profile updated.');
    }

    public function reactivate(Request $request, User $user, AuditLogger $auditLogger): RedirectResponse
    {
        $request->validate(['confirm_reactivation' => 'required|accepted']);
        DB::transaction(function () use ($request, $user, $auditLogger) {
            $user = User::lockForUpdate()->findOrFail($user->id);
            if ($user->is_active) {
                return;
            }
            $user->update(['is_active' => true]);
            $auditLogger->record($request->user(), 'account_reactivated', 'users', 'Account access restored; operational eligibility is evaluated separately.', $request, $user);
        });

        return back()->with('success', 'Account reactivated. Helper duty and readiness requirements still apply.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $request->validate(['confirm_reset' => 'required|accepted']);
        if ($user->role === 'seeker') {
            return back()->with('error', 'Seekers request password resets using their private verified recovery email. Admins cannot access that address.');
        }
        try {
            $result = Password::sendResetLink(['email' => $user->email]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Password-reset delivery failed. The password was not changed.');
        }

        return back()->with($result === Password::RESET_LINK_SENT ? 'success' : 'error', __($result));
    }

    private function profileTable(User $user): string
    {
        return match ($user->role) {
            'helper' => 'helpers','adviser' => 'advisers','moderator' => 'moderators','professional' => 'psychology_professionals','admin' => 'system_administrators',default => abort(422)
        };
    }
}
