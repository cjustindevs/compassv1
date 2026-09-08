<?php

use App\Http\Controllers\ChatController;
use App\Http\Middleware\AuthenticateJwt;
use App\Models\Session;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

Route::post('/login', function (Request $request, JwtService $jwt) {
    $data = $request->validate(['login' => 'required|string|max:255', 'password' => 'required|string']);
    $email = str_contains($data['login'], '@') ? $data['login'] : strtolower($data['login']).'@compass.local';
    $user = User::where('email', $email)->where('is_active', true)->first();
    if (! $user || ! Hash::check($data['password'], $user->password)) {
        return response()->json(['message' => 'Invalid credentials.'], 401);
    }
    return response()->json(['access_token' => $jwt->issue($user), 'token_type' => 'Bearer', 'expires_in' => 3600]);
})->middleware('throttle:5,1');

Route::middleware([AuthenticateJwt::class, 'throttle:120,1'])->group(function () {
    Route::get('/user', fn (Request $request) => $request->user()->only(['id', 'name', 'role']));
    Route::get('/sessions', function (Request $request) {
        $data = $request->validate([
            'status' => 'nullable|in:active,completed,evaluated,waiting,helper_assigned',
            'date_from' => 'nullable|date', 'date_to' => 'nullable|date|after_or_equal:date_from',
            'page' => 'nullable|integer|min:1',
        ]);
        $user = $request->user();
        abort_unless(in_array($user->role, ['seeker', 'helper']), 403);
        $query = Session::query();
        if ($user->role === 'seeker') {
            abort_unless($user->helpSeeker, 403);
            $query->where('seeker_id', $user->helpSeeker->id);
        } else {
            abort_unless($user->helper, 403);
            $query->where('helper_id', $user->helper->id);
        }
        return $query->when($data['status'] ?? null, fn ($q, $status) => $q->where('session_status', $status))
            ->when($data['date_from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($data['date_to'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->select(['id', 'session_status', 'session_type', 'start_time', 'end_time', 'duration'])
            ->orderByDesc('id')->paginate(20);
    });
    Route::post('/messages', [ChatController::class, 'sendMessage']);
    Route::get('/sessions/{sessionId}/messages', [ChatController::class, 'getMessages'])->whereNumber('sessionId');
});
