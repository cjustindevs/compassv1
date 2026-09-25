<?php
namespace App\Services;
class OperatingHoursService {
    public function acceptsAssignments(): bool {
        if (! config('app.enforce_duty_hours', false)) return true;
        $now = now('Asia/Manila');
        return !$now->isSunday() && $now->format('H:i') >= '18:00' && $now->format('H:i') < '22:30';
    }
    public function message(): string {
        return 'Live support: Monday to Saturday, 6:00 PM to 11:00 PM Philippine time. New sessions are accepted before 10:30 PM. Availability is not guaranteed. Self-help and emergency resources remain accessible.';
    }
}
