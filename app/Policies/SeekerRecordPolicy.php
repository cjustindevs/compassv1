<?php
namespace App\Policies;
use App\Models\User;
use App\Models\Session;
use Illuminate\Database\Eloquent\Model;
class SeekerRecordPolicy {
    public function view(User $user, Model $record): bool {
        $seekerId = $record->seeker_id ?? $record->session?->seeker_id;
        return $user->is_active && $user->role === 'seeker' && $user->helpSeeker && (int)$user->helpSeeker->id === (int)$seekerId;
    }
    public function update(User $user, Model $record): bool { return $this->view($user,$record); }
    public function participate(User $user, Session $session): bool {
        return $user->is_active && (($user->role === 'seeker' && $this->view($user,$session)) || ($user->role === 'helper' && $user->helper && (int)$session->helper_id === (int)$user->helper->id));
    }
    public function evaluate(User $user, Session $session): bool {
        return $this->view($user,$session) && $session->session_status === Session::STATUS_COMPLETED;
    }
}
