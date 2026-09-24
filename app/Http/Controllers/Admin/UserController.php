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
        $users = User::query()
            ->with('helper')
            ->latest()
            ->get()
            ->map(fn (User $user) => $this->toDirectoryUser($user));

        return view('admin.users.index', [
            'admin' => $request->user(),
            'users' => $users,
            'roleOptions' => User::ROLE_LABELS,
            'userSummary' => [
                'registered' => User::count(),
                'pendingInvitations' => User::whereNull('email_verified_at')->count(),
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
    private function toDirectoryUser(User $user): array
    {
        $status = !$user->is_active ? 'offline' : ($user->role === 'helper'
            ? ($user->helper?->status ?? 'offline')
            : ($user->email_verified_at ? 'available' : 'offline'));

        return [
            'id' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'roleLabel' => User::ROLE_LABELS[$user->role] ?? Str::headline($user->role),
            'status' => in_array($status, ['available', 'busy', 'offline'], true) ? $status : 'offline',
            'statusLabel' => Str::headline($status),
            'joined' => $user->created_at?->format('M Y') ?? '—',
            'avatarUrl' => $user->avatar_path ? asset($user->avatar_path) : null,
            'initials' => $user->initials(),
        ];
    }
}
