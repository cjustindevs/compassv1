<x-guest-layout>
    <h1 class="text-xl font-semibold mb-4">Create your COMPASS account</h1>
    <p class="text-sm text-gray-600 mb-4">We will generate an alias for you to use when signing in.</p>
    @if($errors->any())<p class="text-red-700 mb-4">{{ $errors->first() }}</p>@endif
    <form method="POST" action="{{ route('seeker.onboarding.store') }}" class="form-container-compact space-y-3">
        @csrf
        <fieldset class="form-section-compact">
        <legend>About you</legend>
        <div class="form-row-compact">
        <label class="block">Age<input class="form-input block w-full rounded border-gray-300" type="number" name="age" min="13" max="99" value="{{ old('age') }}" required></label>
        <label class="block">Gender<select class="form-input block w-full rounded border-gray-300" name="gender" required>
            <option value="">Select</option>
            @foreach(['male', 'female', 'non-binary', 'prefer-not-to-say'] as $gender)
                <option value="{{ $gender }}" @selected(old('gender') === $gender)>{{ ucfirst(str_replace('-', ' ', $gender)) }}</option>
            @endforeach
        </select></label>
        </div>
        <label class="block mt-3">Language<select class="form-input block w-full rounded border-gray-300" name="preferred_language" required>
            @foreach(['English', 'Tagalog', 'English/Tagalog'] as $language)
                <option @selected(old('preferred_language') === $language)>{{ $language }}</option>
            @endforeach
        </select></label>
        </fieldset>
        <fieldset class="form-section-compact space-y-3">
        <legend>Account security</legend>
        <p class="text-sm">Use at least 8 characters, with uppercase and lowercase letters, a number, and a symbol.</p>
        <label class="block">Password<input class="form-input block w-full rounded border-gray-300" type="password" name="password" minlength="8" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^a-zA-Z0-9]).{8,}" autocomplete="new-password" required></label>
        <label class="block">Confirm password<input class="form-input block w-full rounded border-gray-300" type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required></label>
        </fieldset>
        <button class="btn-compact rounded bg-green-700 text-white px-4 py-2">Create account</button>
        <a href="{{ route('login') }}" class="ml-3 underline">Sign in</a>
    </form>
</x-guest-layout>
