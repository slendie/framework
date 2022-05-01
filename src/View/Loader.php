<?php
namespace Slendie\Framework\View;

class Loader
{
    const KEY_WORD = '\w\.\_\-\>\)\(';
    protected $base = "";
    protected $path = "";
    protected $extension = "";
    protected $template = "";
    protected $doc = "";
    protected $data = [];

    public function __construct()
    {
    }

    public function setBase( $folder ): void
    {
        $this->base = $folder;
    }

    /**
     * Set default folder for views.
     * If not used, default folder is resources/views
     * @param string $folder Folder where views are
     * @return void
     */
    public function setPath( $folder ): void 
    {
        $path = str_replace( '.', DIRECTORY_SEPARATOR, $folder);
        if ( substr( $path, -1 ) != DIRECTORY_SEPARATOR ) {
            $path .= DIRECTORY_SEPARATOR;
        }
        $this->path = $path;
    }

    public function setTemplate( $template ): void
    {
        $this->template = $template;
    }

    /**
     * Set default template extension for view.
     * If not used, default extension is .tpl.php
     * @param string $extension Extension for views
     * @return void
     */
    public function setExtension( $extension ): void 
    {
        $this->extension = $extension;
    }

    public function setKey( $key, $value ) 
    {
        $this->data[ $key ] = $value;
    }

    public function setData( $data ) 
    {
        if ( is_array( $data ) ) {
            foreach( $data as $key => $value ) {
                $this->setKey( $key, $value );
            }
        }
    }

    public function set( $content )
    {
        $this->doc = $content;
    }

    public function get() 
    {
        return $this->doc;
    }

    public function replace( $search, $replace_to )
    {
        $this->set( str_replace( $search, $replace_to, $this->get() ));
    }

    public function pregReplace( $pattern, $replace_to )
    {
        $this->set( preg_replace( $pattern, $replace_to, $this->get() ));
    }

    public function base(): string
    {
        return $this->base;
    }
    
    /**
     * Get default folder.
     * @return string Folder 
     */
    public function path(): string 
    {
        return $this->path;
    }

    public function data(): array
    {
        return $this->data;
    }

    public function extension(): string
    {
        return $this->extension;
    }
    
    public function key( string $key )
    {
        if ( $this->keyExists( $key )) {
            return $this->data[ $key ];
        } else {
            return NULL;
        }
    }

    public function extract( string $pattern )
    {
        preg_match_all( $pattern, $this->get(), $matches );
        return $matches;
    }

    public function load( $template, $data = [] )
    {
        $this->setTemplate( $template );
        $this->setData( $data );

        return $this->parse();
    }

    public function parse($return_doc = true)
    {
        if ( '' == $this->template ) {
            return '';
        }

        $template = str_replace('.', DIRECTORY_SEPARATOR, $this->template);
        $filename = $this->base() . $this->path() . $template . '.' . $this->extension;

        if ( !file_exists( $filename ) ) {
            throw new \Exception( sprintf('%s file was not found.', $filename));
        }

        if ( count( $this->data ) > 0 ) {
            extract( $this->data );
        }

        ob_start();
        include $filename;
        $this->set( ob_get_clean() );

        if ( $return_doc ) {
            return $this->get();
        }
    }

    public function keyExists( $key ): bool
    {
        return array_key_exists( $key, $this->data() );
    }

    public function parseKeys( $subject, $with_slashes = false ): string
    {
        $key_pattern = '/\$([' . self::KEY_WORD . ']*)/';
        preg_match_all( $key_pattern, $subject, $matches );

        foreach( $matches[0] as $i => $found ) {
            $key = $matches[1][$i];
            if ( true == strpos( $key, '->' ) ) {
                $obj_parts = explode('->', $key);
                if ( $this->keyExists( $obj_parts[0]) ) {
                    // $obj = $this->key( $obj_parts[0] );
                    $obj = $this->key( array_shift( $obj_parts ) );
                    $rest = implode('->', $obj_parts);

                    $eval = '$check = !is_null($obj->' . $rest . ');';
                    // xdebug_var_dump($eval);
                    eval($eval);
                    // xdebug_var_dump($check);
                    // $check = true;
                    // while( count($obj_parts) > 0 && $check == true ) {
                    //     $property = array_shift($obj_parts);
                    //     if ( endsWith(')', $property) ) {
                    //         if ( method_exists($obj, $property) ) {
                    //             $eval = '$check = !is_null( $obj->' . $property . ');';
                    //             eval($eval);
                    //         } else {
                    //             $check = false;
                    //         }
                    //     } else {
                    //         if ( property_exists( $obj, $property )) {
                    //             $eval = '$check = !is_null( $obj->' . $property . ');';
                    //             eval($eval);
                    //         } else {
                    //             $check = false;
                    //         }
                    //     }
                    //     if ($check) {
                    //         eval('$obj = $obj->' . $property . ';');
                    //     }
                    // }
                    if ($check) {
                        // $param_value = $obj;
                        $eval = '$param_value = $obj->' . $rest . ';';
                        eval($eval);
                        if ( is_numeric($param_value) ) {
                            $subject = str_replace( $found, $param_value, $subject );
    
                        } elseif ( !is_array($param_value) ) {
                            // $subject = str_replace( $found, "'".addslashes($param_value)."'", $subject );
                            if ( $with_slashes ) {
                                $subject = str_replace( $found, "'".addslashes($param_value)."'", $subject );
                            } else {
                                $subject = str_replace( $found, $param_value, $subject );
                            }
                            
                        }
                    }
                    
                }
            } else {
                if ( $this->keyExists( $matches[1][$i] ) ) {
                    $value = $this->key( $matches[1][$i] );

                    if ( is_numeric($value) ) {
                        $subject = str_replace( $found, $value, $subject );

                    } elseif ( !is_array($value) ) {
                        // $subject = str_replace( $found, "'".addslashes($value)."'", $subject );
                        if ( $with_slashes ) {
                            $subject = str_replace( $found, "'".addslashes($value)."'", $subject );
                        } else {
                            $subject = str_replace( $found, $value, $subject );
                        }
                        
                    }

                }
            }
        }

        return $subject;
    }
}