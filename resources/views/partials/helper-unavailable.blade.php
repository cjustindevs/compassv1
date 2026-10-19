<details data-unavailable-helpers>
    <summary>Unavailable Helpers and reasons</summary>
    <ul data-unavailable-list>
        @foreach($choices['unavailable'] ?? [] as $candidate)
            <li><strong>{{ $candidate['name'] }}</strong> — {{ $candidate['reason'] }}@if($candidate['readiness_passed']) <small>Readiness passed; another requirement blocks assignment.</small>@endif</li>
        @endforeach
    </ul>
</details>
