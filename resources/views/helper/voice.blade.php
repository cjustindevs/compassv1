@extends('layouts.helper')

@section('title', 'Voice Call')

@section('heading', 'Voice Call')
@section('subheading', 'Session ' . $session->reference_number . ' · ' . ($session->seeker->generated_alias ?? 'Seeker'))

@section('content')

    <a href="{{ route('helper.cases.show', ['id' => $session->id]) }}" class="btn btn-secondary btn-sm mb-4" style="padding:6px 14px;"><i class="fas fa-arrow-left"></i> Back to case</a>

    <div class="card" style="text-align:center;padding:48px 24px;">
        <div class="voice-avatar">
            <i class="fas fa-user"></i>
        </div>

        <div class="mt-4">
            <div class="text-2xl font-bold text-gray-800">{{ $session->seeker->generated_alias ?? 'Seeker' }}</div>
            <div class="text-sm text-gray-500 mt-1">
                <span class="risk-badge {{ $session->risk_level ?? 'low' }}">{{ ucfirst($session->risk_level ?? 'Low') }}</span>
                Voice session
            </div>
        </div>

        <div class="text-sm text-gray-500 mt-4">
            <i class="fas fa-lock mr-1"></i> End-to-end encrypted · Recording: {{ $session->voice_recording_consent ? 'On' : 'Off' }}
        </div>

        <div class="mt-8">
            <div class="call-timer" id="callTimer">0:00</div>
            <div class="text-xs text-gray-400 uppercase tracking-wide mt-1" id="callState">Idle</div>
        </div>

        <div class="call-actions">
            <button class="call-btn mute" id="muteBtn" title="Mute"><i class="fas fa-microphone"></i></button>
            <button class="call-btn" id="startBtn" title="Start call" style="background:var(--green-500);color:white;width:72px;height:72px;"><i class="fas fa-phone"></i></button>
            <button class="call-btn end danger-flash" id="endBtn" title="End call" style="display:none;"><i class="fas fa-phone-slash"></i></button>
        </div>

        <div style="max-width:420px;margin:32px auto 0;">
            @if($callLog)
                <hr class="divider">
                <div class="grid grid-cols-3 gap-4 text-center">
                    <div>
                        <div class="text-xs text-gray-400 uppercase">Started</div>
                        <div class="font-semibold text-gray-800">{{ $callLog->call_start?->format('h:i A') }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400 uppercase">Ended</div>
                        <div class="font-semibold text-gray-800">{{ $callLog->call_end?->format('h:i A') }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400 uppercase">Duration</div>
                        <div class="font-semibold text-gray-800">{{ $callLog->duration_label }}</div>
                    </div>
                </div>
            @endif
        </div>
    </div>

@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const sessionId = {{ $session->id }};
        const timerEl = document.getElementById('callTimer');
        const stateEl = document.getElementById('callState');
        const startBtn = document.getElementById('startBtn');
        const endBtn = document.getElementById('endBtn');
        const muteBtn = document.getElementById('muteBtn');

        let interval = null;
        let seconds = 0;
        let started = {{ $callLog ? 'true' : 'false' }};

        function pad(n) { return n < 10 ? '0' + n : n; }
        function tick() {
            seconds++;
            timerEl.textContent = Math.floor(seconds / 60) + ':' + pad(seconds % 60);
        }
        function startScripted() {
            if (interval) return;
            interval = setInterval(tick, 1000);
            stateEl.textContent = 'In call...';
        }
        function stop() {
            clearInterval(interval);
            interval = null;
        }

        startBtn.addEventListener('click', function () {
            fetch('/helper/session/' + sessionId + '/voice/start', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
            });
            startScripted();
            startBtn.style.display = 'none';
            endBtn.style.display = 'flex';
            started = true;
        });

        muteBtn.addEventListener('click', function () {
            this.classList.toggle('active');
            this.querySelector('i').classList.toggle('fa-microphone-slash');
        });

        endBtn.addEventListener('click', function () {
            stop();
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '/helper/session/' + sessionId + '/voice/end';
            const token = document.createElement('input');
            token.type = 'hidden';
            token.name = '_token';
            token.value = csrf;
            form.appendChild(token);
            document.body.appendChild(form);
            form.submit();
        });

        if (started) {
            startBtn.style.display = 'none';
            endBtn.style.display = 'flex';
        }
    });
</script>
@endsection