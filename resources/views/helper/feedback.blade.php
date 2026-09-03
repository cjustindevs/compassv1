@extends('layouts.helper')

@section('title', 'Feedback')
@section('heading', 'Feedback')
@section('subheading', 'Adviser feedback and help-seeker evaluations from your peer-support sessions.')

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="card">
            <div class="card-header">
                <h3>Adviser Feedback</h3>
                <span class="text-xs text-gray-400">{{ $adviserFeedback->count() }} item(s)</span>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Rating</th>
                            <th>Level</th>
                            <th>Feedback</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($adviserFeedback as $feedback)
                            <tr>
                                <td>{{ $feedback->created_date?->format('M d, Y') ?? $feedback->created_at?->format('M d, Y') }}</td>
                                <td><strong>{{ $feedback->competency_rating ?? 'N/A' }}</strong></td>
                                <td><span class="pill">{{ ucfirst($feedback->competency_level ?? 'Pending') }}</span></td>
                                <td>
                                    <a href="{{ route('helper.feedback.view', ['id' => $feedback->id]) }}" class="link">
                                        {{ Illuminate\Support\Str::limit($feedback->feedback_text ?: 'View feedback', 60) }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center py-6 text-gray-400">No adviser feedback yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Help-Seeker Feedback</h3>
                <span class="text-xs text-gray-400">{{ $seekerFeedback->count() }} evaluation(s)</span>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Session</th>
                            <th>Alias</th>
                            <th>Score</th>
                            <th>Comments</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($seekerFeedback as $evaluation)
                            <tr>
                                <td>{{ $evaluation->session?->reference_number ?? 'Session #' . $evaluation->session_id }}</td>
                                <td>{{ $evaluation->session?->seeker?->generated_alias ?? 'Anonymous' }}</td>
                                <td><strong class="text-green-600">{{ $evaluation->overall_score ?? 'N/A' }}</strong></td>
                                <td class="text-sm text-gray-500">{{ Illuminate\Support\Str::limit($evaluation->comments ?: 'No comments', 60) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center py-6 text-gray-400">No help-seeker feedback yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
