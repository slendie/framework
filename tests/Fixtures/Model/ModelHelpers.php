<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures\Model;

use Slendie\Database\Connection;
use Slendie\Database\DatabaseManager;
use Slendie\Foundation\Application;
use Slendie\Model\Model;

/**
 * Liga os models a um SQLite em memória com as tabelas dos fixtures.
 */
final class ModelHelpers
{
    public static function connect(): Connection
    {
        Model::clearBootedModels();
        Model::reguard();
        Model::preventSilentlyDiscardingAttributes(null);

        $manager = new DatabaseManager(new Application(fixturePath('http')), [
            'default' => 'main',
            'connections' => [
                'main' => ['driver' => 'sqlite', 'database' => ':memory:'],
                'other' => ['driver' => 'sqlite', 'database' => ':memory:'],
            ],
        ]);

        Model::setConnectionResolver($manager);

        $connection = $manager->connection();

        foreach ([
            'create table leads (id integer primary key autoincrement, name varchar(100), email varchar(100) null, votes int not null default 0, status varchar(20) null, meta text null, active int not null default 1, born_at datetime null, birthday date null, balance varchar(20) null, created_at datetime null, updated_at datetime null)',
            'create table notes (id integer primary key autoincrement, lead_id int null, body varchar(200), created_at datetime null, updated_at datetime null)',
            'create table profiles (id integer primary key autoincrement, lead_id int, bio varchar(200) null)',
            'create table accounts (id integer primary key autoincrement, name varchar(100), email varchar(100), password varchar(100))',
            'create table posts (id integer primary key autoincrement, title varchar(100), slug varchar(100), deleted_at datetime null, created_at datetime null, updated_at datetime null)',
            'create table tokens (id varchar(36) primary key, label varchar(100), created_at datetime null, updated_at datetime null)',
        ] as $sql) {
            $connection->statement($sql);
        }

        return $connection;
    }
}
