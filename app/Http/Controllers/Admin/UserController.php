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
                'password' => Str::password(40),
                'role' => $validated['role'],
                'email_verified_at' => $validated['account_status'] === 'active' ? now() : null,
            ]);

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
        $status = $user->role === 'helper'
            ? ($user->helper?->status ?? 'offline')
            : ($user->email_verified_at ? 'available' : 'offline');

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
