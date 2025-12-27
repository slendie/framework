<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

use Slendie\Framework\Database\Database;
use Slendie\Framework\Environment\Environment;

final class DatabaseTest extends TestCase
{
    private $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Database::getInstance();
    }

    /**
     * @return void
     */
    public function testCanBeSingleton()
    {
        $this->assertInstanceOf(Database::class, $this->db);
    }

    public function testCanSelectAllUsers()
    {
        $sql = "SELECT * FROM `users`;";
        $rows = $this->db->selectAllPreparedSql( $sql );

        return $this->assertGreaterThan(0, count( $rows));
    }

    public function testCanSelectSingleUser()
    {
        $sql = "SELECT * FROM `users` WHERE `email` = ?;";

        $user = $this->db->selectPreparedSql( $sql, '', ['lastone@test.com'] );

        return $this->assertIsArray( $user );
    }

/*    public function testCanSelectAllUsersWithClass()
    {
        $sql = "SELECT * FROM `users`;";
        $rows = $this->db->selectAllPreparedSql( $sql, 'App\Models\User' );

        $row = $rows[0];

        return $this->assertInstanceOf('App\Models\User', $row);
    }

    public function testCanSelectSingleUserWithClass()
    {
        $sql = "SELECT * FROM `users` WHERE `email` = ?;";

        $user = $this->db->selectPreparedSql($sql, 'App\Models\User', ['lastone@test.com']);

        return $this->assertInstanceOf('App\Models\User', $user);
    }*/

    public function testCanInsertUser()
    {
        $sql = "INSERT INTO `users` (`name`, `email`, `password`) VALUES (?, ?, ?);";

        $n_rows = $this->db->execPreparedSql($sql, ['Database User 1', 'database1@test.com', '123456']);

        return $this->assertEquals(1, $n_rows);
    }

    public function testCanUpdateUser()
    {
        $sql = "UPDATE `users` SET `name` = :name WHERE `email` = :email;";

        $n_rows = $this->db->execPreparedSql( $sql, ['name' => 'Changed to Database User 2', 'email' => 'database1@test.com']);

        return $this->assertEquals(1, $n_rows);
    }

    public function testCanDeleteUser()
    {
        $sql = "DELETE FROM `users` WHERE `email` = :email;";

        $n_rows = $this->db->execPreparedSql( $sql, ['email' => 'database1@test.com']);

        return $this->assertEquals(1, $n_rows);
    }

    public function testCanQuerySql()
    {
        $query = $this->db->query('SELECT * FROM users');

        return $this->assertInstanceOf(PDOStatement::class, $query);
    }

    public function testCanExecSql()
    {
        $inserted_rows = $this->db->exec('INSERT INTO `users` (name, email, password) VALUES ("Database Test 3", "database3@test.com", "123456");');

        return $this->assertEquals(1, $inserted_rows);
    }
}