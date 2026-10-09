<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

            $profile = ['user_account_id'=>$user->id,'first_name'=>$validated['first_name'],'last_name'=>$validated['last_name'],'email'=>$user->email];
            $model = match($user->role) {
                'adviser'=>\App\Models\Adviser::class, 'helper'=>\App\Models\Helper::class,
                'moderator'=>\App\Models\Moderator::class, 'professional'=>\App\Models\PsychologyProfessional::class,
                'admin'=>\App\Models\SystemAdministrator::class, default=>null,
            };
            if ($model) $model::create($profile);
            else {
                $alias='Seeker'.Str::upper(Str::random(12));
                $user->update(['name'=>$alias]);
                \App\Models\HelpSeeker::create(['user_account_id'=>$user->id,'generated_alias'=>$alias,'pseudo_id'=>(string)Str::uuid(),'account_created'=>now()]);
            }

            $role = User::ROLE_LABELS[$user->role] ?? Str::headline($user->role);

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
        abort_unless($request->user()?->role==='admin' && $request->user()->is_active,403);
        abort_if($request->user()->id===$user->id,403,'You cannot deactivate your own account.');
        $request->validate(['confirm_deactivation' => 'required|accepted']);
        DB::transaction(function()use($user, $request, $auditLogger){
            // Lock administrators in a consistent order so concurrent requests
            // cannot remove the final active administrator or act as an inactive one.
            $administrators = User::where('role', 'admin')->orderBy('id')->lockForUpdate()->get();
            abort_unless($administrators->firstWhere('id', $request->user()->id)?->is_active, 403);
            $user=User::lockForUpdate()->findOrFail($user->id);
            if(!$user->is_active)return;
            if ($user->role === 'admin' && $administrators->where('is_active', true)->count() <= 1) {
                throw \Illuminate\Validation\ValidationException::withMessages(['account' => 'The last active administrator cannot be deactivated.']);
            }
            if($helper=$user->helper){
                $helper=\App\Models\Helper::lockForUpdate()->findOrFail($helper->id);
                $pendingDocumentation=$helper->sessions()->whereNotNull('start_time')->whereIn('session_status',['completed','evaluated','cancelled','no_show'])->where(fn($q)=>$q->whereNull('documentation_status')->orWhere('documentation_status','!=','submitted'))->exists();
                $pendingReferral=\App\Models\Referral::where('helper_id',$helper->id)->whereNotIn('status',['completed','closed','cancelled','declined'])->exists();
                $pendingEmergency=\App\Models\EmergencyAlert::whereHas('session',fn($q)=>$q->where('helper_id',$helper->id))->whereNotIn('status',['resolved','closed'])->exists();
                if($helper->activeSessions()->exists() || $pendingDocumentation || $pendingReferral || $pendingEmergency)throw \Illuminate\Validation\ValidationException::withMessages(['account'=>'This Helper has an active assignment or pending documentation, referral, or emergency obligation. Complete or authorize a handoff before deactivation.']);
            }
            $user->update(['is_active'=>false]);
            $auditLogger->record($request->user(), 'account_deactivated', 'users', 'Account deactivated: '.$user->name, $request, $user);
        });
        return back()->with('success','Account deactivated.');
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
}
