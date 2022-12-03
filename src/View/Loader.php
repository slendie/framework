<?php

namespace Slendie\Framework\View;

class Loader
{
    const BREAK_LINE = ( PHP_OS == 'Linux' ? "\n" : "\r\n" );
    const NAME_REGEX = '[A-Za-z0-9\.\-\_]{1,}';   // Any object name
    const WORD_REGEX = '[^\']{1,}';
    const EXTENDED_PATTERN = '/@extends\([\s]*\'(' . self::NAME_REGEX . ')\'[\s]*\)/';
    const SECTION_PATTERN = '/@section\([\s]*\'(' . self::NAME_REGEX . ')\'[\s]*\)(?:' . self::BREAK_LINE . ')?[\s]*(.*?)@endsection(?:' . self::BREAK_LINE . ')?/s';
    const YIELD_PATTERN = '/@yield\([\s]*\'(' . self::NAME_REGEX . ')\'[\s]*\)(?:' . self::BREAK_LINE . ')?/s';

    /**
     * @param string $path
     */
    protected $path;

    /**
     * @param string $file
     */
    protected $file;

    /**
     * @param string $extension
     */
    protected $extension;

    /**
     * @param string $content
     */
    protected $content = '';

    /**
     * @param string $extended
     */
    protected $extended = '';

    /**
     * @param string $sections
     */
    protected $sections = [];

    /**
     * @param string $path
     * @param string $extension
     * @throws \Exception
     */
    public function __construct( string $template = null, string $path = null, $extension = null )
    {
        $this->setBasePath( $path );
        $this->setExtension( $extension );

        $this->setFileContent( $template );

        $this->parse();
    }

    /**
     * Define the view's path.
     * Convert '.' into directory separator.
     * Checks if path exists.
     *
     * @param string|null $path
     * @throws \Exception
     */
    public function setBasePath( string $path = null )
    {
        if ( empty( $path ) ) {
            $path = SITE_FOLDER . env('VIEW')['VIEW_PATH'];
        }

        $path = self::convertToPath( $path );

        if ( substr( $path, -1) !== DIRECTORY_SEPARATOR ) {
            $path .= DIRECTORY_SEPARATOR;
        }
        
        if ( ! $path || ! \is_dir( $path ) ) {
            throw new \Exception( "View path [{$path}] not found." );
        }

        $this->path = $path;
    }

    /**
     * Set view's extension.
     * View's extension are attached at the end of the file name.
     *
     * @param null $extension
     */
    public function setExtension( $extension = null ) 
    {
        if ( empty( $extension ) ) {
            $extension = env('VIEW')['VIEW_EXTENSION'];
        }

        $this->extension = $extension;
    }

    /**
     * Return the base path.
     *
     * @return string
     */
    public function getBasePath(): string
    {
        return $this->path;
    }

    /**
     * Return the view's extension.
     *
     * @return string
     */
    public function getExtension(): string
    {
        return $this->extension;
    }

    private function setFileContent( $file_content = null )
    {
        $this->file = $file_content;

        if ( empty( $file_content ) ) {
            $this->content = '';
        } else {
            $this->content = $this->read( $file_content );
        }
    }

    /**
     * Convert $path into directory path.
     *
     * @param string $path
     * @return string
     */
    public static function convertToPath( string $path ): string
    {
        $converted_path = str_replace('.', DIRECTORY_SEPARATOR, $path);
        $converted_path = str_replace('\\', DIRECTORY_SEPARATOR, $converted_path);
        $converted_path = str_replace('/', DIRECTORY_SEPARATOR, $converted_path);

        return $converted_path;
    }

    /**
     * Check if file exists.
     *
     * @param string $path
     * @return bool
     * @throws \Exception
     */
    private function check( $file ): bool
    {
        if ( empty( $file ) ) {
            return false;
        }

        if ( !file_exists( $file ) ) {
            throw new \Exception( 'File not found: ' . $file );
        }

        if ( !is_readable( $file) ) {
            throw new \Exception( 'File not readable: ' . $file );
        }

        return true;
    }

    /**
     * Read file content and return it.
     *
     * @param string $file
     * @return string
     */
    private function read( $file ): string
    {
        $full_file = self::convertToPath( $this->path . $file ) . ( !empty( $this->extension ) ? '.' . $this->extension : '' );

        if ( !$this->check( $full_file ) ) {
            return '';
        }

        return file_get_contents( $full_file );
    }

    /**
     * Parse the view's content.
     *
     * @return void
     */
    public function parse()
    {
        /* Extract all sections from the view */
        $this->parseSections();

        /* Parse the extended view */
        $this->parseExtended();

        /* Parse the yield sections */
        $this->parseYield();
    }

    /**
     * Extended the current view to their parent.
     *
     * @throws \Exception
     */
    private function parseExtended()
    {
        /* Check for layout extension */
        preg_match( self::EXTENDED_PATTERN, $this->content, $matches );

        if ( count( $matches ) > 0 ) {
            $extended = new Loader( $matches[1], $this->path, $this->extension );
            $extended->parse();

            $this->extended = $extended->getContent();

            /* Remove @extend directive from content */
            // $this->content = str_replace( $matches[0], '', $this->content );

            $this->content = $extended->getContent();
        }
    }

    /**
     * Parse sections from template.
     * Keep all sections into $sections array.
     */
    private function parseSections()
    {
        // Check for sections
        preg_match_all( self::SECTION_PATTERN, $this->content, $matches );

        if ( count( $matches[1] ) > 0 ) {
            foreach( $matches[1] as $i => $key ) {
                /* Keep section keys */
                $this->sections[ $key ] = $matches[2][$i];

                /* Cleanup sections from content */
                $this->content = str_replace( $matches[0][$i], '', $this->content );
            }
        }
    }

    /**
     * Parse yield sections.
     * Replace yield sections with their content.
     * Remove yield sections wich has no content.
     */
    private function parseYield()
    {
        preg_match_all( self::YIELD_PATTERN, $this->content, $matches);

        if ( count( $matches[1] ) > 0 ) {
            foreach( $matches[1] as $i => $key ) {
                if ( array_key_exists( $key, $this->sections ) ) {
                    $this->content = str_replace( $matches[0][$i], $this->sections[ $key ], $this->content);
                }
            }
        }
    }

    public function cleanup()
    {
        $this->cleanupSections();
        $this->cleanupYield();
    }

    public function cleanupSections()
    {
        // Check for sections
        preg_match_all( self::SECTION_PATTERN, $this->content, $matches );

        if ( count( $matches[1] ) > 0 ) {
            foreach( $matches[1] as $i => $key ) {
                /* Cleanup sections from content */
                $this->content = str_replace( $matches[0][$i], '', $this->content );
            }
        }
    }

    public function cleanupYield()
    {
        preg_match_all( self::YIELD_PATTERN, $this->content, $matches);

        if ( count( $matches[1] ) > 0 ) {
            foreach( $matches[1] as $i => $key ) {
                $this->content = str_replace( $matches[0][$i], '', $this->content);
            }
        }
    }

    /**
     * Return the Extendend view.
     *
     * @return string
     */
    public function getExtended(): string
    {
        return $this->extended;
    }

    /**
     * Return the array of sections.
     *
     * @return array
     */
    public function getSections(): array
    {
        return $this->sections;
    }

    /**
     * Return the view's content.
     *
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }
}