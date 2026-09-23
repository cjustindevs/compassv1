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

        $resources = $query->latest()->paginate(15)->withQueryString();

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

        $hotlines = \App\Models\EmergencyResource::orderBy('agency_name')->get();
        return view('adviser.resources', compact('resources', 'categories', 'stats', 'hotlines'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        app(\App\Services\AdviserResourceService::class)->save(new SelfHelpResource,[
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'],
            'content' => $validated['content'] ?? null,
            'icon' => $validated['icon'] ?? 'fa-file-lines',
            'duration' => $validated['duration'] ?? null,
            'difficulty' => $validated['difficulty'] ?? 'beginner',
            'tags' => !empty($validated['tags']) ? array_map('trim', explode(',', $validated['tags'])) : null,
            'is_featured' => $request->boolean('is_featured'),
            'is_published' => $request->boolean('is_published'),
            'visibility' => $validated['visibility'] ?? 'public', 'review_date' => $validated['review_date'] ?? null,
        ], $request->input('change_reason') ?: 'Resource content or publication updated');

        return redirect()->route('adviser.resources')->with('success', 'Resource created successfully.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $resource = SelfHelpResource::findOrFail($id);

        $validated = $request->validate($this->rules());

        app(\App\Services\AdviserResourceService::class)->save($resource,[
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
            'visibility' => $validated['visibility'] ?? 'public', 'review_date' => $validated['review_date'] ?? null,
        ], $request->input('change_reason') ?: 'Resource content or publication updated');

        return redirect()->route('adviser.resources')->with('success', 'Resource updated successfully.');
    }

    public function destroy(int $id): RedirectResponse
    {
        app(\App\Services\AdviserResourceService::class)->save(SelfHelpResource::findOrFail($id),['is_published'=>false,'archived_at'=>now()],'Resource archived; previous versions retained');

        return redirect()->route('adviser.resources')->with('success', 'Resource archived; previous versions retained.');
    }

    public function saveEmergency(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id' => 'nullable|integer|exists:emergency_resources,id',
            'agency_name' => 'required|string|max:255', 'hotline' => ['required', 'string', 'max:50', 'regex:/^[+0-9() .-]+$/'],
            'description' => 'nullable|string|max:1000', 'status' => 'required|in:active,inactive',
            'visibility'=>'nullable|in:public,internal','review_date'=>'nullable|date',
        ]);
        $resource = isset($data['id']) ? \App\Models\EmergencyResource::findOrFail($data['id']) : new \App\Models\EmergencyResource();
        unset($data['id']);
        app(\App\Services\AdviserResourceService::class)->save($resource,$data,'Emergency directory entry reviewed and updated');
        \App\Models\AuditLog::create(['user_account_id' => auth()->id(), 'module' => 'adviser',
            'action' => 'emergency_resource_saved', 'description' => 'Emergency resource #'.$resource->id.' updated.']);
        return redirect()->route('adviser.resources')->with('success', 'Emergency information saved.');
    }

    private function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'visibility'=>'nullable|in:public,internal', 'review_date'=>'nullable|date', 'change_reason'=>'nullable|string|max:1000',
            'description' => 'nullable|string|max:2000',
            'category' => 'required|string|max:100',
            'content' => 'nullable|string|max:30000',
            'icon' => 'nullable|string|max:50',
            'duration' => 'nullable|string|max:50',
            'difficulty' => 'nullable|in:beginner,intermediate,advanced',
            'tags' => 'nullable|string|max:255',
            'is_featured' => 'nullable|boolean',
            'is_published' => 'nullable|boolean',
        ];
    }
}
