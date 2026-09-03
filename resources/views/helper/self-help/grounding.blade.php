@extends('layouts.helper')

@section('title', 'Grounding Exercise')
@section('heading', 'Grounding Exercise')
@section('subheading', 'The 5-4-3-2-1 technique to bring you back to the present.')

@section('styles')
    <style>
        .ground-step {
            background: white; border: 1px solid var(--gray-200); border-radius: 16px;
            padding: 20px; margin-bottom: 12px; text-align: left;
            position: relative; overflow: hidden;
        }
        .ground-step .step-num {
            width: 34px; height: 34px; border-radius: 50%;
            background: var(--green-500); color: white; font-weight: 700;
            display: inline-flex; align-items: center; justify-content: center; margin-right: 10px;
        }
        .ground-step h4 { font-size: 16px; font-weight: 700; color: var(--gray-800); display: inline-block; vertical-align: middle; }
        .ground-step p { font-size: 13px; color: var(--gray-500); margin-top: 6px; }
        .ground-step.completed { background: var(--green-50); border-color: var(--green-200); }
        .ground-step.completed::after {
            content: '✓'; position: absolute; right: 16px; top: 50%; transform: translateY(-50%);
            font-size: 28px; color: var(--green-600); font-weight: 800;
        }
    </style>
@endsection

@section('content')
    <div class="card" style="max-width:560px;margin:0 auto;">
        <p class="text-sm text-gray-500 mb-4">Work through each step out loud or in your head. Tap each card to mark it complete.</p>

        <div class="ground-step" data-step="1">
            <span class="step-num">5</span><h4>Things you can see</h4>
            <p>Look around and name 5 things you can see right now.</p>
        </div>
        <div class="ground-step" data-step="2">
            <span class="step-num">4</span><h4>Things you can touch</h4>
            <p>Notice 4 things you can feel — the chair, your clothes, the air.</p>
        </div>
        <div class="ground-step" data-step="3">
            <span class="step-num">3</span><h4>Things you can hear</h4>
            <p>Listen and identify 3 sounds around you.</p>
        </div>
        <div class="ground-step" data-step="4">
            <span class="step-num">2</span><h4>Things you can smell</h4>
            <p>Acknowledge 2 smells — or 2 you can imagine.</p>
        </div>
        <div class="ground-step" data-step="5">
            <span class="step-num">1</span><h4>Thing you can taste</h4>
            <p>Notice 1 thing you can taste — or take a sip of water.</p>
        </div>

        <div class="text-center mt-4">
            <a href="{{ route('helper.self-help') }}" class="btn btn-secondary" style="width:100%;">
                <i class="fas fa-arrow-left mr-1"></i> Back to Self-Care
            </a>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.ground-step').forEach(function (step) {
                step.addEventListener('click', function () {
                    this.classList.toggle('completed');
                });
            });
        });
    </script>
@endsection
