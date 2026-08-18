<?php

namespace App\Http\Controllers\Professional;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfessionalProfileController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $professional = $user->psychologyProfessional;

        if (! $professional) {
            abort(403, 'No psychology professional profile found for this account.');
        }

        return view('professional.profile', compact('user', 'professional'));
    }

    public function update(Request $request)
    {
        $user = Auth::user();
        $professional = $user->psychologyProfessional;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id . ',id',
            'specialization' => 'nullable|string|max:255',
            'license_number' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:20',
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        $professional->update([
            'email' => $validated['email'],
            'specialization' => $validated['specialization'] ?? null,
            'license_number' => $validated['license_number'] ?? null,
            'phone' => $validated['phone'] ?? null,
        ]);

        return redirect()->route('professional.profile')
            ->with('success', 'Profile updated successfully.');
    }

    public function updateAvailability(Request $request)
    {
        $request->validate([
            'is_available' => 'required|boolean',
        ]);

        $professional = Auth::user()->psychologyProfessional;

        $professional->update([
            'is_available' => $request->boolean('is_available'),
        ]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'is_available' => $professional->is_available]);
        }

        return redirect()->back()
            ->with('success', 'Availability status updated.');
    }
}
