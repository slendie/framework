@foreach($editions as $edition)
@foreach($edition['dates'] as $i => $date)
<p>{{ $edition['name'] }} - {{ $i }} - {{ $date }}</p>
@endforeach
@endforeach
