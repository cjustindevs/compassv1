<?php

namespace App\Http\Controllers;

use App\Models\SelfHelpResource;
use App\Models\UserResourceProgress;
use App\Models\UserSavedResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SelfHelpController extends Controller
{
    /**
     * Content types. Slugs are part of the `selfhelp.category` route contract,
     * so the keys must not be renamed. Presentation is text only — no icons.
     */
    public const CATEGORIES = [
        'meditation' => ['label' => 'Meditation'],
        'exercise' => ['label' => 'Exercises'],
        'article' => ['label' => 'Articles'],
        'tool' => ['label' => 'Tools'],
        'video' => ['label' => 'Videos'],
    ];

    /**
     * Support-focused sections used to group published resources on the
     * dashboard. Order is intentional: grounding techniques that calm the body
     * come first, then the broader topics.
     *
     * `terms` are matched against each resource's title and tags so resources
     * are classified from their own content. The first matching section wins,
     * and anything unclassified falls through to `more` so no resource is ever
     * hidden from the dashboard.
     */
    public const SECTIONS = [
        'breathing' => [
            'label' => 'Breathing Exercises',
            'description' => 'Slow, controlled breathing to settle a racing mind and a tense body.',
            'terms' => ['breathing', 'breath'],
        ],
        'grounding' => [
            'label' => 'Grounding Techniques',
            'description' => 'Use your senses to return to the present when things feel overwhelming.',
            'terms' => ['grounding', 'ground', 'panic'],
        ],
        'stress' => [
            'label' => 'Stress Management',
            'description' => 'Notice stress early and release the tension your body is holding.',
            'terms' => ['stress', 'burnout', 'relaxation', 'tension'],
        ],
        'mindfulness' => [
            'label' => 'Mindfulness',
            'description' => 'Short practices that build focus, presence, and calm in your day.',
            'terms' => ['mindfulness', 'mindful', 'meditation', 'meditate'],
        ],
        'journaling' => [
            'label' => 'Journaling',
            'description' => 'Write things down to make sense of your feelings and notice patterns over time.',
            'terms' => ['journal', 'tracking', 'emotions', 'writing'],
        ],
        'coping' => [
            'label' => 'Coping Strategies',
            'description' => 'Steps to take in the moment when things feel like too much.',
            'terms' => ['coping', 'crisis', 'emergency', 'urgent'],
        ],
        'sleep' => [
            'label' => 'Sleep and Rest',
            'description' => 'Build a routine that makes restful sleep easier to come by.',
            'terms' => ['sleep', 'rest', 'routine', 'health'],
        ],
        'anxiety' => [
            'label' => 'Working with Anxiety',
            'description' => 'Understand what anxiety is and find steadier ground around people and plans.',
            'terms' => ['anxiety', 'social', 'confidence', 'nervousness'],
        ],
        'more' => [
            'label' => 'More Resources',
            'description' => 'Everything else in the library, kept together so nothing gets missed.',
            'terms' => [],
        ],
    ];

    /**
     * Self-help dashboard: featured, categories, saved, continue, search
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $search = trim((string) $request->query('q'));

        $query = SelfHelpResource::query()->published();

        $categoryLabels = collect(static::CATEGORIES)
            ->map(fn ($meta) => $meta['label'])
            ->all();

        if ($search !== '') {
            $query->search($search);
        }

        $featured = (clone $query)->featured()->orderByDesc('views_count')->limit(6)->get();
        $all = (clone $query)->latest()->get();

        $featuredIds = $featured->pluck('id')->all();
        $sections = $this->groupIntoSections($all);

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
                ->filter(fn ($p) => $p->resource && SelfHelpResource::published()->whereKey($p->resource->id)->exists())
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
                'count' => SelfHelpResource::published()->where('category', $slug)->count(),
            ];
        })->filter(fn ($cat) => $cat['count'] > 0)->values();

        return view('selfhelp.index', compact(
            'all',
            'sections',
            'featuredIds',
            'categories',
            'categoryLabels',
            'savedResources',
            'savedIds',
            'continueProgress',
            'search'
        ));
    }

    /**
     * Group published resources into the text sections defined in SECTIONS.
     *
     * Classification reads the resource's own title and tags rather than
     * hard-coded IDs, so newly published resources are picked up
     * automatically. Empty sections are omitted; `more` guarantees that no
     * published resource is dropped from the dashboard.
     *
     * @param  iterable<SelfHelpResource>  $resources
     * @return array<int, array{key: string, label: string, description: string, resources: Collection}>
     */
    protected function groupIntoSections(iterable $resources): array
    {
        $buckets = [];

        foreach ($resources as $resource) {
            $buckets[$this->sectionKeyFor($resource)][] = $resource;
        }

        $sections = [];

        foreach (static::SECTIONS as $key => $meta) {
            if (empty($buckets[$key])) {
                continue;
            }

            $sections[] = [
                'key' => $key,
                'label' => $meta['label'],
                'description' => $meta['description'],
                'resources' => collect($buckets[$key])->values(),
            ];
        }

        return $sections;
    }

    /**
     * Resolve the section a resource belongs to, or `more` when nothing matches.
     */
    protected function sectionKeyFor(SelfHelpResource $resource): string
    {
        $haystack = Str::lower(implode(' ', array_filter([
            $resource->title,
            implode(' ', $resource->tags_list),
        ])));

        foreach (static::SECTIONS as $key => $meta) {
            if (empty($meta['terms'])) {
                continue;
            }

            foreach ($meta['terms'] as $term) {
                if (Str::contains($haystack, $term)) {
                    return $key;
                }
            }
        }

        return 'more';
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

        return back()->with('success', 'Progress updated ('.$validated['percentage'].'%).');
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
