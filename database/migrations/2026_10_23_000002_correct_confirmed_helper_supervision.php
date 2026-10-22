<?php

use App\Models\Adviser;
use App\Models\Helper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration {
    public function up(): void
    {
        // User-confirmed production IDs. Never apply this to unrelated demo accounts.
        DB::transaction(function () {
            $adviser = Adviser::find(5);
            $helper = Helper::find(16);
            if (!$helper || !$adviser) return;
            if (mb_strtolower(trim($helper->first_name)) !== 'lean'
                || mb_strtolower(trim($helper->last_name)) !== 'dancel'
                || mb_strtolower(trim($adviser->last_name)) !== 'james'
                || !$adviser->user?->is_active || $adviser->user?->role !== 'adviser') {
                Log::notice('Confirmed supervision correction skipped: account identity check did not match.', ['helper_id'=>16,'adviser_id'=>5]);
                return;
            }
            if ((int)$helper->adviser_id === (int)$adviser->id) return;
            $helper->assignmentReason = 'User-confirmed correction: Helper #16 Lean Dancel is supervised by Adviser #5 James.';
            // Canonical save preserves assignment history and enforces Adviser capacity.
            $helper->update(['adviser_id'=>$adviser->id]);
        }, 3);
    }

    public function down(): void
    {
        // A deployment rollback must not silently undo a corrected live assignment.
    }
};
