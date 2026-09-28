<?php
namespace App\Http\Controllers;
use App\Models\{Referral,ReferralAppointment};
use App\Services\{ConsentService,ReferralAppointmentService};
use Illuminate\Http\Request;
class SeekerAppointmentController extends Controller {
 public function show(Request $request, Referral $referral) {
  abort_unless($request->user()->is_active && $request->user()->role === 'seeker' && $request->user()->helpSeeker?->id === $referral->session->seeker_id,403);
  return response()->view('session.support-embedded',compact('referral'))->header('Cache-Control','no-store, private');
 }
 public function respond(Request $request, ReferralAppointment $appointment) {
  $data=$request->validate(['decision'=>'required|in:confirmed,reschedule_requested']);
  app(ReferralAppointmentService::class)->respond($appointment,$data['decision']);
  return back()->with('success',$data['decision']==='confirmed' ? 'Appointment confirmed.' : 'Your request was sent. The existing appointment remains scheduled until the professional changes it.');
 }
}
