@extends('layouts.helper')

@section('title', 'Breathing Exercise')
@section('heading', 'Breathing Exercise')
@section('subheading', 'A 4-4-4 breathing session to help you relax and refocus.')

@section('styles')
    <style>
        .breath-circle {
            width: 180px; height: 180px; border-radius: 50%;
            background: linear-gradient(135deg, #EAF8F0, #D0F0D8);
            margin: 24px auto; display: flex; align-items: center; justify-content: center;
            font-size: 20px; font-weight: 700; color: var(--green-700);
            transition: transform 1s ease, background 0.5s ease, border-color 0.5s ease;
            border: 4px solid #04A052;
        }
        .breath-circle.inhale { transform: scale(1.35); background: linear-gradient(135deg,#D0F0D8,#A8E4BC); border-color:#16A34A; }
        .breath-circle.hold { transform: scale(1.35); background: linear-gradient(135deg,#FEF3C7,#FDE68A); border-color:#F59E0B; color:#B45309; }
        .breath-circle.exhale { transform: scale(0.8); background: linear-gradient(135deg,#DBEAFE,#93C5FD); border-color:#3B82F6; color:#1D4ED8; }
        #breath-text { min-height: 30px; font-weight: 700; font-size: 18px; color: var(--gray-700); }
        #breath-timer { font-size: 13px; color: var(--gray-400); }
    </style>
@endsection

@section('content')
    <div class="card" style="max-width:520px;margin:0 auto;text-align:center;">
        <p class="text-sm text-gray-500">Inhale for 4 seconds, hold for 4 seconds, exhale for 4 seconds. Repeat for 3 rounds.</p>

        <div class="breath-circle" id="breath-circle"><i class="fas fa-wind"></i></div>
        <div id="breath-text">Ready?</div>
        <p id="breath-timer">Round <span id="round-count">1</span> of 3</p>

        <div class="flex flex-col gap-3 mt-4">
            <button type="button" class="btn btn-primary" id="begin-btn" style="width:100%;">
                <i class="fas fa-play mr-1"></i> Begin
            </button>
            <button type="button" class="btn btn-primary" id="reset-btn" style="width:100%;display:none;background:var(--gray-500);">
                <i class="fas fa-redo mr-1"></i> Do Again
            </button>
            <a href="{{ route('helper.self-help') }}" class="btn btn-secondary" style="width:100%;">
                <i class="fas fa-arrow-left mr-1"></i> Back to Self-Care
            </a>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const circle = document.getElementById('breath-circle');
            const text = document.getElementById('breath-text');
            const roundEl = document.getElementById('round-count');
            const beginBtn = document.getElementById('begin-btn');
            const resetBtn = document.getElementById('reset-btn');
            let timer = null;

            function clear() { if (timer) { clearTimeout(timer); timer = null; } }

            function done() {
                circle.className = 'breath-circle';
                text.textContent = 'Great job! 🎉';
                beginBtn.style.display = 'none';
                resetBtn.style.display = '';
            }

            function run() {
                clear();
                beginBtn.style.display = 'none';
                resetBtn.style.display = 'none';
                let round = 1;
                roundEl.textContent = String(round);

                const phase = (label, cls, ms, next) => {
                    text.textContent = label;
                    circle.className = 'breath-circle ' + cls;
                    timer = setTimeout(next, ms);
                };

                const cycle = () => {
                    if (round > 3) { done(); return; }
                    roundEl.textContent = String(round);
                    phase('Inhale…', 'inhale', 4000, () => {
                        phase('Hold…', 'hold', 4000, () => {
                            phase('Exhale…', 'exhale', 4000, () => {
                                round += 1;
                                cycle();
                            });
                        });
                    });
                };

                cycle();
            }

            beginBtn.addEventListener('click', run);
            resetBtn.addEventListener('click', function () {
                text.textContent = 'Ready?';
                circle.className = 'breath-circle';
                beginBtn.style.display = '';
                resetBtn.style.display = 'none';
                roundEl.textContent = '1';
                clear();
            });
        });
    </script>
@endsection
