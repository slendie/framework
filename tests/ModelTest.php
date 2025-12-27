<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Slendie\Framework\Database\Model;

final class ModelTest extends TestCase
{
    public function testCanMakeModel()
    {
        $model = new Model('users');

        return $this->assertInstanceOf(Model::class, $model);
    }

    public function testCanExtendedModel()
    {
        $model = new App\Models\User();

        return $this->assertInstanceOf(App\Models\User::class, $model);
    }

    public function testCanExtendedStaticModel()
    {
        $rows = App\Models\User::all();

        return $this->assertInstanceOf(App\Models\User::class, $rows[0]);
    }

    public function testCanPreserveTable()
    {
        $model = new App\Models\User();

        return $this->assertEquals('users', $model->getTable());
    }

    public function testCanMakeWhere()
    {
        $select = App\Models\User::where('email', 'test@test.com')->where('name', 'User Test')->getSql();

        $expected = "SELECT * FROM `users` WHERE `email` = 'test@test.com' AND `name` = 'User Test'";

        return $this->assertEquals($expected, $select);
    }

    public function testCanSelectFirst()
    {
        $user = App\Models\User::where('email', '%@test.com', 'LIKE')->first();

        return $this->assertInstanceOf(App\Models\User::class, $user);
    }

    public function testCanMultipleSelectWithWhere()
    {
        $rows = App\Models\User::where('email', '%@test.com', 'LIKE')->get();

        return $this->assertGreaterThan(0, count( $rows ) );
    }

    public function testCanSingleSelectWithWhereAndGet()
    {
        $rows = App\Models\User::where('email', 'test@test.com')->get();

        $row = $rows[0];

        return $this->assertEquals('Test User', $row->name);
    }

    public function testCanSingleSelectWithWhereAndFirst()
    {
        $row = App\Models\User::where('email', 'test@test.com')->first();

        return $this->assertEquals('Test User', $row->name);
    }

    public function testCanCallNonStaticWhere()
    {
        $model = new App\Models\User();
        $row = $model->where('email', 'test@test.com')->first();

        return $this->assertEquals('Test User', $row->name);
    }
}