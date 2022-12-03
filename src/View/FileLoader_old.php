<?php
namespace Slendie\Framework\View;

class FileLoader
{
    const KEY_PATTERN = '[b]*\'([A-Za-z0-9\.\-]{1,})\'[b]*';

    protected $extends = '';
    protected $root = '';
    protected $path = '';
    protected $ext = '';
    protected $doc = '';

    public function __construct( $template )
    {
        $this->root = env('base_dir');
        $this->path = str_replace('.', DIRECTORY_SEPARATOR, env('view_path') );
        $this->ext = env('view_extension');

        $this->load( $template );
    }

    public function load( $template )
    {
        if ( '' == $template ) {
            return '';
        }

        // Get template all content
        $template = str_replace('.', DIRECTORY_SEPARATOR, $template);
        $filename = $this->root . $this->path . DIRECTORY_SEPARATOR . $template . '.' . $this->ext;

        if ( !file_exists( $filename ) ) {
            throw new \Exception( sprintf('O template %s não existe.', $filename));
        }

        ob_start();
        include $filename;
        $this->doc = ob_get_clean();

        // Check for extends
        $pattern = '/@extends\(' . self::KEY_PATTERN . '\)/';
        preg_match( $pattern, $this->doc, $matches );

        if ( count( $matches ) > 0 ) {
            $template = new Template();
            $this->extends = $template->render( $matches[1] );

            $this->doc = str_replace( $matches[0], '', $this->doc );
        }

        // Check for sections
        $pattern = '/@section\(' . self::KEY_PATTERN . '\)([)@endsection/';
        preg_match( $pattern, $this->doc, $matches );

        dc('FileLoader::load', $matches);
    }

    public function setDoc( $content )
    {
        $this->doc = $content;
    }

    public function get()
    {
        return $this->doc;
    }
}