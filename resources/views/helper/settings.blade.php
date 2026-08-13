@extends('layouts.helper')

@section('title', 'Settings')

@section('heading', 'Settings')
@section('subheading', 'Manage your notifications, appearance, and privacy.')

@section('content')

    <div class="card">
        <div class="card-header">
            <h3>Preferences</h3>
            <span class="text-xs text-gray-400">Saved to your account</span>
        </div>
        <form method="POST" action="{{ route('helper.settings.update') }}">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label class="form-label">Appearance</label>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <label class="checkbox-group border border-gray-200 rounded-xl p-4">
                        <input type="checkbox" name="dark_mode" value="1" {{ $preferences['dark_mode'] ? 'checked' : '' }}>
                        <span><span class="font-semibold text-gray-800" style="font-size:14px;">Dark mode</span><span class="block text-xs text-gray-500">Reduce glare at night</span></span>
                    </label>
                    <label class="checkbox-group border border-gray-200 rounded-xl p-4">
                        <input type="checkbox" name="high_contrast" value="1" {{ $preferences['high_contrast'] ? 'checked' : '' }}>
                        <span><span class="font-semibold text-gray-800" style="font-size:14px;">High contrast</span><span class="block text-xs text-gray-500">Improve readability</span></span>
                    </label>
                    <div class="border border-gray-200 rounded-xl p-4">
                        <label class="form-label" style="margin-bottom:4px;">Font size</label>
                        <select name="font_size" class="form-control">
                            <option value="small" {{ $preferences['font_size'] === 'small' ? 'selected' : '' }}>Small</option>
                            <option value="medium" {{ $preferences['font_size'] === 'medium' || !$preferences['font_size'] ? 'selected' : '' }}>Medium</option>
                            <option value="large" {{ $preferences['font_size'] === 'large' ? 'selected' : '' }}>Large</option>
                        </select>
                    </div>
                </div>
            </div>

            <hr class="divider">

            <div class="form-group">
                <label class="form-label">Notifications</label>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <label class="checkbox-group border border-gray-200 rounded-xl p-4">
                        <input type="checkbox" name="email_notifications" value="1" {{ $preferences['email_notifications'] ? 'checked' : '' }}>
                        <span><span class="font-semibold text-gray-800" style="font-size:14px;">Email notifications</span><span class="block text-xs text-gray-500">Receive email updates</span></span>
                    </label>
                    <label class="checkbox-group border border-gray-200 rounded-xl p-4">
                        <input type="checkbox" name="push_notifications" value="1" {{ $preferences['push_notifications'] ? 'checked' : '' }}>
                        <span><span class="font-semibold text-gray-800" style="font-size:14px;">Push notifications</span><span class="block text-xs text-gray-500">Get real-time alerts</span></span>
                    </label>
                    <label class="checkbox-group border border-gray-200 rounded-xl p-4">
                        <input type="checkbox" name="session_reminders" value="1" {{ $preferences['session_reminders'] ? 'checked' : '' }}>
                        <span><span class="font-semibold text-gray-800" style="font-size:14px;">Session reminders</span><span class="block text-xs text-gray-500">Remind me about sessions</span></span>
                    </label>
                    <div class="border border-gray-200 rounded-xl p-4">
                        <label class="form-label" style="margin-bottom:4px;">Preferred communication</label>
                        <select name="preferred_communication_mode" class="form-control">
                            <option value="chat" {{ $preferences['preferred_communication_mode'] === 'chat' ? 'selected' : '' }}>Chat</option>
                            <option value="voice" {{ $preferences['preferred_communication_mode'] === 'voice' ? 'selected' : '' }}>Voice</option>
                            <option value="both" {{ $preferences['preferred_communication_mode'] === 'both' ? 'selected' : '' }}>Both</option>
                        </select>
                    </div>
                </div>
            </div>

            <hr class="divider">

            <div class="form-group">
                <label class="form-label">Language</label>
                <input type="text" name="preferred_language" class="form-control" value="{{ $preferences['preferred_language'] ?? 'English' }}" style="max-width:280px;">
            </div>

            <hr class="divider">

            <div class="form-group">
                <label class="form-label">Privacy</label>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <label class="checkbox-group border border-gray-200 rounded-xl p-4">
                        <input type="checkbox" name="show_email" value="1" {{ $preferences['show_email'] ? 'checked' : '' }}>
                        <span><span class="font-semibold text-gray-800" style="font-size:14px;">Show email to peers</span><span class="block text-xs text-gray-500">Display your email publicly</span></span>
                    </label>
                    <label class="checkbox-group border border-gray-200 rounded-xl p-4">
                        <input type="checkbox" name="allow_data_research" value="1" {{ $preferences['allow_data_research'] ? 'checked' : '' }}>
                        <span><span class="font-semibold text-gray-800" style="font-size:14px;">Allow anonymized research use</span><span class="block text-xs text-gray-500">Contribute to studies anonymously</span></span>
                    </label>
                </div>
            </div>

            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Settings</button>
        </form>
    </div>

@endsection