<?php

use Slendie\Framework\Environment\Environment;
use PHPUnit\Framework\TestCase;

class EnvironmentTest extends TestCase
{
    private $env;

    protected function setUp(): void
    {
        parent::setUp();

        $this->env = Environment::getInstance();
        $this->env->setEnvFile( SITE_FOLDER . '.env.testing' );
        $this->env->forceLoad();
    }

    /**
     * Get the environment file name
     */
    public function testCanGetFilename()
    {
        $this->assertEquals( SITE_FOLDER . '.env.testing', $this->env->getFilename() );
    }

    /**
     * Get a key
     */
    public function testCanGetKey()
    {
        $this->assertEquals( 'Slendie', $this->env->get('APP_TITLE') );
    }

    /**
     * Get a key from a section
     */
    public function testCanGetKeyFromSection()
    {
        $env_value = $this->env->get('DATABASE');

        $this->assertEquals( 'sqlite', $env_value['DRIVER'] );
    }

    /**
     * Get a key from environment 
     */
    public function testCanGetKeyFromEnvironment()
    {
        $this->assertEquals( 'Slendie', getenv('APP_TITLE') );
    }

    /**
     * Get a key from environment from a section
     */
    public function testCanGetKeyFromEnvironmentFromSection()
    {
        $this->assertEquals( 'sqlite', getenv('DATABASE.DRIVER') );
    }

    /**
     * Get array from environment
     */
    public function testCanGetArrayFromEnvironment()
    {
        $expected = [
            'VIEW_PATH'     => 'resources.views',
            'VIEW_CACHE'    => 'resources.cache',
            'VIEW_EXTENSION' => 'tpl.php',
        ];

        $this->assertEquals( $expected, $this->env->get('VIEW') );
    }

    public function testCanGetFromHelperFunction()
    {
        $database_name = env('DATABASE')['DBNAME'];

        $expected = "storage\\slendie.sqlite3";   // .env.testing

        $this->assertEquals( $expected, $database_name );
    }

    public function testCanGetFromEnvironmentVariable()
    {
        $database_name = getenv('DATABASE.DBNAME');

        $expected = "storage\\slendie.sqlite3";   // .env.testing

        $this->assertEquals( $expected, $database_name );
    }
}