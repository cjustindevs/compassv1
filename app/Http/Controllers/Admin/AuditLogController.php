<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class AuditLogController extends Controller
{
    private const ACTOR_FILTERS = [
        'all' => 'All actors',
        'admin' => 'Administrator',
        'moderator' => 'Moderator',
        'adviser' => 'Adviser',
        'professional' => 'Professional',
        'system' => 'System',
    ];

    private const CATEGORY_FILTERS = [
        'all' => 'All event categories',
        'authentication' => 'Authentication',
        'users' => 'User management',
        'permissions' => 'Roles & Permissions',
        'referrals' => 'Referrals',
        'sessions' => 'Sessions',
        'announcements' => 'Announcements',
        'resources' => 'Resource Library',
        'backups' => 'Backups',
        'reports' => 'Reports',
        'system' => 'System',
    ];

    private const DATE_FILTERS = [
        'all' => 'All dates',
        'today' => 'Today',
        '7-days' => 'Last 7 days',
        '30-days' => 'Last 30 days',
    ];

    private const PAGE_SIZES = [25, 50, 100];

    /**
     * Display persisted immutable audit records.
     */
    public function __invoke(Request $request): View
    {
        $filters = $this->filters($request);
        $loadFailed = false;

        try {
            $logs = $this->realLogs($request, $filters);
        } catch (Throwable $exception) {
            report($exception);
            $loadFailed = true;
            $logs = $this->emptyPaginator($request, $filters['perPage']);
        }

        return view('admin.audit-logs.index', [
            'admin' => $request->user(),
            'logs' => $logs,
            'filters' => $filters,
            'actorFilters' => self::ACTOR_FILTERS,
            'categoryFilters' => self::CATEGORY_FILTERS,
            'dateFilters' => self::DATE_FILTERS,
            'pageSizes' => self::PAGE_SIZES,
            'loadFailed' => $loadFailed,
            'activeFilterCount' => collect(['actor', 'category', 'date'])
                ->filter(fn (string $key): bool => $filters[$key] !== 'all')
                ->count(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function realLogs(Request $request, array $filters): LengthAwarePaginator
    {
        $query = AuditLog::query()->with('actor')->latest('created_at');

        $this->applySearch($query, $filters['search']);
        $this->applyActorFilter($query, $filters['actor']);
        $this->applyCategoryFilter($query, $filters['category']);
        $this->applyDateFilter($query, $filters['date']);

        return $query
            ->paginate($filters['perPage'])
            ->withQueryString()
            ->through(fn (AuditLog $log): array => $this->realRecord($log));
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        $actor = $request->string('actor')->toString();
        $category = $request->string('category')->toString();
        $date = $request->string('date')->toString();
        $perPage = $request->integer('per_page', 25);

        return [
            'search' => Str::limit($request->string('q')->trim()->toString(), 120, ''),
            'actor' => array_key_exists($actor, self::ACTOR_FILTERS) ? $actor : 'all',
            'category' => array_key_exists($category, self::CATEGORY_FILTERS) ? $category : 'all',
            'date' => array_key_exists($date, self::DATE_FILTERS) ? $date : 'all',
            'perPage' => in_array($perPage, self::PAGE_SIZES, true) ? $perPage : 25,
        ];
    }

    private function applySearch(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $like = '%'.Str::lower($search).'%';

        $query->where(function (Builder $query) use ($like): void {
            $query
                ->whereRaw('LOWER(action) LIKE ?', [$like])
                ->orWhereRaw('LOWER(COALESCE(module, ?)) LIKE ?', ['', $like])
                ->orWhereRaw('LOWER(COALESCE(description, ?)) LIKE ?', ['', $like])
                ->orWhereHas('actor', function (Builder $actor) use ($like): void {
                    $actor->whereRaw('LOWER(name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$like]);
                });
        });
    }

    private function applyActorFilter(Builder $query, string $actor): void
    {
        if ($actor === 'all') {
            return;
        }

        if ($actor === 'system') {
            $query->whereNull('user_account_id');

            return;
        }

        $query->whereHas('actor', fn (Builder $user): Builder => $user->where('role', $actor));
    }

    private function applyCategoryFilter(Builder $query, string $category): void
    {
        if ($category === 'all') {
            return;
        }

        $terms = match ($category) {
            'authentication' => ['auth', 'login', 'password'],
            'users' => ['user', 'account'],
            'permissions' => ['role', 'permission'],
            'referrals' => ['referral', 'assignment'],
            'sessions' => ['session', 'case'],
            'announcements' => ['announcement'],
            'resources' => ['resource', 'library'],
            'backups' => ['backup', 'restore'],
            'reports' => ['report', 'export'],
            default => ['system', 'configuration'],
        };

        $query->where(function (Builder $query) use ($terms): void {
            foreach ($terms as $term) {
                $like = '%'.$term.'%';
                $query->orWhereRaw('LOWER(COALESCE(module, ?)) LIKE ?', ['', $like])
                    ->orWhereRaw('LOWER(action) LIKE ?', [$like]);
            }
        });
    }

    private function applyDateFilter(Builder $query, string $date): void
    {
        if ($date !== 'all') {
            $query->where('created_at', '>=', $this->dateThreshold($date));
        }
    }

    private function dateThreshold(string $date): CarbonImmutable
    {
        return match ($date) {
            'today' => CarbonImmutable::now()->startOfDay(),
            '7-days' => CarbonImmutable::now()->subDays(7),
            default => CarbonImmutable::now()->subDays(30),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function realRecord(AuditLog $log): array
    {
        $actor = $log->actor;
        $actorType = $actor?->role ?? 'system';

        return $this->formatRecord([
            'id' => (string) $log->id,
            'actor' => $this->actorLabel($actor),
            'actorType' => $actorType,
            'action' => $this->humanizeAction($log->action),
            'category' => $this->eventCategory($log->module, $log->action),
            'target' => $log->description ?: ($log->module ? Str::headline($log->module) : '—'),
            'createdAt' => CarbonImmutable::instance($log->created_at),
        ]);
    }

    private function actorLabel(?User $actor): string
    {
        if (! $actor) {
            return 'system';
        }

        if ($actor->role === 'admin') {
            return $actor->email;
        }

        $role = match ($actor->role) {
            'professional' => 'professional',
            default => $actor->role,
        };

        return $role.': '.$actor->name;
    }

    private function humanizeAction(string $action): string
    {
        return Str::of($action)
            ->replace(['_', '-', '.'], ' ')
            ->squish()
            ->lower()
            ->ucfirst()
            ->toString();
    }

    private function eventCategory(?string $module, string $action): string
    {
        $terms = Str::lower(($module ?? '').' '.$action);

        return match (true) {
            Str::contains($terms, ['auth', 'login', 'password']) => 'authentication',
            Str::contains($terms, ['role', 'permission']) => 'permissions',
            Str::contains($terms, ['referral', 'assignment']) => 'referrals',
            Str::contains($terms, ['session', 'case']) => 'sessions',
            Str::contains($terms, ['announcement']) => 'announcements',
            Str::contains($terms, ['resource', 'library']) => 'resources',
            Str::contains($terms, ['backup', 'restore']) => 'backups',
            Str::contains($terms, ['report', 'export']) => 'reports',
            Str::contains($terms, ['user', 'account']) => 'users',
            default => 'system',
        };
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function formatRecord(array $record): array
    {
        /** @var CarbonImmutable $createdAt */
        $createdAt = $record['createdAt'];
        $today = CarbonImmutable::now()->startOfDay();

        $record['timeLabel'] = match (true) {
            $createdAt->greaterThanOrEqualTo($today) => $createdAt->format('h:i A'),
            $createdAt->greaterThanOrEqualTo($today->subDay()) => 'yesterday',
            default => $createdAt->format('M j, Y'),
        };
        $record['timestampTitle'] = $createdAt->format('F j, Y \a\t h:i A');
        $record['timestampIso'] = $createdAt->toIso8601String();

        return $record;
    }

    private function emptyPaginator(Request $request, int $perPage): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, $perPage, 1, [
            'path' => route('admin.audit-logs'),
            'query' => $request->query(),
        ]);
    }
}
