<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ActiveSessionCatalog
{
    /**
     * Return only the non-sensitive device metadata needed by the account
     * review dialog. Session payloads and IP addresses are never exposed.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function sessions(Request $request, User $user): Collection
    {
        if (config('session.driver') !== 'database') {
            return collect([$this->currentSession($request)]);
        }

        $currentId = $request->session()->getId();
        $records = DB::table((string) config('session.table', 'sessions'))
            ->where('user_id', $user->getKey())
            ->orderByDesc('last_activity')
            ->get(['id', 'user_agent', 'last_activity'])
            ->map(fn (object $session): array => $this->viewModel(
                (string) $session->id,
                (string) ($session->user_agent ?? ''),
                (int) $session->last_activity,
                hash_equals($currentId, (string) $session->id)
            ));

        if (! $records->contains('current', true)) {
            $records->prepend($this->currentSession($request));
        }

        return $records->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function currentSession(Request $request): array
    {
        return $this->viewModel(
            $request->session()->getId(),
            (string) $request->userAgent(),
            now()->getTimestamp(),
            true
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function viewModel(string $id, string $userAgent, int $lastActivity, bool $current): array
    {
        $timestamp = CarbonImmutable::createFromTimestamp($lastActivity);

        return [
            'id' => hash('sha256', $id),
            'device' => $this->deviceLabel($userAgent),
            'current' => $current,
            'lastActiveLabel' => $current ? 'Active now' : 'Last active '.$timestamp->diffForHumans(),
            'lastActiveIso' => $timestamp->toIso8601String(),
            'lastActiveTitle' => $timestamp->format('F j, Y \a\t h:i A'),
        ];
    }

    private function deviceLabel(string $userAgent): string
    {
        $browser = match (true) {
            Str::contains($userAgent, ['Edg/', 'Edge/']) => 'Edge',
            Str::contains($userAgent, ['Chrome/', 'CriOS/']) => 'Chrome',
            Str::contains($userAgent, ['Firefox/', 'FxiOS/']) => 'Firefox',
            Str::contains($userAgent, ['Safari/']) => 'Safari',
            default => 'Browser',
        };
        $platform = match (true) {
            Str::contains($userAgent, ['iPhone', 'iPad']) => 'iOS',
            Str::contains($userAgent, ['Android']) => 'Android',
            Str::contains($userAgent, ['Macintosh', 'Mac OS']) => 'macOS',
            Str::contains($userAgent, ['Windows']) => 'Windows',
            Str::contains($userAgent, ['Linux']) => 'Linux',
            default => 'unknown device',
        };

        return $browser.' on '.$platform;
    }
}
