<?php

namespace App\Services;

use App\Models\{AuditLog, User};

class TechnicalDashboardSummary
{
    public function data(AnalyticsPeriod $period): array
    {
        $events = $period->constrain(AuditLog::query(), 'created_at');
        $security = (clone $events)->where(fn ($q) => $q->where('module', 'security')->orWhere('action', AuditLogger::ADMIN_LOGIN_FAILED))->count();
        $failedLogins = (clone $events)->where('action', AuditLogger::ADMIN_LOGIN_FAILED)->count();
        $health = 'Not monitored';
        try {
            $snapshot = app(SystemHealthMonitor::class)->snapshot();
            if (! in_array($snapshot['source'] ?? 'unavailable', ['preview', 'unavailable'], true)) {
                $statuses = collect($snapshot['services'] ?? [])->pluck('status');
                $health = $statuses->contains('critical') ? 'Critical' : ($statuses->contains('warning') ? 'Degraded' : ($statuses->isNotEmpty() && $statuses->every(fn ($s) => $s === 'healthy') ? 'Operational' : 'Unknown'));
            }
        } catch (\Throwable $error) { report($error); }
        $backups = app(BackupCatalog::class)->snapshots();

        return ['security_events' => $security, 'failed_admin_logins' => $failedLogins, 'health' => $health,
            'backup' => $backups->isEmpty() ? 'Not configured' : 'View backup records',
            'inactive' => User::where('is_active', false)->count(), 'total' => User::count(),
            'audit' => $period->series(AuditLog::whereIn('module', ['auth', 'authentication', 'users', 'security', 'system', 'backups']), 'created_at')];
    }
}
