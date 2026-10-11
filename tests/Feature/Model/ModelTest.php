<?php

declare(strict_types=1);

use Slendie\Database\Connection;
use Slendie\Database\Pagination\Paginator;
use Slendie\Model\Collection;
use Slendie\Model\MassAssignmentException;
use Slendie\Model\Model;
use Slendie\Model\ModelNotFoundException;
use Slendie\Model\RelationNotFoundException;
use Slendie\Tests\Fixtures\Model\Account;
use Slendie\Tests\Fixtures\Model\Category;
use Slendie\Tests\Fixtures\Model\Customized;
use Slendie\Tests\Fixtures\Model\Lead;
use Slendie\Tests\Fixtures\Model\LeadStatus;
use Slendie\Tests\Fixtures\Model\ModelHelpers;
use Slendie\Tests\Fixtures\Model\Note;
use Slendie\Tests\Fixtures\Model\Person;
use Slendie\Tests\Fixtures\Model\Post;
use Slendie\Tests\Fixtures\Model\Profile;
use Slendie\Tests\Fixtures\Model\Token;

beforeEach(function (): void {
    $this->db = ModelHelpers::connect();
});

afterEach(function (): void {
    Model::setConnectionResolver(null);
    Model::preventSilentlyDiscardingAttributes(null);
});

/**
 * @return list<string>
 */
function sqlLog(Connection $db): array
{
    return array_column($db->getQueryLog(), 'sql');
}

// -----------------------------------------------------------------------------
// Convenções e configuração
// -----------------------------------------------------------------------------

it('deduz o nome da tabela por convenção e permite override', function (): void {
    expect((new Lead())->getTable())->toBe('leads')
        ->and((new Category())->getTable())->toBe('categories')
        ->and((new Person())->getTable())->toBe('people')
        ->and((new Customized())->getTable())->toBe('my_table');
});

it('sem ligação configurada, falha com uma mensagem clara', function (): void {
    Model::setConnectionResolver(null);

    Lead::all();
})->throws(LogicException::class, 'Nenhuma ligação');

// -----------------------------------------------------------------------------
// Mass assignment
// -----------------------------------------------------------------------------

it('fill respeita $fillable e descarta o resto em silêncio', function (): void {
    Model::preventSilentlyDiscardingAttributes(false);

    $lead = new Lead(['name' => 'Ana', 'is_admin' => 1]);

    expect($lead->name)->toBe('Ana')
        ->and($lead->getAttributes())->toBe(['name' => 'Ana']);
});

it('atributos não permitidos lançam MassAssignmentException quando pedido', function (): void {
    Model::preventSilentlyDiscardingAttributes(true);

    new Lead(['is_admin' => 1]);
})->throws(MassAssignmentException::class, 'is_admin');

it('em APP_DEBUG=true lança, em produção descarta', function (): void {
    Slendie\Tests\Fixtures\Http\HttpHelpers::app(); // app.debug = true

    expect(fn () => new Lead(['is_admin' => 1]))->toThrow(MassAssignmentException::class);

    config(['app.debug' => false]);

    expect((new Lead(['is_admin' => 1]))->getAttributes())->toBe([]);
});

it('um model sem $fillable e com $guarded = [*] bloqueia tudo; forceFill ignora a protecção', function (): void {
    Model::preventSilentlyDiscardingAttributes(false);

    $category = new Category(['name' => 'X']);

    expect($category->getAttributes())->toBe([])
        ->and($category->forceFill(['name' => 'X'])->name)->toBe('X');
});

it('unguard / reguard e $guarded vazio', function (): void {
    Model::unguard();
    $category = new Category(['name' => 'X']);
    Model::reguard();

    expect($category->name)->toBe('X')
        ->and((new Profile(['bio' => 'b']))->bio)->toBe('b')
        ->and((new Lead())->isFillable('name'))->toBeTrue()
        ->and((new Lead())->isFillable('id'))->toBeFalse()
        ->and((new Lead())->getFillable())->toContain('email');
});

// -----------------------------------------------------------------------------
// Casts
// -----------------------------------------------------------------------------

it('casts: round-trip set → base de dados → get', function (): void {
    $lead = Lead::create([
        'name' => 'Ana',
        'votes' => '7',
        'meta' => ['a' => 1, 'b' => [2, 3]],
        'active' => false,
        'born_at' => '2026-05-09 10:30:00',
        'birthday' => '2000-02-03 15:00:00',
        'status' => LeadStatus::Won,
        'balance' => 12.3456,
    ]);

    $fresh = Lead::find($lead->id);

    expect($fresh->votes)->toBe(7)
        ->and($fresh->meta)->toBe(['a' => 1, 'b' => [2, 3]])
        ->and($fresh->active)->toBeFalse()
        ->and($fresh->born_at)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($fresh->born_at->format('Y-m-d H:i:s'))->toBe('2026-05-09 10:30:00')
        ->and($fresh->birthday->format('Y-m-d H:i:s'))->toBe('2000-02-03 00:00:00')
        ->and($fresh->status)->toBe(LeadStatus::Won)
        ->and($fresh->balance)->toBe('12.35')
        ->and($fresh->getAttributes()['meta'])->toBeString()
        ->and($fresh->getAttributes()['status'])->toBe('won');
});

it('casts: float, string, object, collection, timestamp', function (): void {
    $model = new class () extends Model {
        protected array $guarded = [];

        protected bool $timestamps = false;

        protected array $casts = ['f' => 'float', 's' => 'string', 'o' => 'object', 'c' => 'collection', 't' => 'timestamp', 'i' => 'immutable_datetime'];
    };

    $model->f = '1.5';
    $model->s = 12;
    $model->o = ['x' => 1];
    $model->c = [1, 2];
    $model->t = '2026-01-01 00:00:00';
    $model->i = new DateTime('2026-01-02 03:04:05');

    expect($model->f)->toBe(1.5)
        ->and($model->s)->toBe('12')
        ->and($model->o)->toBeObject()
        ->and($model->o->x)->toBe(1)
        ->and($model->c)->toBeInstanceOf(Collection::class)
        ->and($model->c->count())->toBe(2)
        ->and($model->t)->toBe(strtotime('2026-01-01 00:00:00'))
        ->and($model->i)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($model->i->format('Y-m-d H:i:s'))->toBe('2026-01-02 03:04:05');
});

it('casts: null mantém-se null e um cast desconhecido falha', function (): void {
    $lead = new Lead(['votes' => null, 'meta' => null]);

    expect($lead->votes)->toBeNull()->and($lead->meta)->toBeNull();

    $bad = new class () extends Model {
        protected array $casts = ['secret' => 'encrypted'];
    };
    $bad->forceFill(['secret' => 'x']);
})->throws(LogicException::class, 'não suportado');

it('accessor, mutator, appends e hidden em toArray / toJson', function (): void {
    $account = Account::create(['name' => 'ana', 'email' => 'a@x.pt', 'password' => 'secret']);

    expect($account->name)->toBe('Ana')
        ->and($account->getAttributes()['password'])->toBe('hashed:secret')
        ->and($account->toArray())->toBe(['name' => 'Ana', 'email' => 'a@x.pt', 'id' => $account->id, 'display_name' => 'ANA'])
        ->and(json_decode($account->toJson(), true))->not->toHaveKey('password')
        ->and($account->makeVisible('password')->toArray())->toHaveKey('password')
        ->and($account->makeHidden('email')->toArray())->not->toHaveKey('email');
});

it('$visible limita os campos serializados', function (): void {
    $model = new class () extends Model {
        protected array $guarded = [];

        protected bool $timestamps = false;

        protected array $visible = ['a'];
    };
    $model->a = 1;
    $model->b = 2;

    expect($model->toArray())->toBe(['a' => 1])
        ->and((string) $model)->toBe('{"a":1}')
        ->and($model->makeVisible('b')->toArray())->toBe(['a' => 1, 'b' => 2]);
});

it('toArray serializa datas, enums e relações carregadas', function (): void {
    $lead = Lead::create(['name' => 'Ana', 'status' => LeadStatus::New, 'birthday' => '2000-01-02', 'born_at' => '2000-01-02 03:04:05']);
    $lead->notes()->create(['body' => 'olá']);
    $lead->load('notes');

    $array = Lead::find($lead->id)->load('notes')->toArray();

    expect($array['status'])->toBe('new')
        ->and($array['birthday'])->toBe('2000-01-02')
        ->and($array['born_at'])->toBe('2000-01-02 03:04:05')
        ->and($array['notes'][0]['body'])->toBe('olá');
});

// -----------------------------------------------------------------------------
// Persistência
// -----------------------------------------------------------------------------

it('create / save / update / delete / refresh / fresh / replicate', function (): void {
    $lead = Lead::create(['name' => 'Ana', 'email' => 'ana@x.pt']);

    expect($lead->exists)->toBeTrue()
        ->and($lead->wasRecentlyCreated)->toBeTrue()
        ->and($lead->id)->toBeInt()
        ->and($lead->getKey())->toBe($lead->id);

    expect($lead->update(['name' => 'Ana S.']))->toBeTrue()
        ->and(Lead::find($lead->id)->name)->toBe('Ana S.');

    $lead->name = 'Outro';
    expect($lead->fresh()->name)->toBe('Ana S.')
        ->and($lead->name)->toBe('Outro')
        ->and($lead->refresh()->name)->toBe('Ana S.');

    $copy = $lead->replicate();
    expect($copy->exists)->toBeFalse()
        ->and($copy->id)->toBeNull()
        ->and($copy->created_at)->toBeNull()
        ->and($copy->name)->toBe('Ana S.');
    $copy->save();
    expect(Lead::count())->toBe(2);

    expect($lead->delete())->toBeTrue()
        ->and($lead->exists)->toBeFalse()
        ->and(Lead::count())->toBe(1)
        ->and($lead->delete())->toBeNull();
});

it('dirty tracking: só as colunas alteradas vão no UPDATE', function (): void {
    $lead = Lead::create(['name' => 'Ana', 'email' => 'ana@x.pt', 'votes' => 1]);
    $this->db->table('leads')->update(['updated_at' => '2000-01-01 00:00:00']);
    $lead = Lead::find($lead->id);

    expect($lead->isClean())->toBeTrue()->and($lead->isDirty())->toBeFalse();

    $lead->votes = 2;
    $lead->name = 'Ana';

    expect($lead->isDirty())->toBeTrue()
        ->and($lead->isDirty('votes'))->toBeTrue()
        ->and($lead->isDirty(['name', 'email']))->toBeFalse()
        ->and($lead->getDirty())->toBe(['votes' => 2])
        ->and($lead->getOriginal('votes'))->toBe(1);

    $this->db->enableQueryLog();
    $lead->save();

    $updates = array_values(array_filter(sqlLog($this->db), static fn (string $sql): bool => str_starts_with($sql, 'update')));

    expect($updates)->toHaveCount(1)
        ->and($updates[0])->toContain('"votes"')->toContain('"updated_at"')
        ->and($updates[0])->not->toContain('"name"')->not->toContain('"email"')
        ->and($lead->wasChanged('votes'))->toBeTrue()
        ->and($lead->wasChanged('name'))->toBeFalse()
        ->and($lead->isClean())->toBeTrue();
});

it('um save sem alterações não executa queries', function (): void {
    $lead = Lead::create(['name' => 'Ana']);

    $this->db->enableQueryLog();
    $lead->save();

    expect(sqlLog($this->db))->toBe([]);
});

it('timestamps são criados e actualizados; $timestamps = false desliga-os', function (): void {
    $lead = Lead::create(['name' => 'Ana']);

    expect($lead->created_at)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($lead->updated_at)->toBeInstanceOf(DateTimeImmutable::class);

    $this->db->table('leads')->where('id', '=', $lead->id)->update(['updated_at' => '2000-01-01 00:00:00']);
    $lead = Lead::find($lead->id);
    $lead->votes = 9;
    $lead->save();

    expect($lead->updated_at->format('Y') >= '2026')->toBeTrue()
        ->and($lead->touch())->toBeTrue();

    $profile = Profile::create(['lead_id' => 1, 'bio' => 'x']);
    expect($profile->getAttributes())->not->toHaveKey('created_at')
        ->and($profile->touch())->toBeFalse();
});

it('eventos: creating que devolve false cancela; saved corre em insert e update', function (): void {
    $log = [];

    Lead::saving(function (Lead $lead) use (&$log): void {
        $log[] = 'saving';
    });
    Lead::saved(function (Lead $lead) use (&$log): void {
        $log[] = 'saved';
    });
    Lead::created(function (Lead $lead) use (&$log): void {
        $log[] = 'created';
    });
    Lead::updated(function (Lead $lead) use (&$log): void {
        $log[] = 'updated';
    });
    Lead::deleted(function (Lead $lead) use (&$log): void {
        $log[] = 'deleted';
    });

    $lead = Lead::create(['name' => 'Ana']);
    $lead->update(['name' => 'Ana S.']);
    $lead->delete();

    expect($log)->toBe(['saving', 'created', 'saved', 'saving', 'updated', 'saved', 'deleted']);

    Lead::creating(fn (Lead $lead): bool => $lead->name !== 'Bloqueado');

    $blocked = new Lead(['name' => 'Bloqueado']);

    expect($blocked->save())->toBeFalse()
        ->and($blocked->exists)->toBeFalse()
        ->and(Lead::count())->toBe(0);
});

it('updating e deleting a false cancelam a operação', function (): void {
    Lead::updating(fn (): bool => false);
    Lead::deleting(fn (): bool => false);

    $lead = Lead::create(['name' => 'Ana']);
    $lead->name = 'X';

    expect($lead->save())->toBeFalse()
        ->and($lead->delete())->toBeFalse()
        ->and(Lead::count())->toBe(1)
        ->and(Lead::find($lead->id)->name)->toBe('Ana');
});

it('is() / isNot()', function (): void {
    $a = Lead::create(['name' => 'A']);
    $b = Lead::create(['name' => 'B']);

    expect($a->is(Lead::find($a->id)))->toBeTrue()
        ->and($a->isNot($b))->toBeTrue()
        ->and($a->is(null))->toBeFalse()
        ->and($a->is(new Lead()))->toBeFalse();
});

it('ArrayAccess e magic __isset / __unset', function (): void {
    $lead = new Lead(['name' => 'Ana']);

    expect($lead['name'])->toBe('Ana')
        ->and(isset($lead['name']))->toBeTrue()
        ->and(isset($lead->email))->toBeFalse();

    $lead['email'] = 'a@x.pt';
    unset($lead->name);

    expect($lead->email)->toBe('a@x.pt')->and($lead->name)->toBeNull();

    unset($lead['email']);
    expect($lead->email)->toBeNull();
});

// -----------------------------------------------------------------------------
// Estáticos e query
// -----------------------------------------------------------------------------

it('critério de aceitação: where()->fetch() e whereLike()->fetchAll() em classe e em instância', function (): void {
    Lead::create(['name' => 'Ana Silva', 'email' => '123456789']);
    Lead::create(['name' => 'Rui Silva', 'email' => '987654321']);
    Lead::create(['name' => 'Zé Costa']);

    $leads = new Lead();

    expect($leads->where('email', '=', '123456789')->fetch())->toBeInstanceOf(Lead::class)
        ->and(Lead::where('email', '=', '123456789')->fetch()->name)->toBe('Ana Silva')
        ->and(Lead::where('email', '123456789')->first()->name)->toBe('Ana Silva')
        ->and($leads->whereLike('name', 'SILVA')->fetchAll())->toHaveCount(2)
        ->and($leads->whereLike('name', 'SILVA', true)->fetchAll())->toHaveCount(0)
        ->and(Lead::whereLike('name', '%silva%')->get()->pluck('name')->all())->toBe(['Ana Silva', 'Rui Silva'])
        ->and(Lead::where('name', 'Nobody')->fetch())->toBeNull();
});

it('find, findMany, findOrFail, all, first, firstWhere, count', function (): void {
    $a = Lead::create(['name' => 'A', 'votes' => 1]);
    $b = Lead::create(['name' => 'B', 'votes' => 2]);

    expect(Lead::find($a->id)->name)->toBe('A')
        ->and(Lead::find(999))->toBeNull()
        ->and(Lead::find([$a->id, $b->id]))->toHaveCount(2)
        ->and(Lead::findMany([$b->id])->first()->name)->toBe('B')
        ->and(Lead::findMany([]))->toHaveCount(0)
        ->and(Lead::findOrFail($b->id)->name)->toBe('B')
        ->and(Lead::all())->toHaveCount(2)
        ->and(Lead::all()->first())->toBeInstanceOf(Lead::class)
        ->and(Lead::first()->name)->toBe('A')
        ->and(Lead::firstWhere('name', 'B')->id)->toBe($b->id)
        ->and(Lead::count())->toBe(2)
        ->and(Lead::where('votes', '>', 1)->count())->toBe(1)
        ->and(Lead::query()->exists())->toBeTrue()
        ->and(Lead::query()->pluck('name'))->toBe(['A', 'B'])
        ->and(Lead::query()->value('name'))->toBe('A');
});

it('findOrFail lança ModelNotFoundException com classe e ids', function (): void {
    try {
        Lead::findOrFail(77);
        $this->fail('Devia ter lançado.');
    } catch (ModelNotFoundException $e) {
        expect($e->getModel())->toBe(Lead::class)
            ->and($e->getIds())->toBe([77])
            ->and($e->getMessage())->toContain(Lead::class)->toContain('77');
    }

    expect(fn () => Lead::query()->firstOrFail())->toThrow(ModelNotFoundException::class)
        ->and(fn () => Lead::findOrFail([1, 2]))->toThrow(ModelNotFoundException::class);
});

it('firstOrCreate, firstOrNew, updateOrCreate, destroy, forceCreate', function (): void {
    $a = Lead::firstOrCreate(['email' => 'a@x.pt'], ['name' => 'A']);
    $again = Lead::firstOrCreate(['email' => 'a@x.pt'], ['name' => 'Outro']);
    $new = Lead::firstOrNew(['email' => 'b@x.pt'], ['name' => 'B']);

    expect($again->id)->toBe($a->id)
        ->and($again->name)->toBe('A')
        ->and($new->exists)->toBeFalse()
        ->and($new->name)->toBe('B')
        ->and(Lead::count())->toBe(1);

    $updated = Lead::updateOrCreate(['email' => 'a@x.pt'], ['name' => 'A2']);
    $created = Lead::updateOrCreate(['email' => 'c@x.pt'], ['name' => 'C']);

    expect($updated->id)->toBe($a->id)
        ->and(Lead::find($a->id)->name)->toBe('A2')
        ->and($created->exists)->toBeTrue()
        ->and(Lead::count())->toBe(2);

    $forced = Lead::forceCreate(['name' => 'F', 'id' => 50]);
    expect($forced->id)->toBe(50);

    expect(Lead::destroy([$a->id, $created->id]))->toBe(2)
        ->and(Lead::destroy(50))->toBe(1)
        ->and(Lead::destroy([]))->toBe(0)
        ->and(Lead::count())->toBe(0);
});

it('update e delete em massa via query (updated_at incluído)', function (): void {
    Lead::create(['name' => 'A', 'votes' => 1]);
    Lead::create(['name' => 'B', 'votes' => 5]);

    $this->db->enableQueryLog();

    expect(Lead::where('votes', '>', 2)->update(['status' => 'won']))->toBe(1)
        ->and(sqlLog($this->db)[0])->toContain('"updated_at"')
        ->and(Lead::where('votes', '>', 2)->delete())->toBe(1)
        ->and(Lead::count())->toBe(1);
});

it('paginate devolve um Paginator com Models', function (): void {
    foreach (range(1, 5) as $i) {
        Lead::create(['name' => "L{$i}", 'votes' => $i]);
    }

    $page = Lead::orderBy('votes')->paginate(2, 2);

    expect($page)->toBeInstanceOf(Paginator::class)
        ->and($page->total())->toBe(5)
        ->and($page->lastPage())->toBe(3)
        ->and($page->items()[0])->toBeInstanceOf(Lead::class)
        ->and(array_map(static fn (Lead $l): string => $l->name, $page->items()))->toBe(['L3', 'L4']);

    $simple = Lead::orderBy('votes')->simplePaginate(2, 3);
    expect($simple->items())->toHaveCount(1)->and($simple->hasMorePages())->toBeFalse();
});

it('chunk e cursor hidratam models', function (): void {
    foreach (range(1, 5) as $i) {
        Lead::create(['name' => "L{$i}"]);
    }

    $sizes = [];
    Lead::query()->chunk(2, function (Collection $leads) use (&$sizes): void {
        $sizes[] = count($leads);
        expect($leads->first())->toBeInstanceOf(Lead::class);
    });

    $names = [];
    foreach (Lead::query()->cursor() as $lead) {
        $names[] = $lead->name;
    }

    expect($sizes)->toBe([2, 2, 1])->and($names)->toBe(['L1', 'L2', 'L3', 'L4', 'L5']);
});

it('on() usa outra ligação', function (): void {
    expect(Lead::on('other')->getModel()->getConnectionName())->toBe('other');

    expect(fn () => Lead::on('other')->get())->toThrow(Slendie\Database\QueryException::class);
});

it('with() está fora de âmbito', function (): void {
    Lead::with('notes');
})->throws(LogicException::class, 'Eager loading');

it('scopes locais', function (): void {
    Lead::create(['name' => 'A', 'active' => true, 'votes' => 1]);
    Lead::create(['name' => 'B', 'active' => false, 'votes' => 9]);
    Lead::create(['name' => 'C', 'active' => true, 'votes' => 9]);

    expect(Lead::active()->count())->toBe(2)
        ->and(Lead::active(false)->first()->name)->toBe('B')
        ->and(Lead::active()->votedAbove(5)->get()->pluck('name')->all())->toBe(['C'])
        ->and((new Lead())->active()->count())->toBe(2);
});

it('scopes globais respeitam grupos or e podem ser removidos', function (): void {
    Lead::addGlobalScope('activeOnly', fn ($q) => $q->where('active', '=', 1));

    Lead::create(['name' => 'A', 'active' => true, 'votes' => 1]);
    Lead::create(['name' => 'B', 'active' => false, 'votes' => 9]);

    expect(Lead::count())->toBe(1)
        ->and(Lead::where('votes', 1)->orWhere('votes', 9)->count())->toBe(1)
        ->and(Lead::withoutGlobalScope('activeOnly')->count())->toBe(2)
        ->and(Lead::query()->withoutGlobalScopes()->count())->toBe(2)
        ->and(Lead::query()->scopes())->toBe(['activeOnly'])
        ->and(Lead::find(2))->toBeNull();
});

it('SoftDeletes: delete marca, withTrashed, onlyTrashed, restore, forceDelete', function (): void {
    $post = Post::create(['title' => 'T', 'slug' => 't']);
    Post::create(['title' => 'U', 'slug' => 'u']);

    expect($post->delete())->toBeTrue()
        ->and($post->trashed())->toBeTrue()
        ->and($post->exists)->toBeTrue()
        ->and(Post::count())->toBe(1)
        ->and(Post::withTrashed()->count())->toBe(2)
        ->and(Post::onlyTrashed()->count())->toBe(1)
        ->and(Post::onlyTrashed()->first()->title)->toBe('T')
        ->and(Post::withoutTrashed()->count())->toBe(1)
        ->and(Post::find($post->id))->toBeNull()
        ->and($post->fresh()->trashed())->toBeTrue();

    expect($post->restore())->toBeTrue()
        ->and($post->trashed())->toBeFalse()
        ->and(Post::count())->toBe(2);

    expect($post->forceDelete())->toBeTrue()
        ->and($post->exists)->toBeFalse()
        ->and(Post::withTrashed()->count())->toBe(1);

    Post::where('slug', 'u')->delete();
    expect(Post::count())->toBe(0)->and(Post::withTrashed()->count())->toBe(1);

    Post::onlyTrashed()->forceDelete();
    expect(Post::withTrashed()->count())->toBe(0);
});

it('SoftDeletes: restoring / restored disparam eventos', function (): void {
    $log = [];
    Post::restoring(function () use (&$log): void {
        $log[] = 'restoring';
    });
    Post::restored(function () use (&$log): void {
        $log[] = 'restored';
    });

    $post = Post::create(['title' => 'T']);
    $post->delete();
    $post->restore();

    expect($log)->toBe(['restoring', 'restored']);
});

it('HasUuids gera a chave ao criar', function (): void {
    $token = Token::create(['label' => 'a']);
    $other = Token::create(['label' => 'b']);

    expect($token->id)->toBeString()
        ->and(Slendie\Support\Str::isUuid($token->id))->toBeTrue()
        ->and($token->id)->not->toBe($other->id)
        ->and($token->getIncrementing())->toBeFalse()
        ->and(Token::find($token->id)->label)->toBe('a')
        ->and(Token::find($token->id)->is($token))->toBeTrue();
});

// -----------------------------------------------------------------------------
// Relações
// -----------------------------------------------------------------------------

it('hasMany / belongsTo / hasOne com FKs por convenção, lazy por propriedade e por método', function (): void {
    $lead = Lead::create(['name' => 'Ana']);
    $other = Lead::create(['name' => 'Rui']);

    $note = $lead->notes()->create(['body' => 'um']);
    $lead->notes()->create(['body' => 'dois']);
    $other->notes()->create(['body' => 'tres']);
    $lead->profile()->create(['bio' => 'bio']);

    expect($note->lead_id)->toBe($lead->id)
        ->and($lead->relationLoaded('notes'))->toBeFalse()
        ->and($lead->notes)->toBeInstanceOf(Collection::class)
        ->and($lead->notes)->toHaveCount(2)
        ->and($lead->relationLoaded('notes'))->toBeTrue()
        ->and($lead->notes()->where('body', 'dois')->get())->toHaveCount(1)
        ->and($lead->notes()->count())->toBe(2)
        ->and(Note::find($note->id)->lead->name)->toBe('Ana')
        ->and(Note::find($note->id)->owner->name)->toBe('Ana')
        ->and($lead->profile->bio)->toBe('bio')
        ->and($other->profile)->toBeNull();
});

it('relações com chaves explícitas', function (): void {
    $lead = Lead::create(['name' => 'Ana']);
    Note::forceCreate(['body' => 'x', 'lead_id' => $lead->id]);

    $notes = $lead->hasMany(Note::class, 'lead_id', 'id')->get();
    $owner = Note::first()->belongsTo(Lead::class, 'lead_id', 'id')->first();

    expect($notes)->toHaveCount(1)->and($owner->id)->toBe($lead->id);
});

it('save / saveMany / make via relação', function (): void {
    $lead = Lead::create(['name' => 'Ana']);

    $made = $lead->notes()->make(['body' => 'm']);
    expect($made->exists)->toBeFalse()->and($made->lead_id)->toBe($lead->id);

    $lead->notes()->save(new Note(['body' => 's']));
    $saved = $lead->notes()->saveMany([new Note(['body' => 'a']), new Note(['body' => 'b'])]);

    expect($saved)->toHaveCount(2)->and($lead->notes()->count())->toBe(3);
});

it('associate / dissociate', function (): void {
    $lead = Lead::create(['name' => 'Ana']);
    $note = Note::forceCreate(['body' => 'x']);

    expect($note->lead)->toBeNull();

    $note->lead()->associate($lead);
    expect($note->lead_id)->toBe($lead->id)->and($note->isDirty('lead_id'))->toBeTrue();
    $note->save();

    expect(Note::find($note->id)->lead->id)->toBe($lead->id);

    $loaded = Note::find($note->id);
    $loaded->lead;
    $loaded->lead()->dissociate();

    expect($loaded->lead_id)->toBeNull()->and($loaded->lead)->toBeNull();
});

it('load() / setRelation / unsetRelation / relationLoaded', function (): void {
    $lead = Lead::create(['name' => 'Ana']);
    $lead->notes()->create(['body' => 'x']);

    $fresh = Lead::find($lead->id);

    expect($fresh->relationLoaded('notes'))->toBeFalse()
        ->and($fresh->load(['notes', 'profile']))->toBe($fresh)
        ->and($fresh->relationLoaded('notes'))->toBeTrue()
        ->and($fresh->relationLoaded('profile'))->toBeTrue()
        ->and($fresh->unsetRelation('notes')->relationLoaded('notes'))->toBeFalse()
        ->and($fresh->setRelation('notes', new Collection())->notes)->toHaveCount(0);

    $fresh->load('inexistente');
})->throws(RelationNotFoundException::class);

it('só métodos que devolvem Relation contam como relações', function (): void {
    $lead = Lead::create(['name' => 'Ana']);

    // `save` existe, mas não é uma relação: __get devolve null.
    expect($lead->save)->toBeNull()
        ->and($lead->getRelationValue('toArray'))->toBeNull();
});

// -----------------------------------------------------------------------------
// Collection de models
// -----------------------------------------------------------------------------

it('as queries devolvem Collection com toJson (hidden aplicado), modelKeys, find, load e fresh', function (): void {
    $a = Account::create(['name' => 'ana', 'email' => 'a@x.pt', 'password' => 'p']);
    Account::create(['name' => 'rui', 'email' => 'r@x.pt', 'password' => 'p']);

    $accounts = Account::all();

    expect(json_decode($accounts->toJson(), true))->toHaveCount(2)
        ->and($accounts->toJson())->not->toContain('password')
        ->and(json_encode($accounts))->not->toContain('password')
        ->and($accounts->modelKeys())->toBe([1, 2])
        ->and($accounts->find($a->id)->email)->toBe('a@x.pt')
        ->and($accounts->find($a)->email)->toBe('a@x.pt')
        ->and($accounts->contains($a))->toBeTrue()
        ->and($accounts->fresh()->first()->is($a))->toBeTrue();

    $leads = Lead::query()->get();
    expect($leads)->toHaveCount(0);

    $lead = Lead::create(['name' => 'Ana']);
    $lead->notes()->create(['body' => 'x']);

    $all = Lead::all()->load('notes');
    expect($all->first()->relationLoaded('notes'))->toBeTrue();
});

// -----------------------------------------------------------------------------
// Route-model binding
// -----------------------------------------------------------------------------

function bindingApp(): Slendie\Foundation\Application
{
    $app = Slendie\Tests\Fixtures\Http\HttpHelpers::app([
        'database.default' => 'main',
        'database.connections.main' => ['driver' => 'sqlite', 'database' => ':memory:'],
    ]);

    $connection = $app->make(Slendie\Database\DatabaseManager::class)->connection();
    $connection->statement('create table posts (id integer primary key autoincrement, title varchar(100), slug varchar(100), deleted_at datetime null, created_at datetime null, updated_at datetime null)');
    $connection->statement('create table leads (id integer primary key autoincrement, name varchar(100), email varchar(100) null, votes int not null default 0, status varchar(20) null, meta text null, active int not null default 1, born_at datetime null, birthday date null, balance varchar(20) null, created_at datetime null, updated_at datetime null)');

    return $app;
}

it('route-model binding: instância, 404 e {lead:slug}', function (): void {
    Model::clearBootedModels();
    $app = bindingApp();

    Post::create(['title' => 'Olá', 'slug' => 'ola']);
    Lead::create(['name' => 'Ana']);

    $router = Slendie\Tests\Fixtures\Http\HttpHelpers::router($app);
    $router->get('/leads/{lead}', fn (Lead $lead): string => 'lead:'.$lead->name);
    $router->get('/posts/{post:slug}', fn (Post $post): string => 'post:'.$post->title);
    $router->get('/posts-id/{post}', fn (Post $post): string => 'post:'.$post->title);

    $send = fn (string $uri) => Slendie\Tests\Fixtures\Http\HttpHelpers::send($app, 'GET', $uri);

    expect($send('/leads/1')->getContent())->toBe('lead:Ana')
        ->and($send('/leads/99')->getStatusCode())->toBe(404)
        ->and($send('/posts/ola')->getContent())->toBe('post:Olá')
        ->and($send('/posts/nope')->getStatusCode())->toBe(404)
        ->and($send('/posts-id/1')->getContent())->toBe('post:Olá');

    Post::first()->delete();

    expect($send('/posts-id/1')->getStatusCode())->toBe(404);
});

it('o router gera URLs com {param:campo} e getRouteKey', function (): void {
    $app = Slendie\Tests\Fixtures\Http\HttpHelpers::app();
    $router = Slendie\Tests\Fixtures\Http\HttpHelpers::router($app);
    $router->get('/posts/{post:slug}', fn () => 'x')->name('posts.show');

    $post = (new Post())->forceFill(['id' => 3, 'slug' => 'ola']);

    expect(route('posts.show', ['post' => 'ola'], false))->toBe('/posts/ola')
        ->and(route('posts.show', ['post' => $post], false))->toBe('/posts/3');
});

it('onlyTrashed()->restore() repõe em massa; restore sem SoftDeletes falha', function (): void {
    Post::create(['title' => 'A']);
    Post::create(['title' => 'B']);
    Post::query()->delete();

    expect(Post::count())->toBe(0)
        ->and(Post::onlyTrashed()->restore())->toBe(2)
        ->and(Post::count())->toBe(2);

    expect(fn () => Lead::query()->restore())->toThrow(LogicException::class);
});
