<?php
namespace Slendie\Framework\Database;

use mysqli;

class DB {
    const ERROR_CRITICAL = 'critical';
    const ERROR_WARNING = 'warning';
    const ERROR_INFO = 'info';

    private static $instance = NULL;

    private $_driver = "mysql";
    private $_default_db = NULL;
    private $_server = "";
    private $_user = "";
    private $_pass = "";

    private $_dbname = "";
    private $_conn = NULL;
    private $_query = NULL;
    private $_errors = [];
    private $_is_connected = false;

    private function __construct($driver) {
        $this->setDriver($driver);
    }

    public static function getInstance($driver) {
        if ( is_null(self::$instance) ) {
            self::$instance = new DB($driver);
        }

        return self::$instance;
    }

    public function __destruct() {
        // Close db connection once this class was destroyed
        if ( !is_null( $this->_conn ) && true === $this->_is_connected ) {
            $this->_conn->close();
        }
    }

    // Setters
    public function setDriver($driver) {
        $this->_driver = $driver;

        switch ($driver) {
            case 'mysql':
                $this->_default_db = "mysql";
                break;
        }
    }

    public function setDbName($dbname) {
        $this->_dbname = $dbname;
    }
    public function setServer($server) {
        $this->_server = $server;
    }
    public function setUsername($user) {
        $this->_user = $user;
    }
    public function setPassword($pass) {
        $this->_pass = $pass;
    }

    public function setError($error, $severity = self::ERROR_WARNING) {
        $this->_errors[] = [
            'severity'  => $severity,
            'message'   => $error
        ];
    }

    // Getters
    public function getDriver() {
        return $this->_driver;
    }

    public function getServer() {
        return $this->_server;
    }
    public function getUsername() {
        return $this->_user;
    }
    public function getPassword() {
        return $this->_pass;
    }
    public function getDbName() {
        return $this->_dbname;
    }

    public function hasErrors() {
        if ( count( $this->_errors ) > 0 ) {
            return true;
        } else {
            return false;
        }
    }
    public function getErrors() {
        return $this->_errors;
    }

    // Connect to database
    public function connect($server, $user, $pass, $db = "", $port = 3306) {
        if ( $db == "" ) {
            $db = $this->_default_db;
        }
        $this->setServer( $server );
        $this->setUsername( $user );
        $this->setPassword( $pass );
        $this->setDbName( $db );

        switch ( $this->getDriver() ) {
            case 'mysql':
                $this->_conn = new mysqli($server, $user, $pass, $db, $port);
                $warnings = $this->_conn->get_warnings();

                if ( $this->_conn->connect_errno ) {
                    $this->setError("Failed to connect to MySQL: (" . $this->_conn->connect_errno . ") " . $this->_conn->connect_error, self::ERROR_CRITICAL);
                    $this->_is_connected = false;
                } else {
                    $this->_is_connected = true;
                }
                $this->_conn->set_charset("utf8");
                break;
        }
    }

    public function changeDatabase($db) {
        $this->connect( $this->getServer(), $this->getUsername(), $this->getPassword(), $db);
    }

    // Querying
    public function query( $sql ) {
        $this->_query = mysqli_query( $this->_conn, $sql );

        return $this->_query;
    }

    // Access data
    public function databases() {
        $sql = "SHOW DATABASES";

        $q = $this->query($sql);
        $r = [];
        foreach( $q as $dbase ) {
            $r[] = $dbase['Database'];
        }

        return $r;
    }

    public function tables() {
        $sql = "SHOW TABLES";
        $key = "Tables_in_" . $this->getDbName();

        $q = $this->query($sql);
        $r = [];
        foreach( $q as $table ) {
            $r[] = $table[$key];
        }

        return $r;
    }

    public function columns( $table ) {
        $sql = "SHOW COLUMNS FROM " . $table;

        $q = $this->query($sql);
        $r = [];
        foreach( $q as $i => $column ) {
            $r[] = [
                'name'  => $column['Field'],
                'type'  => $column['Type'],
                'null'  => $column['Null'],
                'key'   => $column['Key'],
                'default'   => $column['Default']
            ];
        }

        return $r;
    }

    public function select( $table ) {
        $sql = "SELECT * FROM " . $table;

        $q = $this->query($sql);
        $r = [];

        if ( $q->num_rows > 0 ) {
            while( $row = $q->fetch_assoc() ) {
                $r[] = $row;
            }
        }

        return $r;
    }
}
