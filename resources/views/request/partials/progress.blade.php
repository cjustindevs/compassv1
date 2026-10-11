<nav class="request-progress" aria-label="Request progress">
    @foreach([1=>'Screening',2=>'Review request',3=>'Matching'] as $number=>$label)
        <span @if($number === $currentStep) aria-current="step" @endif class="{{ $number === $currentStep ? 'current' : ($number < $currentStep ? 'complete' : '') }}">
            <b>{{ $number }}</b> {{ $label }} @if($number < $currentStep)<span class="sr-only">completed</span>@endif
        </span>
    @endforeach
</nav>
