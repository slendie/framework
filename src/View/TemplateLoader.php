<?php
namespace Slendie\Framework\View;

use Slendie\Framework\Routing\Router;
use Slendie\Framework\Routing\Request;

class TemplateLoader
{
    const BREAK_LINE = ( PHP_OS == 'Linux' ? "\n" : "\r\n" );
    const NAME_PATTERN = '[A-Za-z0-9\.\-\_]{1,}';
    const WORD_PATTERN = '[^\']{1,}';
    const ANYWORD_PATTERN = '[\w\.\_\-\>\)\(\[\]\b\s\\\'\=\$]';

    protected $extended = '';
    protected $template = '';
    protected $doc = '';
    protected $sections = [];

    protected $template_file;
    protected $params;

    protected $rootpath;
    protected $path;
    protected $cache;
    protected $ext;

    /**
     * Define template and build all doc.
     * 
     * @return void
     */
    public function __construct( $template_file = null, $params = [] )
    {
        $this->template_file = $template_file;
        $this->params = $params;

        $this->rootpath = env('base_dir');
        $this->path = env('view_path');
        $this->cache = env('view_cache');
        $this->ext = env('view_extension');

        if ( !is_null( $this->template_file ) ) {
            $this->template = $this->read( $this->template_file );
        }

        $this->parseLoad();

        $this->parseMagicFunctions();

        $this->parsePhp();
    }

    /**
     * Write file.
     * If directory structure does not exists, create it.
     * 
     * @return void
     */
    private function file_force_contents($fullfile, $contents){
        $parts = explode(DIRECTORY_SEPARATOR, $fullfile);
        $file = array_pop($parts);
        
        $dir = '';
        foreach( $parts as $part ) {
            $dir .= $part . DIRECTORY_SEPARATOR;
            if ( !is_dir( $dir ) ) {
                mkdir( $dir );
            }
        }

        file_put_contents($fullfile, $contents);
    }

    /**
     * Build file full name (with path).
     * 
     * @return string $filename
     */
    private function filename( $template_file, $path = null )
    {
        if ( is_null( $path ) ) {
            $path = $this->path;
        }
        return $this->rootpath . str_replace( '.', DIRECTORY_SEPARATOR, $path ) . DIRECTORY_SEPARATOR . str_replace( '.', DIRECTORY_SEPARATOR, $template_file ) . '.' . $this->ext;
    }

    /**
     * Read file from system.
     * 
     * @return string $content
     */
    public function read( $template_file, $path = null, $params = [] )
    {
        if ( is_null( $path ) ) {
            $path = $this->path;
        }
        $filename = $this->filename( $template_file, $path );
        if ( !file_exists( $filename ) ) {
            throw new \Exception('Ficheiro ' . $filename . ' não existe.');
            exit;
        }
        $content = file_get_contents( $filename );
        return $content;
    }

    /**
     * Parse php file from system.
     * 
     * @return string $content
     */
    public function load( $template_file, $path = null, $params = [] )
    {
        if ( is_null( $path ) ) {
            $path = $this->path;
        }
        ob_start();
        if ( count( $params ) > 0 ) {
            extract( $params );
        }
        include( $this->filename( $template_file, $path ) );
        $content = ob_get_clean();
        return $content;
    }

    public function parseLoad()
    {
        // Extended
        $extend_pattern = '/@extends\([b]*\'(' . self::NAME_PATTERN . ')\'[b]*\)/';
        preg_match( $extend_pattern, $this->template, $matches );

        if ( count( $matches ) > 0 ) {
            // $this->extended = $this->read( $matches[1] );
            $loader = new TemplateLoader( $matches[1] );
            $this->extended = $loader->get();

            $this->template = str_replace( $matches[0], '', $this->template);
            $matches = [];

            // Sections
            $sections_pattern = '/@section\([\s]*\'(' . self::NAME_PATTERN . ')\'[\s]*\)(?:' . self::BREAK_LINE . ')?[\s]*(.*?)@endsection(?:' . self::BREAK_LINE . ')?/s';
            preg_match_all( $sections_pattern, $this->template, $matches );

            if ( count( $matches ) > 0 ) {
                foreach( $matches[1] as $i => $key ) {
                    $this->sections[ $key ] = $matches[2][$i];
                    $this->template = str_replace( $matches[0][$i], '', $this->template);
                }
            }
            $matches = [];
            
            // Yield
            $yield_pattern = '/@yield\([\s]*\'(' . self::NAME_PATTERN . ')\'[\s]*\)/';
            preg_match_all( $yield_pattern, $this->extended, $matches);

            $this->doc = $this->extended;
            $this->extended = '';

            foreach( $matches[1] as $i => $key ) {
                if ( array_key_exists( $key, $this->sections ) ) {
                    $this->doc = str_replace( $matches[0][$i], $this->sections[ $key ], $this->doc);
                } else {
                    $this->doc = str_replace( $matches[0][$i], '', $this->doc);
                }
            }
            $matches = [];
        } else {
            $this->doc = $this->template;
        }

        // Includes
        $include_pattern = '/@include\([\s]*\'(' . self::NAME_PATTERN . ')\'[\s]*\)/';
        preg_match_all( $include_pattern, $this->doc, $matches);

        if ( count( $matches ) > 0 ) {
            foreach( $matches[1] as $i => $include ) {
                $loader = new TemplateLoader( $include );
                $content = $loader->get();

                $this->doc = str_replace( $matches[0][$i], $content, $this->doc );
            }
        }
        $matches = [];
    }

    /**
     * Parse magic functions (starting with @)
     * 
     * @return void
     */
    public function parseMagicFunctions()
    {
        // Assets
        $this->doc = $this->parseMagicAsset( $this->doc );

        // Route
        $this->doc = $this->parseMagicRoute( $this->doc );
    }

    /**
     * Parse magic function @asset('resource')
     * 
     * @return string $doc
     */
    private function parseMagicAsset( $doc )
    {
        $asset_pattern = '/@asset\([\s]*\'(' . self::WORD_PATTERN . ')\'[\s]*\)/';
        preg_match_all( $asset_pattern, $doc, $matches);

        $request = Request::getInstance();

        if ( count( $matches ) > 0 ) {
            foreach( $matches[1] as $i => $asset ) {
                $doc = str_replace( $matches[0][$i], $request->base() . $matches[1][$i], $doc);
            }
        }

        return $doc;
    }

    /**
     * Parse magic function @route('route', ['param' => $value])
     * 
     * @return string $doc;
     */
    private function parseMagicRoute( $doc )
    {
        $route_pattern = '/@route\(([\s]*\'' . self::WORD_PATTERN . '\'[\s]*[,]?' . self::ANYWORD_PATTERN . '*)\)/';
        preg_match_all( $route_pattern, $doc, $matches);

        foreach( $matches[0] as $i => $route ) {
            $doc = str_replace( $route, "<?php echo route( " . $matches[1][$i] . " ); ?>", $doc );
        }

        return $doc;
    }

    /**
     * Parse php blocks: if, for, foreach.
     * 
     * @return void;
     */
    public function parsePhp()
    {
        // if
        $this->doc = $this->parsePhpIf( $this->doc );

        // for
        $this->doc = $this->parsePhpFor( $this->doc );        

        // foreach
        $this->doc = $this->parsePhpForEach( $this->doc );

        // echo
        $this->doc = $this->parsePhpEcho( $this->doc );
    }

    /**
     * Parse php block if
     * 
     * @return string $doc
     */
    private function parsePhpIf( $doc )
    {
        $if_pattern = '/\{% if ([^%\}]*) %\}/s';
        preg_match_all( $if_pattern, $doc, $matches);

        foreach( $matches[1] as $i => $cond ) {
            $doc = str_replace( $matches[0][$i], '<?php if (' . $cond . ') { ?>', $doc);
        }

        $else_pattern = '/\{% else %\}/s';
        preg_match_all( $else_pattern, $doc, $matches);

        foreach( $matches[0] as $i => $item ) {
            $doc = str_replace( $matches[0][$i], '<?php } else { ?>', $doc);
        }

        $elseif_pattern = '/\{% elseif ([^%\}]*) %\}/s';
        preg_match_all( $elseif_pattern, $doc, $matches);

        foreach( $matches[1] as $i => $cond ) {
            $doc = str_replace( $matches[0][$i], '<?php } elseif ' . $cond . ' { ?>', $doc);
        }

        $endif_pattern = '/\{% endif %\}/s';
        preg_match_all( $endif_pattern, $doc, $matches);

        foreach( $matches[0] as $i => $item ) {
            $doc = str_replace( $matches[0][$i], '<?php } ?>', $doc);
        }

        return $doc;
    }

    /**
     * Parse php block for
     * 
     * @return string $doc
     */
    private function parsePhpFor( $doc )
    {
        $for_pattern = '/\{% for ([^%\}]*) %\}/s';
        preg_match_all( $for_pattern, $doc, $matches);

        foreach( $matches[1] as $i => $cond ) {
            $doc = str_replace( $matches[0][$i], '<?php for ' . $cond . ' { ?>', $doc);
        }

        $endfor_pattern = '/\{% endfor %\}/s';
        preg_match_all( $endfor_pattern, $doc, $matches);

        foreach( $matches[0] as $i => $item ) {
            $doc = str_replace( $matches[0][$i], '<?php } ?>', $doc);
        }

        return $doc;
    }

    /**
     * Parse php block foreach
     * 
     * @return string $doc
     */
    private function parsePhpForEach( $doc )
    {
        $foreach_pattern = '/\{% foreach ([^%\}]*) %\}/s';
        preg_match_all( $foreach_pattern, $doc, $matches);

        foreach( $matches[1] as $i => $cond ) {
            $doc = str_replace( $matches[0][$i], '<?php foreach ' . $cond . ' { ?>', $doc);
        }

        $endforeach_pattern = '/\{% endforeach %\}/s';
        preg_match_all( $endforeach_pattern, $doc, $matches);

        foreach( $matches[0] as $i => $item ) {
            $doc = str_replace( $matches[0][$i], '<?php } ?>', $doc);
        }

        return $doc;
    }

    /**
     * Parse php keys {{ }}
     * 
     * @return stirng $doc
     */
    private function parsePhpEcho( $doc )
    {
        $echo_pattern = '/\{\{ ([^\}]*) \}\}/s';
        preg_match_all( $echo_pattern, $doc, $matches);

        foreach( $matches[1] as $i => $echo ) {
            // Modifiers
            $modifiers = explode('|', $echo);
            // dd( $modifiers );
            $echo = $modifiers[0];
            if ( count( $modifiers ) > 1 ) {
                for( $t = 1; $t < count( $modifiers ); $t++) {
                    $base_modifier = explode(':', $modifiers[$t] );
                    switch( $base_modifier[0] ) {
                        case 'money':
                        case 'decimal':
                            if ( count( $base_modifier ) > 1 ) {
                                $decimals = $base_modifier[1];
                            } else {
                                $decimals = 2;
                            }
                            $echo = 'number_format( ' . $echo . ', ' . $decimals . ', ",", "." )';
                            break;

                        case 'upper':
                            $echo = 'strtoupper( ' . $echo . ' ) ';
                            break;

                        case 'lower':
                            $echo = 'strtolower( ' . $echo . ' ) ';
                            break;

                        case 'first':
                            $echo = 'ucfirst( ' . $echo . ' ) ';
                            break;

                    }
                }
            }
            // dd( $modifiers );
            $doc = str_replace( $matches[0][$i], '<?php echo ' . $echo . '; ?>', $doc);
        }

        return $doc;
    }

    /**
     * Return $doc template
     * 
     * @return string $doc
     */
    public function get()
    {
        return $this->doc;
    }

    /**
     * Render $doc as php
     * 
     * @return void
     */
    public function render()
    {
        $cache_file = $this->filename( $this->template_file, $this->cache );
        $this->file_force_contents( $cache_file, $this->doc );
        $this->doc = $this->load( $this->template_file, $this->cache, $this->params );
    }
}