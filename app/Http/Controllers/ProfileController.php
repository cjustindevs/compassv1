<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Session;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile page with session statistics.
     */
    public function show(Request $request): View
    {
        $user = $request->user();

        $stats = $this->sessionStatistics($user);

        return view('profile.index', [
            'user' => $user,
            'stats' => $stats,
        ]);
    }

    /**
     * Display the user's profile edit form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Upload a profile picture (stored in public/uploads/avatars).
     */
    public function updateAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $user = $request->user();

        $this->deleteAvatar($user);

        $file = $request->file('avatar');
        $filename = 'avatar-' . $user->id . '-' . Str::random(8) . '.' . $file->getClientOriginalExtension();

        $directory = public_path('uploads/avatars');
        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $file->move($directory, $filename);

        $user->update(['avatar_path' => 'uploads/avatars/' . $filename]);

        return back()->with('success', 'Profile picture updated.');
    }

    /**
     * Delete the user's account (with confirmation).
     */
    public function deleteAccount(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
            'confirm_delete' => ['required', 'accepted'],
        ]);

        $user = $request->user();

        $this->deleteAvatar($user);

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/')->with('success', 'Your account has been deleted. Take care of yourself.');
    }

    /**
     * Delete the user's account (Breeze-compatible alias, no confirmation field).
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        $this->deleteAvatar($user);

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    /**
     * Aggregate session statistics for the profile page.
     */
    private function sessionStatistics($user): array
    {
        $helpSeeker = $user->helpSeeker;

        if (! $helpSeeker) {
            return [
                'total_sessions' => 0,
                'average_rating' => 0,
                'total_minutes' => 0,
                'streak_days' => 0,
                'completed_sessions' => 0,
            ];
        }

        $sessions = Session::with('evaluation')
            ->where('seeker_id', $helpSeeker->id)
            ->get();

        $totalSessions = $sessions->count();
        $completed = $sessions->where('completion_status', 'completed')->whereNotNull('end_time');
        $totalMinutes = (int) $completed->sum('duration');

        $ratings = $sessions
            ->map(fn ($s) => $s->evaluation?->overall_score)
            ->filter(fn ($r) => $r !== null && $r > 0);

        $averageRating = $ratings->count() > 0
            ? round($ratings->sum() / $ratings->count(), 1)
            : 0;

        $sessionDates = $sessions
            ->map(fn ($s) => $s->created_date?->toDateString())
            ->filter()
            ->unique()
            ->sortDesc()
            ->values();

        $streakDays = 0;

        foreach ($sessionDates as $index => $date) {
            $expected = now()->startOfDay()->subDays($index)->toDateString();

            if ($date === $expected) {
                $streakDays++;
            } else {
                break;
            }
        }

        return [
            'total_sessions' => $totalSessions,
            'average_rating' => $averageRating,
            'total_minutes' => $totalMinutes,
            'streak_days' => $streakDays,
            'completed_sessions' => $completed->count(),
        ];
    }

    /**
     * Remove the stored avatar file from disk.
     */
    private function deleteAvatar($user): void
    {
        if ($user->avatar_path) {
            $path = public_path($user->avatar_path);

            if ($path && File::isFile($path)) {
                File::delete($path);
            }
        }
    }
}