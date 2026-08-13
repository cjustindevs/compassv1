<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SelfHelpResource;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class ResourceLibraryController extends Controller
{
    /**
     * Display published support resources in the shared administrator shell.
     */
    public function __invoke(Request $request): View
    {
        $loadFailed = false;

        try {
            $savedIds = $request->user()
                ->savedResources()
                ->pluck('resource_id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            $resources = SelfHelpResource::query()
                ->published()
                ->latest()
                ->get()
                ->map(fn (SelfHelpResource $resource): array => $this->toLibraryResource($resource, $savedIds));
        } catch (Throwable $exception) {
            report($exception);
            $resources = collect();
            $loadFailed = true;
        }

        return view('admin.resource-library.index', [
            'admin' => $request->user(),
            'resources' => $resources,
            'loadFailed' => $loadFailed,
            'categories' => [
                'all' => 'All',
                'mental-health' => 'Mental Health',
                'stress' => 'Stress',
                'depression' => 'Depression',
                'anxiety' => 'Anxiety',
                'meditation' => 'Meditation',
                'emergency' => 'Emergency',
            ],
            'resourceTypes' => [
                'all' => 'All resource types',
                'article' => 'Article',
                'exercise' => 'Exercise',
                'video' => 'Video',
                'contact' => 'Contact',
            ],
        ]);
    }

    /**
     * @param  array<int, int>  $savedIds
     * @return array<string, mixed>
     */
    private function toLibraryResource(SelfHelpResource $resource, array $savedIds): array
    {
        $type = match ($resource->category) {
            'article' => 'article',
            'video' => 'video',
            default => 'exercise',
        };
        $category = $this->subjectCategory($resource);
        $durationMinutes = $this->durationMinutes($resource->duration);

        return [
            'id' => (string) $resource->id,
            'title' => $resource->title,
            'description' => $resource->description ?: 'Open this curated COMPASS resource to learn more.',
            'type' => $type,
            'typeLabel' => Str::headline($type),
            'category' => $category,
            'categoryLabel' => Str::headline($category),
            'duration' => $resource->duration ?: 'Self-paced',
            'durationMinutes' => $durationMinutes,
            'bookmarked' => in_array($resource->id, $savedIds, true),
            'tone' => $this->toneFor($category),
            'icon' => match ($type) {
                'article' => 'book-open',
                'video' => 'play-circle',
                'contact' => 'phone',
                default => 'activity',
            },
            'detailUrl' => route('selfhelp.show', $resource->id),
            'saveUrl' => route('selfhelp.save', $resource->id),
            'unsaveUrl' => route('selfhelp.unsave', $resource->id),
            'searchText' => Str::lower(implode(' ', [
                $resource->title,
                $resource->description,
                Str::headline($category),
                Str::headline($type),
                implode(' ', $resource->tags_list),
            ])),
        ];
    }

    private function subjectCategory(SelfHelpResource $resource): string
    {
        $terms = Str::lower(implode(' ', [
            $resource->title,
            $resource->description,
            $resource->category,
            implode(' ', $resource->tags_list),
        ]));

        return match (true) {
            Str::contains($terms, ['emergency', 'crisis']) => 'emergency',
            Str::contains($terms, ['depression', 'depressive']) => 'depression',
            Str::contains($terms, ['anxiety', 'panic', 'grounding']) => 'anxiety',
            $resource->category === 'meditation'
                || Str::contains($terms, ['meditation', 'mindfulness', 'progressive muscle']) => 'meditation',
            Str::contains($terms, ['stress', 'burnout', 'relaxation']) => 'stress',
            default => 'mental-health',
        };
    }

    private function durationMinutes(?string $duration): ?int
    {
        if (! $duration || ! preg_match('/\d+/', $duration, $matches)) {
            return null;
        }

        return (int) $matches[0];
    }

    private function toneFor(string $category): string
    {
        return match ($category) {
            'anxiety' => 'lavender',
            'meditation' => 'mint',
            'stress' => 'sand',
            'emergency' => 'blush',
            'depression' => 'sky',
            default => 'cyan',
        };
    }
}
