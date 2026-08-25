<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditLogger
{
    public const ADMIN_LOGIN_FAILED = 'ADMIN_LOGIN_FAILED';

    public const ADMIN_LOGIN_SUCCEEDED = 'ADMIN_LOGIN_SUCCEEDED';

    public const USER_CREATED = 'USER_CREATED';

    public const PASSWORD_CHANGED = 'PASSWORD_CHANGED';

    /**
     * Append an immutable system activity record.
     */
    public function record(
        ?User $actor,
        string $action,
        ?string $module = null,
        ?string $description = null,
        ?Request $request = null
    ): AuditLog {
        $request ??= request();

        $auditLog = new AuditLog;
        $auditLog->user_account_id = $actor?->getKey();
        $auditLog->action = Str::limit($action, 255, '');
        $auditLog->module = $module === null ? null : Str::limit($module, 255, '');
        $auditLog->description = $description;
        $auditLog->ip_address = $request->ip();
        $auditLog->user_agent = $request->userAgent();
        $auditLog->save();

        return $auditLog;
    }
}
