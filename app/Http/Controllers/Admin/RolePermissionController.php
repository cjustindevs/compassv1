<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RolePermissionController extends Controller
{
    /**
     * Display the local role-based access control matrix.
     *
     * The repository does not yet have persistent permission entities. Keep
     * this view model isolated so it can be replaced by an RBAC service once
     * the backend authorization design is approved.
     */
    public function __invoke(Request $request): View
    {
        return view('admin.roles-permissions.index', [
            'admin' => $request->user(),
            'permissionActions' => ['read', 'create', 'update', 'delete', 'approve', 'export'],
            'permissionMatrix' => $this->localPermissionMatrix(),
            'currentRole' => User::ROLE_LABELS['admin'],
            'existingRoles' => array_values(User::ROLE_LABELS),
        ]);
    }

    /**
     * Initial local-only matrix matching the supplied administrator reference.
     *
     * @return array<int, array<string, mixed>>
     */
    private function localPermissionMatrix(): array
    {
        return [
            $this->category('dashboard', 'Dashboard', 'dashboard', ['read']),
            $this->category('users', 'Users', 'users'),
            $this->category('reports', 'Reports', 'file-text', ['read']),
            $this->category('announcements', 'Announcements', 'megaphone', ['read']),
            $this->category('system-health', 'System Health', 'activity'),
            $this->category('audit-logs', 'Audit Logs', 'audit-log'),
            $this->category('settings', 'Settings', 'sliders'),
            $this->category('referral-access', 'Referral Access', 'inbox', ['read', 'create', 'update', 'export']),
            $this->category('clinical-notes', 'Clinical Notes', 'clipboard', ['read', 'create', 'update', 'export']),
            $this->category('completed-cases', 'Completed Cases', 'folder-check', ['read', 'export']),
        ];
    }

    /**
     * @param  array<int, string>  $enabled
     * @return array<string, mixed>
     */
    private function category(string $id, string $name, string $icon, array $enabled = []): array
    {
        $permissions = [];

        foreach (['read', 'create', 'update', 'delete', 'approve', 'export'] as $action) {
            $permissions[$action] = in_array($action, $enabled, true);
        }

        return compact('id', 'name', 'icon', 'permissions');
    }
}
