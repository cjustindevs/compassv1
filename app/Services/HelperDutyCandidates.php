<?php

namespace App\Services;

use App\Models\Helper;
use Illuminate\Support\Facades\DB;

class HelperDutyCandidates
{
    public function allows(Helper $helper): bool
    {
        $user = $helper->user;
        if (! $user || ! $user->is_active || $user->role !== 'helper'
            || $helper->getCurrentReadiness()?->assessment_result !== 'ready') {
            return false;
        }

        // A saved availability label is not evidence of a current login.
        // Non-database drivers cannot supply an authoritative session roster.
        if (config('session.driver') !== 'database') {
            return false;
        }

        return DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->where('last_activity', '>', now()->subMinutes((int) config('session.lifetime', 120))->timestamp)
            ->exists();
    }
}
