<?php

namespace App\Http\Controllers;

use App\Models\Referral;
use App\Services\ProfessionalTransferService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfessionalTransferController extends Controller
{
    public function cancel(Referral $referral)
    {
        app(ProfessionalTransferService::class)->cancel($referral);

        return back()->with('success', 'Transfer cancelled. The current Professional remains responsible.');
    }

    public function store(Request $request, Referral $referral)
    {
        $data = $request->validate(['professional_id' => 'required|integer|exists:psychology_professionals,id', 'reason' => 'required|string|min:10|max:1000']);
        app(ProfessionalTransferService::class)->request($referral, (int) $data['professional_id'], $data['reason']);

        return back()->with('success', 'Transfer requested. The current Professional remains responsible until acceptance.');
    }

    public function show(int $id)
    {
        $transfer = DB::table('referral_professional_transfers')->find($id);
        abort_unless($transfer && auth()->user()?->is_active && auth()->user()->role === 'professional' && auth()->user()->psychologyProfessional?->id === $transfer->to_professional_id, 404);

        return view('professional.transfer', compact('transfer'));
    }

    public function respond(Request $request, int $id)
    {
        $data = $request->validate(['decision' => 'required|in:accept,decline']);
        app(ProfessionalTransferService::class)->respond($id, $data['decision'] === 'accept');
        $row = DB::table('referral_professional_transfers')->find($id);

        return redirect($data['decision'] === 'accept' ? '/professional/referral/'.$row->referral_id : '/professional/referrals')->with('success', 'Transfer response recorded.');
    }
}
