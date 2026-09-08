<?php

namespace App\Http\Controllers\Helper;

use App\Http\Controllers\Controller;
use App\Models\SelfHelpResource;
use Illuminate\Http\Request;

class HelperResourceController extends Controller
{
    /**
     * Show the resource library — all published resources from the database.
     */
    public function index(Request $request)
    {
        $query = SelfHelpResource::query()->published();

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('search')) {
            $query->search(trim($request->search));
        }

        $resources = $query
            ->orderBy('is_featured', 'desc')
            ->orderByDesc('views_count')
            ->paginate(12)
            ->through(fn (SelfHelpResource $resource) => [
                'id' => $resource->id,
                'title' => $resource->title,
                'description' => $resource->description,
                'category' => ucfirst($resource->category),
                'category_slug' => $resource->category,
                'icon' => $resource->icon ?? '📘',
                'duration' => $resource->duration,
                'difficulty' => $resource->difficulty_label,
                'featured' => $resource->is_featured,
                'views' => (int) $resource->views_count,
            ]);

        $categories = SelfHelpResource::query()
            ->published()
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->map(fn ($c) => ucfirst($c));

        return view('helper.resources', [
            'resources' => $resources,
            'categories' => $categories,
            'activeCategory' => $request->query('category'),
            'search' => $request->query('search', ''),
        ]);
    }
}
