<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Slendie\Framework\Database\Connection;

use App\App;

final class ConnectionTest extends TestCase
{
    private $conn;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conn = Connection::getInstance();
        $this->conn->setOptions( env('DATABASE') );
        $this->conn->connect();
    }

    public function testCanBeSingleton()
    {
        $this->assertInstanceOf(Connection::class, $this->conn);
    }

    public function testCanConnectToDatabase()
    {
        $this->assertInstanceOf(Connection::class, $this->conn);
    }

    /**
     * https://www.php.net/manual/en/pdo.query.php
     * @depends testCanConnectToDatabase
     */
    public function testCanReturnPdoStatement()
    {
        $query = $this->conn->query('SELECT * FROM users');

        $this->assertInstanceOf(PDOStatement::class, $query);
    }

    /**
     * https://www.php.net/manual/en/pdo.exec.php
     * https://www.php.net/manual/en/pdostatement.rowcount.php
     * @depends testCanReturnPdoStatement
     */
    public function testCanExecDelete()
    {
        $n_rows = $this->conn->exec('DELETE FROM `users`;');
        $sttm = $this->conn->query('SELECT * FROM `users`;');

        $rows = $sttm->fetchAll();

        $count_users = count( $rows );

        $this->assertEquals(0, $count_users);
    }

    /**
     * https://www.php.net/manual/en/pdo.exec.php
     * @depends testCanExecDelete
     */
    public function testCanExecInsert()
    {
        $deleted_rows = $this->conn->exec('DELETE FROM `users`;');
        $inserted_rows = $this->conn->exec('INSERT INTO `users` (name, email, password) VALUES ("Test User", "test@test.com", "123456");');

        $this->assertEquals(1, $inserted_rows);
    }

    /**
     * @depends testCanExecInsert
     */
    public function testCanQuerySingleSelect()
    {
        $sttm = $this->conn->query("SELECT * FROM `users` WHERE `email` = 'test@test.com';");

        $row = $sttm->fetch();

        $this->assertEquals('Test User', $row['name']);
    }

    /**
     * https://www.php.net/manual/en/pdo.prepare.php
     * @depends testCanExecInsert
     */
    public function testCanExecPrepareSelect()
    {
        $sql = "SELECT * FROM `users` WHERE `email` = :email;";
        $sttm = $this->conn->prepare( $sql, [PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY] );
        $sttm->execute(['email' => 'test@test.com']);

        $row = $sttm->fetch();

        $this->assertEquals('Test User', $row['name']);

        $sttm->execute(['email' => 'noone@test.com']);
        
        $row = $sttm->fetch();

        $this->assertEquals(false, $row);
    }

    /**
     * https://www.php.net/manual/en/pdo.prepare.php
     * @depends testCanExecInsert
     */
    public function testCanExecPrepareSelectQuotation()
    {
        $sql = "SELECT * FROM `users` WHERE `email` = ?;";
        $sttm = $this->conn->prepare( $sql );
        $sttm->execute(['test@test.com']);

        $row = $sttm->fetch();

        $this->assertEquals('Test User', $row['name']);

        $sttm->execute(['noone@test.com']);
        
        $row = $sttm->fetch();

        $this->assertEquals(false, $row);
    }

    /**
     * https://www.php.net/manual/en/pdo.prepare.php
     * @depends testCanConnectToDatabase
     */
    public function testCanExecPrepareInsert()
    {
        $sql = "INSERT INTO `users` (`name`, `email`, `password`) VALUES (:name, :email, :password);";
        $sttm = $this->conn->prepare( $sql, [PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY] );
        $n_rows = $sttm->execute([
            'name'      => 'User Prepared 1',
            'email'     => 'prepared1@test.com',
            'password'  => '123456',
        ]);

        $this->assertEquals(1, $n_rows);
    }

    /**
     * https://www.php.net/manual/en/pdo.prepare.php
     * @depends testCanExecPrepareInsert
     */
    public function testCanExecPrepareUpdate()
    {
        $sql = "UPDATE `users` SET `name` = :name WHERE `email` = :email;";
        $sttm = $this->conn->prepare( $sql, [PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY] );
        $n_rows = $sttm->execute([
            'name'      => 'User Updated 1',
            'email'     => 'prepared1@test.com',
        ]);

        $sttm = $this->conn->query("SELECT * FROM `users` WHERE `email` = 'prepared1@test.com';");
        $row = $sttm->fetch();

        $this->assertEquals('User Updated 1', $row['name']);
    }

    /**
     * https://www.php.net/manual/en/pdo.errorinfo.php
     * @depends testCanConnectToDatabase
     */
    public function testCanGetErrorInfo()
    {
        $sql = "bogus SQL";
        try {
            $sttm = $this->conn->prepare( $sql, [PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY] );
            $errorMsg = '';
        } catch (\PDOException $e) {
            $msg = $e->getMessage();
            $errorMsg = $this->conn->errorInfo()[2];
        }

        $this->assertEquals('near "bogus": syntax error', $errorMsg);
    }

    /**
     * https://www.php.net/manual/en/pdo.errorcode.php
     * @depends testCanConnectToDatabase
     */
    public function testCanGetErrorCode()
    {
        try {
            $sttm = $this->conn->exec("INSERT INTO bones(skull) VALUES ('lucy')");
            $errorMsg = '';
        } catch (\PDOException $e) {
            $msg = $e->getMessage();
            $errorMsg = $this->conn->errorCode();
        }

        $this->assertEquals('HY000', $errorMsg);
    }

    /**
     * https://www.php.net/manual/en/pdo.errorcode.php
     * @depends testCanConnectToDatabase
     */
    public function testCanGetErrorFromCatch()
    {
        try {
            $sttm = $this->conn->exec("INSERT INTO bones(skull) VALUES ('lucy')");
            $msg = '';
            $code = 0;
        } catch (\PDOException $e) {
            $msg = $e->getMessage();
            $code = $e->getCode();
        }

        $this->assertEquals('HY000', $code);
    }

    /**
     * https://www.php.net/manual/en/pdo.lastinsertid.php
     * @depends testCanConnectToDatabase
     */
    public function testGetLastInsertId()
    {
        $sql = "INSERT INTO `users` (`name`, `email`, `password`) VALUES (:name, :email, :password);";
        $sttm = $this->conn->prepare( $sql, [PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY] );
        $n_rows = $sttm->execute([
            'name'      => 'Last User Inserted',
            'email'     => 'lastone@test.com',
            'password'  => '123456',
        ]);

        $id = $this->conn->lastInsertId();

        $sql = "SELECT * FROM `users` WHERE `email` = ?";
        $sttm = $this->conn->prepare( $sql );
        $sttm->execute(['lastone@test.com']);

        $row = $sttm->fetch();

        $this->assertEquals($row['id'], $id);
    }

    /**
     * https://www.php.net/manual/en/pdo.begintransaction.php
     * @depends testCanConnectToDatabase
     */
    public function testCanRollbackTransaction()
    {
        $this->conn->beginTransaction();

        $sql = "INSERT INTO `users` (`name`, `email`, `password`) VALUES (:name, :email, :password);";
        $sttm = $this->conn->prepare( $sql, [PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY] );
        $n_rows = $sttm->execute([
            'name'      => 'Not inserted data',
            'email'     => 'noone@test.com',
            'password'  => '123456',
        ]);

        $this->conn->rollback();

        $sql = "SELECT * FROM `users` WHERE `email` = ?";
        $sttm = $this->conn->prepare( $sql );
        $sttm->execute(['noone@test.com']);

        $row = $sttm->fetch();

        $this->assertEquals(false, $row);
    }
}