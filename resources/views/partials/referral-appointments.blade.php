@if($referral->help_seeker_consent && $referral->approved_at)
<div class="bg-white border border-gray-200 rounded-2xl p-5 my-4">
    <h3 class="font-semibold text-gray-800">Professional appointments</h3>
    <p class="text-sm text-gray-500 mb-3">All times are Philippine Time (Asia/Manila).</p>
    @forelse($referral->appointments as $appointment)
        <div class="border-t border-gray-100 py-3">
            <p class="text-sm">Meeting format: {{ $appointment->meeting_format ? ucwords(str_replace('_',' ', $appointment->meeting_format)) : 'See meeting instructions' }}</p>
            <p class="text-sm">Seeker response: {{ ucwords(str_replace('_',' ', $appointment->seeker_response ?? 'Awaiting confirmation')) }}</p>
            @if(auth()->user()->role === 'seeker' && $appointment->status === 'scheduled' && $appointment->starts_at->isFuture() && in_array($referral->status,['accepted','in_progress']))
                <form method="POST" action="{{ route('seeker.appointment.respond',$appointment) }}" class="flex flex-wrap gap-2 my-3">
                    @csrf
                    <button name="decision" value="confirmed" class="bg-green-700 text-white rounded-lg px-3 py-2" @disabled($appointment->seeker_response === 'confirmed')>{{ $appointment->seeker_response === 'confirmed' ? 'Confirmed' : 'Confirm appointment' }}</button>
                    <button name="decision" value="reschedule_requested" class="border rounded-lg px-3 py-2" @disabled($appointment->seeker_response === 'reschedule_requested')>Request another schedule</button>
                </form>
            @endif

            @if(auth()->user()->role === 'seeker')
                <button type="button" class="text-green-700 underline text-sm" onclick="document.getElementById('appointment-{{ $appointment->id }}').showModal()">View appointment details</button>
                <dialog id="appointment-{{ $appointment->id }}" aria-labelledby="appointment-title-{{ $appointment->id }}" class="m-auto rounded-2xl border border-gray-200 p-6 w-full max-w-lg backdrop:bg-black/40">
                    <h3 id="appointment-title-{{ $appointment->id }}" class="font-bold text-lg">Referral appointment</h3>
                    <p class="text-sm my-3">{{ $appointment->starts_at->timezone('Asia/Manila')->format('M d, Y g:i A') }} to {{ $appointment->ends_at->timezone('Asia/Manila')->format('g:i A') }} (Asia/Manila)</p>
                    <p class="text-sm whitespace-pre-wrap">{{ $appointment->meeting_details }}</p>
                    <p class="text-sm mt-2">Status: {{ ucfirst($appointment->status) }}</p>
                    <form method="dialog" class="mt-4"><button class="bg-green-600 text-white rounded-lg px-4 py-2">Close</button></form>
                </dialog>
                @if((string)request('appointment') === (string)$appointment->id)
                    <script>document.addEventListener('DOMContentLoaded',()=>document.getElementById('appointment-{{ $appointment->id }}').showModal());</script>
                @endif
            @endif
            <span class="text-sm font-semibold">{{ $appointment->starts_at->timezone('Asia/Manila')->format('M d, Y g:i A') }} to {{ $appointment->ends_at->timezone('Asia/Manila')->format('g:i A') }}</span>
            <span class="text-xs text-green-700">{{ ucfirst($appointment->status) }}</span>
            <p class="text-sm whitespace-pre-wrap mt-2">{{ $appointment->meeting_details }}</p>
        </div>
    @empty
        <p class="text-sm text-gray-500">No appointment has been scheduled.</p>
    @endforelse
    @if(auth()->user()->role === 'professional' && in_array($referral->status,['accepted','in_progress']))
        <form method="POST" action="{{ route('professional.referral.appointment',$referral) }}" class="grid sm:grid-cols-2 gap-3 mt-4">
            @csrf
            <label class="text-sm">Meeting format<select name="meeting_format" required class="block w-full border rounded-lg p-2"><option value="in_person">In person</option><option value="video">Video call</option><option value="phone">Phone call</option></select></label>
            <label class="text-sm">Start (Asia/Manila)<input type="datetime-local" name="starts_at" required class="block w-full border rounded-lg p-2" value="{{ old('starts_at') }}"></label>
            <label class="text-sm">End (Asia/Manila)<input type="datetime-local" name="ends_at" required class="block w-full border rounded-lg p-2" value="{{ old('ends_at') }}"></label>
            <label class="text-sm sm:col-span-2">Meeting instructions<textarea name="meeting_details" required maxlength="2000" class="block w-full border rounded-lg p-2" placeholder="Meeting location or secure meeting instructions. Do not include clinical notes.">{{ old('meeting_details') }}</textarea></label>
            @if($errors->any())<p role="alert" class="text-red-700 text-sm sm:col-span-2">{{ $errors->first() }}</p>@endif
            <button class="bg-green-600 text-white rounded-lg px-4 py-2" type="submit">Save appointment</button>
            <p class="text-xs text-gray-500">Saving a new time preserves the previous appointment as rescheduled.</p>
        </form>
    @endif
</div>
@endif
