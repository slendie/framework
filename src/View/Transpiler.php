<?php

namespace Slendie\Framework\View;

class Transpiler
{
    const ANY_CONDITION = '[^@]{1,}';
    const ANY_ASSET = '[\w\-\.\/]{1,}';
    const ANY_ROUTE = '[\w\-\.]{1,}';
    const ANY_ROUTE_PARAM = '[^\)]*';

    const START_BLOCK = '/@{command}[\s]*\((' . self::ANY_CONDITION . ')\)/';
    const END_BLOCK = '/@{command}/';

    const ELSEIF_PATTERN = '/@elseif[\s]*\((' . self::ANY_CONDITION . ')\)/';
    const ELSE_PATTERN = '/@else/';

    const PHP_PATTERN = '/@php[\s]*\((' . self::ANY_CONDITION . ')\)/';
    const PHP_START_BLOCK_PATTERN = '/@php/';
    const PHP_END_BLOCK_PATTERN = '/@endphp/';

    const ECHO_PATTERN = '/{{ (.+) }}/';

    const ASSET_PATTERN = '/@asset\([\s]*\'(' . self::ANY_ASSET . ')\'[\s]*\)/';
    const ROUTE_PATTERN = '/@route\([\s]*\'(' . self::ANY_ROUTE . ')\'[,\s]*(' . self::ANY_ROUTE_PARAM . ')\)/';

    protected $content = '';

    public function __construct( string $content )
    {
        $this->content = $content;
    }

    public function parse()
    {
        $this->parseIf();
        $this->parseElseIf();
        $this->parseElse();
        $this->parseEndif();

        /* Foreach must be prior of For parser! */
        $this->parseForEach();
        $this->parseEndforEach();

        $this->parseFor();
        $this->parseEndfor();

        $this->parseWhile();
        $this->parseEndWhile();

        /* PHP must be prior or PHP Block parser! */
        $this->parsePhp();
        $this->parsePhpStartBlock();
        $this->parsePhpEndBlock();

        $this->parseAsset();
        $this->parseRoute();

        $this->parseEcho();
    }

    public function parseStartBlock( $command )
    {
        $startblock_pattern = str_replace( '{command}', $command, self::START_BLOCK );

        /* Check content */
        preg_match_all( $startblock_pattern, $this->content, $matches );

        foreach( $matches[0] as $i => $match) {
            $transpiled = '<?php ' . $command . ' (' . $matches[1][$i] . ') { ?>';
            $this->content = str_replace( $match, $transpiled, $this->content );
        }
    }

    public function parseEndBlock( $command )
    {
        $endblock_pattern = str_replace( '{command}', $command, self::END_BLOCK );

        /* Check content */
        preg_match_all( $endblock_pattern, $this->content, $matches );

        foreach( $matches[0] as $i => $match) {
            $transpiled = '<?php } ?>';
            $this->content = str_replace( $match, $transpiled, $this->content );
        }
    }

    public function parseIf()
    {
        $this->parseStartBlock('if');
    }

    public function parseElseIf()
    {
        /* Check content */
        preg_match_all( self::ELSEIF_PATTERN, $this->content, $matches );

        foreach( $matches[0] as $i => $match) {
            $transpiled = '<?php } elseif (' . $matches[1][$i] . ') { ?>';
            $this->content = str_replace( $match, $transpiled, $this->content );
        }
    }

    public function parseElse()
    {
        /* Check content */
        preg_match_all( self::ELSE_PATTERN, $this->content, $matches );

        foreach( $matches[0] as $i => $match) {
            $transpiled = '<?php } else { ?>';
            $this->content = str_replace( $match, $transpiled, $this->content );
        }
    }

    public function parseEndif()
    {
        $this->parseEndBlock('endif');
    }

    public function parseFor()
    {
        $this->parseStartBlock('for');
    }

    public function parseEndfor()
    {
        $this->parseEndBlock('endfor');
    }

    public function parseForEach()
    {
        $this->parseStartBlock('foreach');
    }

    public function parseEndforEach()
    {
        $this->parseEndBlock('endforeach');
    }

    public function parseWhile()
    {
        $this->parseStartBlock('while');
    }

    public function parseEndWhile()
    {
        $this->parseEndBlock('endwhile');
    }

    public function parsePhp()
    {
        /* Check content */
        preg_match_all( self::PHP_PATTERN, $this->content, $matches );

        foreach( $matches[0] as $i => $match) {
            $transpiled = '<?php ' . $matches[1][$i] . ' ?>';
            $this->content = str_replace( $match, $transpiled, $this->content );
        }
    }

    public function parsePhpStartBlock()
    {
        /* Check content */
        preg_match_all( self::PHP_START_BLOCK_PATTERN, $this->content, $matches );

        foreach( $matches[0] as $i => $match) {
            $transpiled = '<?php';
            $this->content = str_replace( $match, $transpiled, $this->content );
        }
    }

    public function parsePhpEndBlock()
    {
        /* Check content */
        preg_match_all( self::PHP_END_BLOCK_PATTERN, $this->content, $matches );

        foreach( $matches[0] as $i => $match) {
            $transpiled = ' ?>';
            $this->content = str_replace( $match, $transpiled, $this->content );
        }
    }

    public function parseEcho()
    {
        /* Check content */
        preg_match_all( self::ECHO_PATTERN, $this->content, $matches );

        foreach( $matches[0] as $i => $match) {
            $transpiled = '<?php echo ' . $matches[1][$i] . '; ?>';
            $this->content = str_replace( $match, $transpiled, $this->content );
        }
    }

    public function parseAsset()
    {
        /* Check content */
        preg_match_all( self::ASSET_PATTERN, $this->content, $matches );

        foreach( $matches[0] as $i => $match) {
            $transpiled = env('APP_URL') . '/' . $matches[1][$i];
            $this->content = str_replace( $match, $transpiled, $this->content );
        }
    }

    public function parseRoute()
    {
        /* Check content */
        preg_match_all( self::ROUTE_PATTERN, $this->content, $matches );

        foreach( $matches[0] as $i => $match) {
            if ( !empty($matches[2][$i]) ) {
                $params = eval( 'return ' . $matches[2][$i] . ';' );
                $transpiled = route($matches[1][$i], $params );
            } else {
                $transpiled = route($matches[1][$i]);
            }
            $this->content = str_replace( $match, $transpiled, $this->content );
        }
    }

   public function getContent()
    {
        return $this->content;
    }
}