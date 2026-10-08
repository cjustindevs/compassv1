<?php

namespace App\Http\Controllers\Helper;

use App\Http\Controllers\Controller;
use App\Models\AdviserFeedback;
use App\Models\Helper;
use App\Models\HelperCompetencyHistory;
use App\Models\HelpSeekerEvaluation;
use App\Services\SupportAudit;
use Illuminate\View\View;
use Illuminate\Http\Request;
use App\Services\HelperViewDateRange;

class HelperCompetencyController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->role==='helper' && $request->user()->is_active,403);
        $helper=$request->user()->helper;
        abort_unless($helper,403);
        $range=app(HelperViewDateRange::class)->apply($request);
        $latest=$helper->latestCompetency;
        $query=HelperCompetencyHistory::where('helper_id',$helper->id)->whereBetween('evaluation_date',[$range['start'],$range['end']]);
        $history=(clone $query)->with('adviser')->orderByDesc('evaluation_date')->orderByDesc('id')->paginate(10)->withQueryString();
        $trend=(clone $query)->orderByDesc('evaluation_date')->orderByDesc('id')->limit(6)->get()->reverse()->values()
            ->map(fn($record)=>['label'=>$record->evaluation_date->copy()->timezone('Asia/Manila')->format('M d, Y'),'score'=>round($record->normalized_score,1)]);
        return view('helper.competency',['latest'=>$latest,'history'=>$history,'trend'=>$trend,'totalEvaluations'=>$history->total(),'range'=>$range]);
    }

    public function show(int $id): View
    {
        abort_unless(auth()->user()?->role === 'helper' && auth()->user()?->is_active, 403);
        $helper = Helper::where('user_account_id', auth()->id())->firstOrFail();

        $evaluation = HelperCompetencyHistory::with('adviser')
            ->where('helper_id', $helper->id)
            ->findOrFail($id);

        return view('helper.competency-detail', compact('evaluation'));
    }

    public function feedback(Request $request): View
    {
        abort_unless($request->user()?->role==='helper' && $request->user()->is_active,403);
        $helper=Helper::where('user_account_id',auth()->id())->firstOrFail();
        $range=app(HelperViewDateRange::class)->apply($request);
        $request->validate(['adviser_search'=>'nullable|string|max:100','seeker_search'=>'nullable|string|max:100']);
        $adviserFeedback=AdviserFeedback::with(['adviser','report.session'])
            ->whereHas('report.session',fn($q)=>$q->where('helper_id',$helper->id))
            ->whereBetween(\Illuminate\Support\Facades\DB::raw('COALESCE(created_date,created_at)'),[$range['start'],$range['end']])
            ->when($request->filled('adviser_search'),fn($q)=>$q->where('feedback_text','like','%'.$request->input('adviser_search').'%'))
            ->orderByDesc('created_date')->orderByDesc('id')->paginate(10,['*'],'adviser_page')->withQueryString();
        $seekerFeedback=HelpSeekerEvaluation::with('session')
            ->whereHas('session',fn($q)=>$q->where('helper_id',$helper->id))
            ->whereBetween(\Illuminate\Support\Facades\DB::raw('COALESCE(submitted_at,created_at)'),[$range['start'],$range['end']])
            ->when($request->filled('seeker_search'),fn($q)=>$q->where('comments','like','%'.$request->input('seeker_search').'%'))
            ->latest()->paginate(10,['*'],'seeker_page')->withQueryString();
        return view('helper.feedback',compact('helper','adviserFeedback','seekerFeedback','range'));
    }

    public function acknowledgeFeedback(int $id)
    {
        abort_unless(auth()->user()?->role === 'helper' && auth()->user()?->is_active, 403);
        $feedback = AdviserFeedback::whereHas('report.session', fn ($q) => $q->where('helper_id', auth()->user()->helper->id))->findOrFail($id);
        if (! $feedback->acknowledged_at) {
            $feedback->update(['acknowledged_at' => now()]);
            SupportAudit::record('training_feedback_acknowledged', $feedback);
        }

        return back()->with('success', 'Feedback acknowledged. Training completion remains subject to adviser verification.');
    }

    public function feedbackShow(int $id): View
    {
        abort_unless(auth()->user()?->role === 'helper' && auth()->user()?->is_active, 403);
        $helper = Helper::where('user_account_id', auth()->id())->firstOrFail();

        $feedback = AdviserFeedback::with(['adviser', 'report.session'])
            ->whereHas('report.session', fn ($query) => $query->where('helper_id', $helper->id))
            ->findOrFail($id);

        return view('helper.feedback-detail', compact('feedback'));
    }
}
