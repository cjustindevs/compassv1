<section class="card mb-4 p-4" data-duty-summary data-duty-url="{{ route('helper.readiness.status') }}">
    <div class="helper-duty-sections">
        <div><h2 class="font-semibold">Today’s duty</h2><div data-duty-today>
            @forelse($dutySummary['today'] as $duty)<article class="helper-duty-item"><strong>{{ $duty['status'] }}</strong><p>{{ $duty['date'] }} · {{ $duty['time'] }}</p>@if($duty['notes'])<small>{{ $duty['notes'] }}</small>@endif</article>
            @empty<p>No duty scheduled today. Contact your Moderator.</p>@endforelse
        </div></div>
        <div><h2 class="font-semibold">Upcoming duties</h2><div data-duty-upcoming>
            @forelse($dutySummary['upcoming'] as $duty)<article class="helper-duty-item"><strong>{{ $duty['date'] }}</strong><p>{{ $duty['time'] }}</p>@if($duty['notes'])<small>{{ $duty['notes'] }}</small>@endif</article>
            @empty<p>No upcoming duty scheduled.</p>@endforelse
        </div></div>
    </div>
    <p class="helper-duty-note" data-duty-note>{{ $dutySummary['testing'] ? 'Testing: today’s duty applies at any clock time. Passed readiness is still required.' : 'Complete readiness before your shift. Availability depends on your duty, readiness, and current assignment.' }}</p>
</section>
<style>.helper-duty-sections{display:grid;grid-template-columns:1fr 1fr;gap:18px}.helper-duty-sections h2{font-size:16px;margin-bottom:10px}.helper-duty-item{border:1px solid #dfe8e2;border-radius:10px;padding:10px 12px;margin-bottom:8px;font-size:13px}.helper-duty-item p{margin:4px 0;color:#64748b}.helper-duty-item small{display:block;overflow-wrap:anywhere}.helper-duty-note{font-size:12px;color:#64748b;margin-top:10px}@media(max-width:700px){.helper-duty-sections{grid-template-columns:1fr}}</style>
<script src="{{ asset('js/helper-duty.js') }}?v={{ filemtime(public_path('js/helper-duty.js')) }}" defer></script>
