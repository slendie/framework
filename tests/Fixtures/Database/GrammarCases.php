<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures\Database;

use Slendie\Database\Query\Builder;
use Slendie\Database\Query\Expression;
use Slendie\Database\Query\JoinClause;

/**
 * Casos de SQL comuns aos 3 drivers, com a sintaxe `"x"`. O teste do MySQL troca `"` por backtick.
 */
final class GrammarCases
{
    /**
     * @return array<string, array{0: callable(Builder): mixed, 1: string, 2: list<mixed>}>
     */
    public static function cases(): array
    {
        return [
            'select simples' => [fn (Builder $b) => $b->from('users'), 'select * from "users"', []],
            'colunas' => [fn (Builder $b) => $b->from('users')->select('id', 'name'), 'select "id", "name" from "users"', []],
            'alias de coluna' => [fn (Builder $b) => $b->from('users')->select('name as n', 'users.id as uid'), 'select "name" as "n", "users"."id" as "uid" from "users"', []],
            'alias de tabela' => [fn (Builder $b) => $b->from('users', 'u')->select('u.id'), 'select "u"."id" from "users" as "u"', []],
            'table.*' => [fn (Builder $b) => $b->from('users')->select('users.*'), 'select "users".* from "users"', []],
            'addSelect' => [fn (Builder $b) => $b->from('users')->select('id')->addSelect('name', ['email']), 'select "id", "name", "email" from "users"', []],
            'selectRaw com bindings' => [fn (Builder $b) => $b->from('users')->selectRaw('price * ? as total', [2]), 'select price * ? as total from "users"', [2]],
            'distinct' => [fn (Builder $b) => $b->from('users')->distinct()->select('name'), 'select distinct "name" from "users"', []],
            'expression na coluna' => [fn (Builder $b) => $b->from('users')->select(new Expression('count(*) as c')), 'select count(*) as c from "users"', []],

            'inner join' => [fn (Builder $b) => $b->from('users')->join('posts', 'users.id', '=', 'posts.user_id'), 'select * from "users" inner join "posts" on "users"."id" = "posts"."user_id"', []],
            'join com 2 colunas' => [fn (Builder $b) => $b->from('users')->join('posts', 'users.id', 'posts.user_id'), 'select * from "users" inner join "posts" on "users"."id" = "posts"."user_id"', []],
            'left join' => [fn (Builder $b) => $b->from('users')->leftJoin('posts', 'users.id', '=', 'posts.user_id'), 'select * from "users" left join "posts" on "users"."id" = "posts"."user_id"', []],
            'right join' => [fn (Builder $b) => $b->from('users')->rightJoin('posts', 'users.id', '=', 'posts.user_id'), 'select * from "users" right join "posts" on "users"."id" = "posts"."user_id"', []],
            'cross join' => [fn (Builder $b) => $b->from('users')->crossJoin('sizes'), 'select * from "users" cross join "sizes"', []],
            'join com closure' => [
                fn (Builder $b) => $b->from('users')->join('posts', function (JoinClause $j): void {
                    $j->on('users.id', '=', 'posts.user_id')->orOn('users.id', 'posts.editor_id')->where('posts.active', '=', 1);
                }),
                'select * from "users" inner join "posts" on "users"."id" = "posts"."user_id" or "users"."id" = "posts"."editor_id" and "posts"."active" = ?',
                [1],
            ],
            'join com grupo' => [
                fn (Builder $b) => $b->from('users')->leftJoin('posts', function (JoinClause $j): void {
                    $j->on('users.id', 'posts.user_id')->on(function (JoinClause $g): void {
                        $g->where('posts.kind', 'a')->orWhere('posts.kind', 'b');
                    });
                }),
                'select * from "users" left join "posts" on "users"."id" = "posts"."user_id" and ("posts"."kind" = ? or "posts"."kind" = ?)',
                ['a', 'b'],
            ],

            'where =' => [fn (Builder $b) => $b->from('users')->where('id', 1), 'select * from "users" where "id" = ?', [1]],
            'where operador' => [fn (Builder $b) => $b->from('users')->where('votes', '>=', 10), 'select * from "users" where "votes" >= ?', [10]],
            'where operador em maiúsculas' => [fn (Builder $b) => $b->from('users')->where('name', 'NOT LIKE', 'a%'), 'select * from "users" where "name" not like ?', ['a%']],
            'where array assoc' => [fn (Builder $b) => $b->from('users')->where(['a' => 1, 'b' => 2]), 'select * from "users" where ("a" = ? and "b" = ?)', [1, 2]],
            'where null vira is null' => [fn (Builder $b) => $b->from('users')->where('deleted_at', null), 'select * from "users" where "deleted_at" is null', []],
            'where <> null vira is not null' => [fn (Builder $b) => $b->from('users')->where('deleted_at', '<>', null), 'select * from "users" where "deleted_at" is not null', []],
            'orWhere' => [fn (Builder $b) => $b->from('users')->where('a', 1)->orWhere('b', 2), 'select * from "users" where "a" = ? or "b" = ?', [1, 2]],
            'where closure aninhada' => [
                fn (Builder $b) => $b->from('users')->where('a', 1)->where(fn (Builder $q) => $q->where('b', 2)->orWhere('c', 3)),
                'select * from "users" where "a" = ? and ("b" = ? or "c" = ?)',
                [1, 2, 3],
            ],
            'orWhere closure' => [
                fn (Builder $b) => $b->from('users')->where('a', 1)->orWhere(fn (Builder $q) => $q->where('b', 2)->where('c', 3)),
                'select * from "users" where "a" = ? or ("b" = ? and "c" = ?)',
                [1, 2, 3],
            ],
            'closure vazia não gera parênteses' => [fn (Builder $b) => $b->from('users')->where('a', 1)->where(fn (Builder $q) => $q), 'select * from "users" where "a" = ?', [1]],
            'whereNot' => [fn (Builder $b) => $b->from('users')->whereNot('a', 1), 'select * from "users" where not ("a" = ?)', [1]],
            'whereNot closure' => [fn (Builder $b) => $b->from('users')->whereNot(fn (Builder $q) => $q->where('a', 1)->where('b', 2)), 'select * from "users" where not ("a" = ? and "b" = ?)', [1, 2]],
            'orWhereNot' => [fn (Builder $b) => $b->from('users')->where('x', 0)->orWhereNot('a', 1), 'select * from "users" where "x" = ? or not ("a" = ?)', [0, 1]],
            'whereIn' => [fn (Builder $b) => $b->from('users')->whereIn('id', [1, 2, 3]), 'select * from "users" where "id" in (?, ?, ?)', [1, 2, 3]],
            'whereNotIn' => [fn (Builder $b) => $b->from('users')->whereNotIn('id', [1, 2]), 'select * from "users" where "id" not in (?, ?)', [1, 2]],
            'orWhereIn' => [fn (Builder $b) => $b->from('users')->where('a', 1)->orWhereIn('id', [4]), 'select * from "users" where "a" = ? or "id" in (?)', [1, 4]],
            'whereIn vazio' => [fn (Builder $b) => $b->from('users')->whereIn('id', []), 'select * from "users" where 0 = 1', []],
            'whereNotIn vazio' => [fn (Builder $b) => $b->from('users')->whereNotIn('id', []), 'select * from "users" where 1 = 1', []],
            'where in com operador' => [fn (Builder $b) => $b->from('users')->where('id', 'in', [7, 8]), 'select * from "users" where "id" in (?, ?)', [7, 8]],
            'whereNull' => [fn (Builder $b) => $b->from('users')->whereNull('a')->whereNotNull('b')->orWhereNull('c'), 'select * from "users" where "a" is null and "b" is not null or "c" is null', []],
            'whereBetween' => [fn (Builder $b) => $b->from('users')->whereBetween('votes', [1, 5]), 'select * from "users" where "votes" between ? and ?', [1, 5]],
            'whereNotBetween' => [fn (Builder $b) => $b->from('users')->whereNotBetween('votes', [1, 5]), 'select * from "users" where "votes" not between ? and ?', [1, 5]],
            'whereColumn' => [fn (Builder $b) => $b->from('users')->whereColumn('a', 'b')->whereColumn('c', '<', 'd'), 'select * from "users" where "a" = "b" and "c" < "d"', []],
            'whereRaw' => [fn (Builder $b) => $b->from('users')->whereRaw('a = ? or b = ?', [1, 2])->orWhereRaw('c is null'), 'select * from "users" where a = ? or b = ? or c is null', [1, 2]],
            'whereExists' => [
                fn (Builder $b) => $b->from('users')->where('a', 1)->whereExists(fn (Builder $q) => $q->from('posts')->whereColumn('posts.user_id', 'users.id')->where('posts.ok', 1)),
                'select * from "users" where "a" = ? and exists (select * from "posts" where "posts"."user_id" = "users"."id" and "posts"."ok" = ?)',
                [1, 1],
            ],
            'whereNotExists' => [fn (Builder $b) => $b->from('users')->whereNotExists(fn (Builder $q) => $q->from('posts')), 'select * from "users" where not exists (select * from "posts")', []],
            'when verdadeiro' => [fn (Builder $b) => $b->from('users')->when(true, fn (Builder $q) => $q->where('a', 1)), 'select * from "users" where "a" = ?', [1]],
            'when falso com default' => [fn (Builder $b) => $b->from('users')->when(false, fn (Builder $q) => $q->where('a', 1), fn (Builder $q) => $q->where('b', 2)), 'select * from "users" where "b" = ?', [2]],
            'unless' => [fn (Builder $b) => $b->from('users')->unless(false, fn (Builder $q) => $q->where('a', 1)), 'select * from "users" where "a" = ?', [1]],
            'where com Expression no valor' => [fn (Builder $b) => $b->from('users')->where('a', '>', new Expression('now()')), 'select * from "users" where "a" > now()', []],
            'bindings por ordem das secções' => [
                fn (Builder $b) => $b->from('users')->selectRaw('? as x', ['s'])->join('p', fn (JoinClause $j) => $j->on('a', 'b')->where('p.k', 'j'))->where('w', 'w')->groupBy('g')->having('c', '>', 'h')->orderByRaw('field(x, ?)', ['o']),
                'select ? as x from "users" inner join "p" on "a" = "b" and "p"."k" = ? where "w" = ? group by "g" having "c" > ? order by field(x, ?)',
                ['s', 'j', 'w', 'h', 'o'],
            ],

            'groupBy' => [fn (Builder $b) => $b->from('users')->groupBy('a', 'b'), 'select * from "users" group by "a", "b"', []],
            'groupBy com array' => [fn (Builder $b) => $b->from('users')->groupBy(['a', 'b']), 'select * from "users" group by "a", "b"', []],
            'having' => [fn (Builder $b) => $b->from('users')->groupBy('a')->having('n', '>', 2), 'select * from "users" group by "a" having "n" > ?', [2]],
            'having 2 args' => [fn (Builder $b) => $b->from('users')->groupBy('a')->having('n', 2)->orHaving('m', 3), 'select * from "users" group by "a" having "n" = ? or "m" = ?', [2, 3]],
            'havingRaw' => [fn (Builder $b) => $b->from('users')->groupBy('a')->havingRaw('sum(x) > ?', [10]), 'select * from "users" group by "a" having sum(x) > ?', [10]],

            'orderBy' => [fn (Builder $b) => $b->from('users')->orderBy('a')->orderBy('b', 'DESC'), 'select * from "users" order by "a" asc, "b" desc', []],
            'orderByDesc' => [fn (Builder $b) => $b->from('users')->orderByDesc('a'), 'select * from "users" order by "a" desc', []],
            'latest' => [fn (Builder $b) => $b->from('users')->latest(), 'select * from "users" order by "created_at" desc', []],
            'oldest' => [fn (Builder $b) => $b->from('users')->oldest('id'), 'select * from "users" order by "id" asc', []],
            'orderByRaw' => [fn (Builder $b) => $b->from('users')->orderByRaw('length(name) desc'), 'select * from "users" order by length(name) desc', []],
            'reorder' => [fn (Builder $b) => $b->from('users')->orderBy('a')->reorder(), 'select * from "users"', []],
            'reorder com nova coluna' => [fn (Builder $b) => $b->from('users')->orderBy('a')->reorder('b', 'desc'), 'select * from "users" order by "b" desc', []],

            'limit e offset' => [fn (Builder $b) => $b->from('users')->limit(10)->offset(20), 'select * from "users" limit 10 offset 20', []],
            'take e skip' => [fn (Builder $b) => $b->from('users')->take(5)->skip(5), 'select * from "users" limit 5 offset 5', []],
            'forPage' => [fn (Builder $b) => $b->from('users')->forPage(3, 15), 'select * from "users" limit 15 offset 30', []],
            'forPage ignora páginas < 1' => [fn (Builder $b) => $b->from('users')->forPage(0, 15), 'select * from "users" limit 15 offset 0', []],

            'query completa' => [
                fn (Builder $b) => $b->from('users')->select('users.id', 'users.name as n')->where('active', true)->orderBy('id')->limit(1),
                'select "users"."id", "users"."name" as "n" from "users" where "active" = ? order by "id" asc limit 1',
                [true],
            ],
        ];
    }

    /**
     * Instruções de escrita comuns (INSERT/UPDATE/DELETE) — o SQL é obtido com `compile*()`.
     *
     * @return array<string, array{0: callable(Builder): string, 1: string}>
     */
    public static function writeCases(): array
    {
        return [
            'insert 1 linha' => [fn (Builder $b) => $b->getGrammar()->compileInsert($b->from('users'), [['a' => 1, 'b' => 2]]), 'insert into "users" ("a", "b") values (?, ?)'],
            'insert N linhas' => [fn (Builder $b) => $b->getGrammar()->compileInsert($b->from('users'), [['a' => 1], ['a' => 2]]), 'insert into "users" ("a") values (?), (?)'],
            'insert default values' => [fn (Builder $b) => $b->getGrammar()->compileInsert($b->from('users'), [[]]), 'insert into "users" default values'],
            'insert com Expression' => [fn (Builder $b) => $b->getGrammar()->compileInsert($b->from('users'), [['a' => new Expression('now()'), 'b' => 1]]), 'insert into "users" ("a", "b") values (now(), ?)'],
            'update' => [fn (Builder $b) => $b->getGrammar()->compileUpdate($b->from('users')->where('id', 1), ['a' => 1, 'b' => new Expression('b + 1')]), 'update "users" set "a" = ?, "b" = b + 1 where "id" = ?'],
            'update sem where' => [fn (Builder $b) => $b->getGrammar()->compileUpdate($b->from('users'), ['a' => 1]), 'update "users" set "a" = ?'],
            'delete' => [fn (Builder $b) => $b->getGrammar()->compileDelete($b->from('users')->where('id', 1)), 'delete from "users" where "id" = ?'],
            'delete sem where' => [fn (Builder $b) => $b->getGrammar()->compileDelete($b->from('users')), 'delete from "users"'],
            'exists' => [fn (Builder $b) => $b->getGrammar()->compileExists($b->from('users')->where('a', 1)), 'select exists(select * from "users" where "a" = ?) as "exists"'],
        ];
    }
}
