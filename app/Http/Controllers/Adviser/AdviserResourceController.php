<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Models\SelfHelpResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdviserResourceController extends Controller
{
    public function index(Request $request): View
    {
        $query = SelfHelpResource::query();

        if ($request->filled('search')) {
            $query->search(trim($request->get('search')));
        }

        if ($request->filled('category') && $request->get('category') !== 'all') {
            $query->inCategory($request->get('category'));
        }

        $resources = $query->latest()->paginate(12)->withQueryString();

        $categories = SelfHelpResource::query()
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        $stats = [
            'total' => SelfHelpResource::count(),
            'published' => SelfHelpResource::published()->count(),
            'views' => SelfHelpResource::sum('views_count'),
            'featured' => SelfHelpResource::featured()->count(),
        ];

        return view('adviser.resources', compact('resources', 'categories', 'stats'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        SelfHelpResource::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'],
            'content' => $validated['content'] ?? null,
            'icon' => $validated['icon'] ?? '📄',
            'duration' => $validated['duration'] ?? null,
            'difficulty' => $validated['difficulty'] ?? 'beginner',
            'tags' => !empty($validated['tags']) ? array_map('trim', explode(',', $validated['tags'])) : null,
            'is_featured' => $request->boolean('is_featured'),
            'is_published' => $request->boolean('is_published'),
        ]);

        return redirect()->route('adviser.resources')->with('success', 'Resource created successfully.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $resource = SelfHelpResource::findOrFail($id);

        $validated = $request->validate($this->rules());

        $resource->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'],
            'content' => $validated['content'] ?? null,
            'icon' => $validated['icon'] ?? $resource->icon,
            'duration' => $validated['duration'] ?? null,
            'difficulty' => $validated['difficulty'] ?? 'beginner',
            'tags' => !empty($validated['tags']) ? array_map('trim', explode(',', $validated['tags'])) : null,
            'is_featured' => $request->boolean('is_featured'),
            'is_published' => $request->boolean('is_published'),
        ]);

        return redirect()->route('adviser.resources')->with('success', 'Resource updated successfully.');
    }

    public function destroy(int $id): RedirectResponse
    {
        SelfHelpResource::findOrFail($id)->delete();

        return redirect()->route('adviser.resources')->with('success', 'Resource deleted successfully.');
    }

    private function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|string|max:100',
            'content' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
            'duration' => 'nullable|string|max:50',
            'difficulty' => 'nullable|in:beginner,intermediate,advanced',
            'tags' => 'nullable|string|max:255',
            'is_featured' => 'nullable|boolean',
            'is_published' => 'nullable|boolean',
        ];
    }
}
