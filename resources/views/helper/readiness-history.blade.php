@extends('layouts.helper')

@section('title', 'Readiness History')

@section('heading', 'Readiness History')
@section('subheading', 'Your past check-ins.')

@section('content')

    <div class="card">
        <div class="card-header">
            <h3>Past Check-ins</h3>
            <a href="{{ route('helper.readiness') }}" class="link"><i class="fas fa-plus"></i> New check-in</a>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Result</th>
                        <th>Availability</th>
                        <th>Stress Level</th>
                        <th>Emotionally Ready</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($checks as $check)
                        <tr>
                            <td>{{ $check->assessment_date?->format('M d, Y h:i A') }}</td>
                            <td><span class="status-badge {{ $check->assessment_result === 'ready' ? 'completed' : 'cancelled' }}">{{ $check->result_label }}</span></td>
                            <td>{{ ucfirst($check->availability_status ?? 'available') }}</td>
                            <td>{{ ucfirst($check->stress_level ?? 'low') }}</td>
                            <td>{{ $check->emotionally_ready ? 'Yes' : 'No' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-6 text-gray-400">No check-ins yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($checks->hasPages())
            <div class="mt-4">
                {{ $checks->links() }}
            </div>
        @endif
    </div>

@endsection