    <div>
        @if( ( 3 + 4 ) = 7 )
            <p>3 + 4 = 7</p>
        @endif
        @if( $vars['APP_ENV'] == 'local' ) <p>Local</p> @elseif ( $vars['APP_ENV'] == 'production' ) <p>Production</p> @endif
    </div>
