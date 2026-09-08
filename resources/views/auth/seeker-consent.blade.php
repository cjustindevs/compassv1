<x-guest-layout>
    <h1 class="text-xl font-semibold mb-4">Your privacy and consent</h1>
    <p class="mb-4">Your sign-in alias is <strong>{{ auth()->user()->helpSeeker->generated_alias }}</strong>. Save it before continuing.</p>
    @if($errors->any())<p class="text-red-700 mb-4">Please accept each item to continue.</p>@endif
    <form method="POST" action="{{ route('seeker.consent.accept') }}" class="space-y-4">
        @csrf
        <label class="flex gap-2"><input type="checkbox" name="agree_privacy" value="1" required><span>I understand that COMPASS stores my account details, screening answers, chat, and feedback to provide and supervise support.</span></label>
        <label class="flex gap-2"><input type="checkbox" name="agree_terms" value="1" required><span>I understand that peer support is provided by trained volunteers under adviser supervision.</span></label>
        <label class="flex gap-2"><input type="checkbox" name="agree_emergency" value="1" required><span>I understand that safety concerns may be shared with authorized supervisors and referred for professional support.</span></label>
        <label class="flex gap-2"><input type="checkbox" name="agree_consent" value="1" required><span>I voluntarily agree to receive peer support and can stop participating at any time.</span></label>
        <button class="rounded bg-green-700 text-white px-4 py-2">Agree and continue</button>
    </form>
</x-guest-layout>
