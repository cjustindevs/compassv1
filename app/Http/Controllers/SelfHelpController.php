<?php

namespace App\Http\Controllers;

use App\Models\SelfHelpResource;
use App\Models\UserResourceProgress;
use App\Models\UserSavedResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SelfHelpController extends Controller
{
    public const CATEGORIES = [
        'meditation' => ['label' => 'Meditation', 'icon' => '🧘', 'color' => 'purple'],
        'exercise' => ['label' => 'Exercises', 'icon' => '🌬️', 'color' => 'blue'],
        'article' => ['label' => 'Articles', 'icon' => '📖', 'color' => 'green'],
        'tool' => ['label' => 'Tools', 'icon' => '🧰', 'color' => 'amber'],
        'video' => ['label' => 'Videos', 'icon' => '🎬', 'color' => 'red'],
    ];

    /**
     * Self-help dashboard: featured, categories, saved, continue, search
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $search = trim((string) $request->query('q'));

        $query = SelfHelpResource::query()->published();

        if ($search !== '') {
            $query->search($search);
        }

        $featured = (clone $query)->featured()->orderByDesc('views_count')->limit(6)->get();
        $all = (clone $query)->latest()->get();

        if ($search === '') {
            $savedIds = $user->savedResources()->pluck('resource_id')->all();

            $savedResources = $user->savedResources()
                ->with('resource')
                ->get()
                ->map(fn ($saved) => $saved->resource)
                ->filter()
                ->take(8);

            $continueProgress = $user->resourceProgress()
                ->with('resource')
                ->where('progress_percentage', '<', 100)
                ->orderByDesc('last_accessed')
                ->get()
                ->filter(fn ($p) => $p->resource?->is_published)
                ->take(5);
        } else {
            $savedIds = [];
            $savedResources = collect();
            $continueProgress = collect();
        }

        $categories = collect(static::CATEGORIES)->map(function ($meta, $slug) {
            return [
                'slug' => $slug,
                'label' => $meta['label'],
                'icon' => $meta['icon'],
                'color' => $meta['color'],
                'count' => SelfHelpResource::where('category', $slug)->where('is_published', true)->count(),
            ];
        });

        return view('selfhelp.index', compact(
            'featured',
            'all',
            'categories',
            'savedResources',
            'savedIds',
            'continueProgress',
            'search'
        ));
    }

    /**
     * Resource detail page with save / progress / complete actions
     */
    public function show(Request $request, int $id): View
    {
        $resource = SelfHelpResource::published()->findOrFail($id);

        $resource->increment('views_count');

        $user = Auth::user();

        $isSaved = UserSavedResource::where('user_id', $user->id)
            ->where('resource_id', $resource->id)
            ->exists();

        $progress = UserResourceProgress::firstOrCreate(
            ['user_id' => $user->id, 'resource_id' => $resource->id],
            ['progress_percentage' => 0]
        );
        $progress->update(['last_accessed' => now()]);

        $tag = $resource->tags_list[0] ?? null;

        $related = SelfHelpResource::published()
            ->where('id', '!=', $resource->id)
            ->where(function ($q) use ($resource, $tag) {
                $q->where('category', $resource->category);

                if ($tag) {
                    $q->orWhereJsonContains('tags', $tag);
                }
            })
            ->take(3)
            ->get();

        return view('selfhelp.resource', compact('resource', 'isSaved', 'progress', 'related'));
    }

    /**
     * Category filtered listing with search
     */
    public function category(Request $request, string $category): View
    {
        abort_unless(array_key_exists($category, static::CATEGORIES), 404);

        $meta = static::CATEGORIES[$category];

        $search = trim((string) $request->query('q'));

        $query = SelfHelpResource::published()->inCategory($category);

        if ($search !== '') {
            $query->search($search);
        }

        $resources = $query->latest()->get();

        $user = Auth::user();
        $savedIds = $user->savedResources()->pluck('resource_id')->all();

        return view('selfhelp.category', compact('category', 'meta', 'resources', 'savedIds', 'search'));
    }

    /**
     * Save a resource for later
     */
    public function save(int $id): RedirectResponse|JsonResponse
    {
        $resource = SelfHelpResource::published()->findOrFail($id);
        $user = Auth::user();

        UserSavedResource::firstOrCreate([
            'user_id' => $user->id,
            'resource_id' => $resource->id,
        ]);

        $resource->increment('saved_count');

        if (request()->expectsJson()) {
            return response()->json(['saved' => true, 'saved_count' => $resource->fresh()->saved_count]);
        }

        return back()->with('success', 'Resource saved to your collection.');
    }

    /**
     * Remove a resource from saved collection
     */
    public function unsave(int $id): RedirectResponse|JsonResponse
    {
        $resource = SelfHelpResource::published()->findOrFail($id);
        $user = Auth::user();

        $deleted = UserSavedResource::where('user_id', $user->id)
            ->where('resource_id', $resource->id)
            ->delete();

        if ($deleted && $resource->saved_count > 0) {
            $resource->decrement('saved_count');
        }

        if (request()->expectsJson()) {
            return response()->json(['saved' => false, 'saved_count' => $resource->fresh()->saved_count]);
        }

        return back()->with('success', 'Resource removed from your collection.');
    }

    /**
     * Update progress percentage for an exercise / meditation
     */
    public function updateProgress(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'percentage' => 'required|integer|min:0|max:100',
        ]);

        $resource = SelfHelpResource::published()->findOrFail($id);

        $progress = UserResourceProgress::updateOrCreate(
            ['user_id' => Auth::id(), 'resource_id' => $resource->id],
            [
                'progress_percentage' => $validated['percentage'],
                'last_accessed' => now(),
                'completed_at' => $validated['percentage'] >= 100 ? now() : null,
            ]
        );

        if (request()->expectsJson()) {
            return response()->json([
                'percentage' => $progress->progress_percentage,
                'completed' => (bool) $progress->completed_at,
            ]);
        }

        return back()->with('success', 'Progress updated (' . $validated['percentage'] . '%).');
    }

    /**
     * Mark a resource as completed
     */
    public function complete(int $id): RedirectResponse|JsonResponse
    {
        $resource = SelfHelpResource::published()->findOrFail($id);

        $progress = UserResourceProgress::updateOrCreate(
            ['user_id' => Auth::id(), 'resource_id' => $resource->id],
            [
                'progress_percentage' => 100,
                'last_accessed' => now(),
                'completed_at' => now(),
            ]
        );

        if (request()->expectsJson()) {
            return response()->json(['completed' => true]);
        }

        return back()->with('success', 'Great job! Resource marked as complete.');
    }
}