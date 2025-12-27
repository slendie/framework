@foreach( [1, 2, 3] as $item)
    <p>{{ $item }}</p>
    @if( $item == 1 )   
        <p>Item é 1</p>
    @else
        <p>Item não é 1</p>
    @endif
@endforeach
