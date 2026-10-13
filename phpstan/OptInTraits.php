<?php

declare(strict_types=1);

/*
| Só para a análise estática (não faz parte do package): o PHPStan só analisa um trait que alguma classe use.
| Os traits abaixo são opcionais, usados pelas aplicações e pelos packages, por isso aqui "usam-se" uma vez.
*/

namespace Slendie\Phpstan;

use Slendie\Model\HasUuids;
use Slendie\Model\Model;
use Slendie\Model\SoftDeletes;
use Slendie\Testing\DatabaseMigrations;
use Slendie\Testing\DatabaseTransactions;
use Slendie\Testing\RefreshDatabase;
use Slendie\Testing\TestCase;
use Slendie\Testing\WithoutMiddleware;

final class OptInModel extends Model
{
    use HasUuids;
    use SoftDeletes;
}

abstract class OptInTestCase extends TestCase
{
    use DatabaseMigrations;
    use DatabaseTransactions;
    use RefreshDatabase;
    use WithoutMiddleware;
}
