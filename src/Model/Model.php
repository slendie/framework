<?php

declare(strict_types=1);

namespace Slendie\Model;

use ArrayAccess;
use BackedEnum;
use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use JsonSerializable;
use LogicException;
use ReflectionMethod;
use ReflectionNamedType;
use Slendie\Container\Container;
use Slendie\Contracts\Arrayable;
use Slendie\Contracts\UrlRoutable;
use Slendie\Database\Connection;
use Slendie\Database\DatabaseManager;
use Slendie\Database\Pagination\Paginator;
use Slendie\Database\Query\Builder as QueryBuilder;
use Slendie\Foundation\Application;
use Slendie\Model\Relations\BelongsTo;
use Slendie\Model\Relations\HasMany;
use Slendie\Model\Relations\HasOne;
use Slendie\Model\Relations\Relation;
use Slendie\Support\Str;
use Stringable;

/**
 * Eloquent "light": objectos hidratados, mass-assignment protegido, casts, timestamps, relações lazy
 * `hasOne/hasMany/belongsTo`, soft deletes e proxies estáticos para o Query Builder.
 *
 * Sem eager loading, `belongsToMany`, relações polimórficas, factories nem observers em classe
 * (ver `docs/models.md`).
 *
 * @method static Builder where(\Closure|string|array<string, mixed> $column, mixed $operator = null, mixed $value = null)
 * @method static Builder whereIn(string $column, array<array-key, mixed> $values)
 * @method static Builder whereLike(string $column, string $value, bool $caseSensitive = false)
 * @method static Builder orderBy(string $column, string $direction = 'asc')
 * @method static Builder latest(string $column = 'created_at')
 * @method static Builder limit(int $value)
 * @method static static|null find(mixed $id)
 * @method static static findOrFail(mixed $id)
 * @method static Collection findMany(array<array-key, mixed> $ids)
 * @method static static|null first()
 * @method static static|null fetch()
 * @method static static|null firstWhere(\Closure|string|array<string, mixed> $column, mixed $operator = null, mixed $value = null)
 * @method static static firstOrCreate(array<string, mixed> $attributes, array<string, mixed> $values = [])
 * @method static static firstOrNew(array<string, mixed> $attributes, array<string, mixed> $values = [])
 * @method static static updateOrCreate(array<string, mixed> $attributes, array<string, mixed> $values = [])
 * @method static Collection get(list<string>|string $columns = ['*'])
 * @method static Collection fetchAll()
 * @method static int count(string $column = '*')
 * @method static Paginator paginate(int $perPage = 15, ?int $page = null, string $pageName = 'page')
 *
 * @implements ArrayAccess<string, mixed>
 *
 * @phpstan-consistent-constructor
 */
abstract class Model implements Arrayable, ArrayAccess, JsonSerializable, Stringable, UrlRoutable
{
    public const string CREATED_AT = 'created_at';

    public const string UPDATED_AT = 'updated_at';

    protected ?string $table = null;

    protected string $primaryKey = 'id';

    protected string $keyType = 'int';

    protected bool $incrementing = true;

    /** @var list<string> */
    protected array $fillable = [];

    /** @var list<string> */
    protected array $guarded = ['*'];

    /** @var array<string, string> */
    protected array $casts = [];

    /** @var list<string> */
    protected array $hidden = [];

    /** @var list<string> */
    protected array $visible = [];

    /** @var list<string> */
    protected array $appends = [];

    protected bool $timestamps = true;

    protected string $dateFormat = 'Y-m-d H:i:s';

    protected ?string $connection = null;

    /**
     * Ignorado: eager loading está fora de âmbito (usa `load()`).
     *
     * @var list<string>
     */
    protected array $with = [];

    /**
     * Estado dos atributos (valores no formato da base de dados); a declaração define os valores por omissão.
     *
     * @var array<string, mixed>
     */
    protected array $attributes = [];

    /** @var array<string, mixed> */
    protected array $original = [];

    /** @var array<string, mixed> */
    protected array $changes = [];

    /** @var array<string, mixed> */
    protected array $relations = [];

    public bool $exists = false;

    public bool $wasRecentlyCreated = false;

    // Estado estático -----------------------------------------------------------

    /** @var array<class-string, bool> */
    protected static array $booted = [];

    /** @var array<class-string, array<string, Closure>> */
    protected static array $globalScopes = [];

    /** @var array<class-string, list<string>> */
    protected static array $traitInitializers = [];

    /** @var array<string, bool> */
    protected static array $relationMethods = [];

    protected static bool $unguarded = false;

    protected static ?bool $preventsSilentlyDiscarding = null;

    /** @var (Closure(): DatabaseManager)|null */
    protected static ?Closure $connectionResolver = null;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(array $attributes = [])
    {
        static::bootIfNotBooted();

        $this->initializeTraits();
        $this->syncOriginal();
        $this->fill($attributes);
    }

    // -------------------------------------------------------------------------
    // Boot
    // -------------------------------------------------------------------------

    protected static function bootIfNotBooted(): void
    {
        if (isset(static::$booted[static::class])) {
            return;
        }

        static::$booted[static::class] = true;

        static::bootTraits();
        static::booted();
    }

    /** Hook para o model: registar scopes e eventos. */
    protected static function booted(): void
    {
    }

    protected static function bootTraits(): void
    {
        foreach (static::traitsOf(static::class) as $trait) {
            $method = 'boot'.Str::classBasename($trait);

            if (method_exists(static::class, $method)) {
                static::{$method}();
            }
        }
    }

    protected function initializeTraits(): void
    {
        $methods = static::$traitInitializers[static::class] ??= array_values(array_filter(
            array_map(static fn (string $trait): string => 'initialize'.Str::classBasename($trait), static::traitsOf(static::class)),
            static fn (string $method): bool => method_exists(static::class, $method),
        ));

        foreach ($methods as $method) {
            $this->{$method}();
        }
    }

    /**
     * Traits usadas pela classe, pelos seus pais e por outros traits.
     *
     * @return list<class-string>
     */
    protected static function traitsOf(string $class): array
    {
        $traits = [];

        foreach ([$class, ...array_values(class_parents($class) ?: [])] as $c) {
            foreach (class_uses($c) ?: [] as $trait) {
                foreach ([$trait, ...array_values(class_uses($trait) ?: [])] as $t) {
                    $traits[$t] = $t;
                }
            }
        }

        return array_values($traits);
    }

    public function usesSoftDeletes(): bool
    {
        return in_array(SoftDeletes::class, static::traitsOf(static::class), true);
    }

    /**
     * Esquece scopes, eventos e o estado de arranque (útil nos testes).
     */
    public static function clearBootedModels(): void
    {
        static::$booted = [];
        static::$globalScopes = [];
        static::$traitInitializers = [];
        static::$relationMethods = [];
        ModelEvents::flush();
    }

    // -------------------------------------------------------------------------
    // Ligação à base de dados
    // -------------------------------------------------------------------------

    /**
     * @param  (Closure(): DatabaseManager)|DatabaseManager|null  $resolver
     */
    public static function setConnectionResolver(Closure|DatabaseManager|null $resolver): void
    {
        static::$connectionResolver = $resolver instanceof DatabaseManager ? static fn (): DatabaseManager => $resolver : $resolver;
    }

    public static function resolveConnection(?string $name = null): Connection
    {
        if (static::$connectionResolver !== null) {
            return (static::$connectionResolver)()->connection($name);
        }

        $container = Container::getInstance();

        if ($container->bound(DatabaseManager::class)) {
            /** @var DatabaseManager $manager */
            $manager = $container->make(DatabaseManager::class);

            return $manager->connection($name);
        }

        throw new LogicException('Nenhuma ligação à base de dados disponível: regista o DatabaseServiceProvider ou usa Model::setConnectionResolver().');
    }

    public function getConnection(): Connection
    {
        return static::resolveConnection($this->connection);
    }

    public function getConnectionName(): ?string
    {
        return $this->connection;
    }

    public function setConnection(?string $name): static
    {
        $this->connection = $name;

        return $this;
    }

    public static function on(?string $connection = null): Builder
    {
        return (new static())->setConnection($connection)->newQuery();
    }

    // -------------------------------------------------------------------------
    // Configuração
    // -------------------------------------------------------------------------

    public function getTable(): string
    {
        return $this->table ?? Str::snake(Str::plural(Str::classBasename(static::class)));
    }

    public function setTable(string $table): static
    {
        $this->table = $table;

        return $this;
    }

    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    public function getKeyType(): string
    {
        return $this->keyType;
    }

    public function getIncrementing(): bool
    {
        return $this->incrementing;
    }

    public function getQualifiedKeyName(): string
    {
        return $this->qualifyColumn($this->getKeyName());
    }

    public function qualifyColumn(string $column): string
    {
        return str_contains($column, '.') ? $column : $this->getTable().'.'.$column;
    }

    public function getKey(): int|string|null
    {
        $key = $this->getAttribute($this->getKeyName());

        return is_int($key) || is_string($key) ? $key : (is_scalar($key) ? (string) $key : null);
    }

    public function usesTimestamps(): bool
    {
        return $this->timestamps;
    }

    public function getCreatedAtColumn(): string
    {
        return static::CREATED_AT;
    }

    public function getUpdatedAtColumn(): string
    {
        return static::UPDATED_AT;
    }

    public function freshTimestampString(): string
    {
        return (new DateTimeImmutable())->format($this->dateFormat);
    }

    /**
     * @return array<string, string>
     */
    public function getCasts(): array
    {
        $casts = $this->casts;

        if ($this->incrementing && ! isset($casts[$this->primaryKey])) {
            $casts[$this->primaryKey] = $this->keyType;
        }

        if ($this->timestamps) {
            $casts += [$this->getCreatedAtColumn() => 'datetime', $this->getUpdatedAtColumn() => 'datetime'];
        }

        return $casts;
    }

    // -------------------------------------------------------------------------
    // Mass assignment
    // -------------------------------------------------------------------------

    /**
     * @return list<string>
     */
    public function getFillable(): array
    {
        return $this->fillable;
    }

    /**
     * @param  list<string>  $guarded
     */
    public function guard(array $guarded): static
    {
        $this->guarded = $guarded;

        return $this;
    }

    public static function unguard(bool $state = true): void
    {
        static::$unguarded = $state;
    }

    public static function reguard(): void
    {
        static::$unguarded = false;
    }

    public static function isUnguarded(): bool
    {
        return static::$unguarded;
    }

    /**
     * `true`: lança `MassAssignmentException` ao descartar atributos; `false`: descarta em silêncio;
     * `null` (omissão): lança com `APP_DEBUG=true`, descarta em produção.
     */
    public static function preventSilentlyDiscardingAttributes(?bool $value = true): void
    {
        static::$preventsSilentlyDiscarding = $value;
    }

    protected static function shouldPreventDiscarding(): bool
    {
        if (static::$preventsSilentlyDiscarding !== null) {
            return static::$preventsSilentlyDiscarding;
        }

        $container = Container::getInstance();

        if ($container->bound('app')) {
            $app = $container->make('app');

            return $app instanceof Application && $app->isDebug();
        }

        return false;
    }

    public function isFillable(string $key): bool
    {
        if (static::$unguarded || in_array($key, $this->fillable, true)) {
            return true;
        }

        if ($this->isGuarded($key)) {
            return false;
        }

        return $this->fillable === [] && ! str_contains($key, '.') && ! str_starts_with($key, '_');
    }

    public function isGuarded(string $key): bool
    {
        return $this->guarded === ['*'] || in_array($key, $this->guarded, true);
    }

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws MassAssignmentException
     */
    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            $key = (string) $key;

            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            } elseif (static::shouldPreventDiscarding()) {
                throw new MassAssignmentException('O atributo ['.$key.'] não é fillable no model ['.static::class.']; adiciona-o a $fillable.');
            }
        }

        return $this;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function forceFill(array $attributes): static
    {
        $unguarded = static::$unguarded;
        static::$unguarded = true;

        try {
            return $this->fill($attributes);
        } finally {
            static::$unguarded = $unguarded;
        }
    }

    // -------------------------------------------------------------------------
    // Atributos
    // -------------------------------------------------------------------------

    public function getAttribute(string $key): mixed
    {
        if ($key === '') {
            return null;
        }

        if (array_key_exists($key, $this->attributes) || $this->hasGetMutator($key)) {
            return $this->getAttributeValue($key);
        }

        if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }

        if ($this->isRelation($key)) {
            return $this->getRelationValue($key);
        }

        return null;
    }

    protected function getAttributeValue(string $key): mixed
    {
        $value = $this->attributes[$key] ?? null;

        if ($this->hasGetMutator($key)) {
            return $this->{'get'.Str::studly($key).'Attribute'}($value);
        }

        return $this->hasCast($key) ? $this->castAttribute($key, $value) : $value;
    }

    public function setAttribute(string $key, mixed $value): static
    {
        if ($this->hasSetMutator($key)) {
            $this->{'set'.Str::studly($key).'Attribute'}($value);

            return $this;
        }

        $this->attributes[$key] = $this->hasCast($key) ? $this->castForStorage($key, $value) : $value;

        return $this;
    }

    protected function hasGetMutator(string $key): bool
    {
        return method_exists($this, 'get'.Str::studly($key).'Attribute');
    }

    protected function hasSetMutator(string $key): bool
    {
        return method_exists($this, 'set'.Str::studly($key).'Attribute');
    }

    /**
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function setRawAttributes(array $attributes, bool $sync = false): static
    {
        $this->attributes = $attributes;

        if ($sync) {
            $this->syncOriginal();
        }

        return $this;
    }

    /**
     * @param  list<string>|string  $keys
     * @return array<string, mixed>
     */
    public function only(array|string $keys): array
    {
        $result = [];

        foreach ((array) $keys as $key) {
            $result[$key] = $this->getAttribute($key);
        }

        return $result;
    }

    /**
     * @param  list<string>|string  $keys
     * @return array<string, mixed>
     */
    public function except(array|string $keys): array
    {
        $result = [];

        foreach (array_keys($this->attributes) as $key) {
            if (! in_array($key, (array) $keys, true)) {
                $result[$key] = $this->getAttribute($key);
            }
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // Casts
    // -------------------------------------------------------------------------

    protected function hasCast(string $key): bool
    {
        return array_key_exists($key, $this->getCasts());
    }

    protected function castType(string $key): string
    {
        $cast = $this->getCasts()[$key];

        return enum_exists($cast) ? $cast : strtolower(trim(explode(':', $cast, 2)[0]));
    }

    protected function castAttribute(string $key, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $type = $this->castType($key);

        if (enum_exists($type)) {
            return $this->toEnum($type, $value);
        }

        return match ($type) {
            'int', 'integer' => is_scalar($value) ? (int) $value : 0,
            'real', 'float', 'double' => is_scalar($value) ? (float) $value : 0.0,
            'string' => is_scalar($value) || $value instanceof Stringable ? (string) $value : '',
            'bool', 'boolean' => (bool) $value,
            'array', 'json' => $this->decodeJson($value, true),
            'object' => $this->decodeJson($value, false),
            'collection' => new Collection((array) $this->decodeJson($value, true)),
            'date' => $this->toDate($value)->setTime(0, 0),
            'datetime', 'immutable_datetime' => $this->toDate($value),
            'timestamp' => $value instanceof DateTimeInterface ? $value->getTimestamp() : (is_numeric($value) ? (int) $value : $this->toDate($value)->getTimestamp()),
            'decimal' => $this->toDecimal($key, $value),
            default => throw new LogicException("Cast [{$type}] não suportado no model [".static::class.'].'),
        };
    }

    protected function castForStorage(string $key, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $type = $this->castType($key);

        if (enum_exists($type)) {
            return $this->toEnum($type, $value)->value;
        }

        return match ($type) {
            'int', 'integer' => is_scalar($value) ? (int) $value : 0,
            'real', 'float', 'double' => is_scalar($value) ? (float) $value : 0.0,
            'string' => is_scalar($value) || $value instanceof Stringable ? (string) $value : '',
            'bool', 'boolean' => (int) (bool) $value,
            'array', 'json', 'object', 'collection' => json_encode(
                $value instanceof Collection ? $value->all() : ($value instanceof Arrayable ? $value->toArray() : $value),
                JSON_THROW_ON_ERROR,
            ),
            'date' => $this->toDate($value)->format('Y-m-d'),
            'datetime', 'immutable_datetime' => $this->toDate($value)->format($this->dateFormat),
            'timestamp' => $value instanceof DateTimeInterface ? $value->getTimestamp() : (is_numeric($value) ? (int) $value : $this->toDate($value)->getTimestamp()),
            'decimal' => $this->toDecimal($key, $value),
            default => throw new LogicException("Cast [{$type}] não suportado no model [".static::class.'].'),
        };
    }

    protected function decodeJson(mixed $value, bool $associative): mixed
    {
        return is_string($value) ? json_decode($value, $associative) : $value;
    }

    protected function toDate(mixed $value): DateTimeImmutable
    {
        return match (true) {
            $value instanceof DateTimeInterface => DateTimeImmutable::createFromInterface($value),
            is_int($value) => (new DateTimeImmutable('@'.$value))->setTimezone(new \DateTimeZone(date_default_timezone_get())),
            is_string($value) => new DateTimeImmutable($value),
            default => throw new InvalidArgumentException('Valor de data inválido.'),
        };
    }

    protected function toDecimal(string $key, mixed $value): string
    {
        $parts = explode(':', $this->getCasts()[$key], 2);

        return number_format(is_numeric($value) ? (float) $value : 0.0, (int) ($parts[1] ?? 2), '.', '');
    }

    /**
     * @param  class-string  $class
     */
    protected function toEnum(string $class, mixed $value): BackedEnum
    {
        if ($value instanceof $class) {
            /** @var BackedEnum $value */
            return $value;
        }

        if (! is_subclass_of($class, BackedEnum::class) || (! is_int($value) && ! is_string($value))) {
            throw new InvalidArgumentException("Valor inválido para o enum [{$class}].");
        }

        return $class::from($value);
    }

    // -------------------------------------------------------------------------
    // Dirty tracking
    // -------------------------------------------------------------------------

    public function syncOriginal(): static
    {
        $this->original = $this->attributes;

        return $this;
    }

    /**
     * @return array<string, mixed>|mixed
     */
    public function getOriginal(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->original : ($this->original[$key] ?? $default);
    }

    /**
     * @return array<string, mixed>
     */
    public function getDirty(): array
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if (! $this->originalIsEquivalent($key)) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    /**
     * @param  list<string>|string|null  $attributes
     */
    public function isDirty(array|string|null $attributes = null): bool
    {
        return $this->hasChanges($this->getDirty(), $attributes);
    }

    /**
     * @param  list<string>|string|null  $attributes
     */
    public function isClean(array|string|null $attributes = null): bool
    {
        return ! $this->isDirty($attributes);
    }

    /**
     * Algum atributo mudou na última gravação?
     *
     * @param  list<string>|string|null  $attributes
     */
    public function wasChanged(array|string|null $attributes = null): bool
    {
        return $this->hasChanges($this->changes, $attributes);
    }

    /**
     * @return array<string, mixed>
     */
    public function getChanges(): array
    {
        return $this->changes;
    }

    /**
     * @param  array<string, mixed>  $changes
     * @param  list<string>|string|null  $attributes
     */
    protected function hasChanges(array $changes, array|string|null $attributes): bool
    {
        if ($attributes === null) {
            return $changes !== [];
        }

        foreach ((array) $attributes as $attribute) {
            if (array_key_exists($attribute, $changes)) {
                return true;
            }
        }

        return false;
    }

    protected function originalIsEquivalent(string $key): bool
    {
        if (! array_key_exists($key, $this->original)) {
            return false;
        }

        $current = $this->attributes[$key] ?? null;
        $original = $this->original[$key];

        if ($current === $original) {
            return true;
        }

        if ($current === null || $original === null) {
            return false;
        }

        return is_numeric($current) && is_numeric($original) && strcmp((string) $current, (string) $original) === 0;
    }

    // -------------------------------------------------------------------------
    // Serialização
    // -------------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = [];

        foreach (array_keys($this->attributes) as $key) {
            $array[$key] = $this->serializeValue($key, $this->getAttribute($key));
        }

        foreach ($this->appends as $key) {
            $array[$key] = $this->serializeValue($key, $this->getAttribute($key));
        }

        foreach ($this->relations as $key => $relation) {
            $array[$key] = $relation instanceof Arrayable ? $relation->toArray() : $relation;
        }

        if ($this->visible !== []) {
            return array_intersect_key($array, array_flip($this->visible));
        }

        return array_diff_key($array, array_flip($this->hidden));
    }

    protected function serializeValue(string $key, mixed $value): mixed
    {
        return match (true) {
            $value instanceof DateTimeInterface => $value->format($this->hasCast($key) && $this->castType($key) === 'date' ? 'Y-m-d' : $this->dateFormat),
            $value instanceof BackedEnum => $value->value,
            $value instanceof Arrayable => $value->toArray(),
            default => $value,
        };
    }

    public function toJson(int $flags = 0): string
    {
        return json_encode($this->jsonSerialize(), $flags | JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __toString(): string
    {
        return $this->toJson();
    }

    /**
     * @param  list<string>|string  $attributes
     */
    public function makeHidden(array|string $attributes): static
    {
        $attributes = (array) $attributes;
        $this->hidden = array_values(array_unique([...$this->hidden, ...$attributes]));
        $this->visible = array_values(array_diff($this->visible, $attributes));

        return $this;
    }

    /**
     * @param  list<string>|string  $attributes
     */
    public function makeVisible(array|string $attributes): static
    {
        $attributes = (array) $attributes;
        $this->hidden = array_values(array_diff($this->hidden, $attributes));

        if ($this->visible !== []) {
            $this->visible = array_values(array_unique([...$this->visible, ...$attributes]));
        }

        return $this;
    }

    // -------------------------------------------------------------------------
    // Query
    // -------------------------------------------------------------------------

    public function newQuery(): Builder
    {
        return $this->newModelQuery();
    }

    public function newModelQuery(): Builder
    {
        return (new Builder($this->newBaseQueryBuilder()))->setModel($this);
    }

    protected function newBaseQueryBuilder(): QueryBuilder
    {
        return $this->getConnection()->table($this->getTable());
    }

    public static function query(): Builder
    {
        return (new static())->newQuery();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function newInstance(array $attributes = [], bool $exists = false): static
    {
        $model = new static($attributes);
        $model->exists = $exists;
        $model->setConnection($this->connection);

        return $model;
    }

    /**
     * Hidrata um model a partir de uma linha da base de dados (sem mass-assignment).
     *
     * @param  array<string, mixed>  $row
     */
    public function newFromBuilder(array $row): static
    {
        $model = $this->newInstance([], true);
        $model->setRawAttributes($row, true);

        return $model;
    }

    /**
     * @param  array<array-key, mixed>  $models
     */
    public function newCollection(array $models = []): Collection
    {
        return new Collection($models);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function make(array $attributes = []): static
    {
        return new static($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function create(array $attributes = []): static
    {
        $model = new static($attributes);
        $model->save();

        return $model;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function forceCreate(array $attributes): static
    {
        $model = (new static())->forceFill($attributes);
        $model->save();

        return $model;
    }

    /**
     * @param  list<string>|string  $columns
     */
    public static function all(array|string $columns = ['*']): Collection
    {
        return static::query()->get($columns);
    }

    /**
     * Apaga os models com estas chaves (um a um, para disparar eventos). Devolve quantos foram apagados.
     *
     * @param  array<array-key, mixed>|Collection|int|string  $ids
     */
    public static function destroy(array|Collection|int|string $ids): int
    {
        $ids = $ids instanceof Collection ? $ids->all() : (array) $ids;

        if ($ids === []) {
            return 0;
        }

        $count = 0;

        foreach (static::query()->whereIn((new static())->getKeyName(), array_values($ids))->get() as $model) {
            if ($model instanceof self && $model->delete()) {
                $count++;
            }
        }

        return $count;
    }

    // Global scopes -------------------------------------------------------------

    /**
     * @param  Closure(Builder, Model): mixed  $scope
     */
    public static function addGlobalScope(string $name, Closure $scope): void
    {
        static::$globalScopes[static::class][$name] = $scope;
    }

    /**
     * @return array<string, Closure>
     */
    public function getGlobalScopes(): array
    {
        $scopes = [];

        foreach (array_reverse([static::class, ...array_values(class_parents($this) ?: [])]) as $class) {
            $scopes = [...$scopes, ...(static::$globalScopes[$class] ?? [])];
        }

        return $scopes;
    }

    // -------------------------------------------------------------------------
    // Eventos
    // -------------------------------------------------------------------------

    /** @param Closure(static): mixed $callback */
    public static function creating(Closure $callback): void
    {
        ModelEvents::listen(static::class, 'creating', $callback);
    }

    /** @param Closure(static): mixed $callback */
    public static function created(Closure $callback): void
    {
        ModelEvents::listen(static::class, 'created', $callback);
    }

    /** @param Closure(static): mixed $callback */
    public static function updating(Closure $callback): void
    {
        ModelEvents::listen(static::class, 'updating', $callback);
    }

    /** @param Closure(static): mixed $callback */
    public static function updated(Closure $callback): void
    {
        ModelEvents::listen(static::class, 'updated', $callback);
    }

    /** @param Closure(static): mixed $callback */
    public static function saving(Closure $callback): void
    {
        ModelEvents::listen(static::class, 'saving', $callback);
    }

    /** @param Closure(static): mixed $callback */
    public static function saved(Closure $callback): void
    {
        ModelEvents::listen(static::class, 'saved', $callback);
    }

    /** @param Closure(static): mixed $callback */
    public static function deleting(Closure $callback): void
    {
        ModelEvents::listen(static::class, 'deleting', $callback);
    }

    /** @param Closure(static): mixed $callback */
    public static function deleted(Closure $callback): void
    {
        ModelEvents::listen(static::class, 'deleted', $callback);
    }

    /** @param Closure(static): mixed $callback */
    public static function restoring(Closure $callback): void
    {
        ModelEvents::listen(static::class, 'restoring', $callback);
    }

    /** @param Closure(static): mixed $callback */
    public static function restored(Closure $callback): void
    {
        ModelEvents::listen(static::class, 'restored', $callback);
    }

    /**
     * @return bool false se um listener cancelou a operação
     */
    protected function fireModelEvent(string $event): bool
    {
        return ModelEvents::fire(static::class, $event, $this);
    }

    // -------------------------------------------------------------------------
    // Persistência
    // -------------------------------------------------------------------------

    /**
     * @param  array{touch?: bool}  $options
     */
    public function save(array $options = []): bool
    {
        if (! $this->fireModelEvent('saving')) {
            return false;
        }

        $saved = $this->exists ? $this->performUpdate($options) : $this->performInsert($options);

        if ($saved) {
            $this->fireModelEvent('saved');
            $this->syncOriginal();
        }

        return $saved;
    }

    /**
     * @param  array{touch?: bool}  $options
     */
    protected function performUpdate(array $options): bool
    {
        if (! $this->isDirty()) {
            return true;
        }

        if (! $this->fireModelEvent('updating')) {
            return false;
        }

        if ($this->timestamps && ($options['touch'] ?? true)) {
            $this->updateTimestamps();
        }

        $dirty = $this->getDirty();

        if ($dirty !== []) {
            $this->newModelQuery()->getQuery()->where($this->getKeyName(), '=', $this->getKey())->update($dirty);
            $this->changes = $dirty;
        }

        $this->fireModelEvent('updated');

        return true;
    }

    /**
     * @param  array{touch?: bool}  $options
     */
    protected function performInsert(array $options): bool
    {
        if (! $this->fireModelEvent('creating')) {
            return false;
        }

        if ($this->timestamps && ($options['touch'] ?? true)) {
            $this->updateTimestamps();
        }

        $attributes = $this->attributes;
        $query = $this->newModelQuery()->getQuery();

        if ($this->incrementing) {
            $key = $this->getKeyName();

            if (($attributes[$key] ?? null) === null) {
                unset($attributes[$key]);
                $id = $query->insertGetId($attributes === [] ? [$key => null] : $attributes);

                $this->attributes[$key] = $this->keyType === 'int' ? (int) $id : $id;
            } else {
                $query->insert($attributes);
            }
        } elseif ($attributes !== []) {
            $query->insert($attributes);
        }

        $this->exists = true;
        $this->wasRecentlyCreated = true;
        $this->changes = $this->attributes;

        $this->fireModelEvent('created');

        return true;
    }

    protected function updateTimestamps(): void
    {
        $time = $this->freshTimestampString();
        $updatedAt = $this->getUpdatedAtColumn();
        $createdAt = $this->getCreatedAtColumn();

        if (! $this->isDirty($updatedAt)) {
            $this->setAttribute($updatedAt, $time);
        }

        if (! $this->exists && ($this->attributes[$createdAt] ?? null) === null) {
            $this->setAttribute($createdAt, $time);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(array $attributes = []): bool
    {
        return $this->exists && $this->fill($attributes)->save();
    }

    public function delete(): ?bool
    {
        if (! $this->exists) {
            return null;
        }

        if (! $this->fireModelEvent('deleting')) {
            return false;
        }

        $this->performDeleteOnModel();

        $this->fireModelEvent('deleted');

        return true;
    }

    public function forceDelete(): ?bool
    {
        return $this->delete();
    }

    protected function performDeleteOnModel(): void
    {
        $this->newModelQuery()->getQuery()->where($this->getKeyName(), '=', $this->getKey())->delete();

        $this->exists = false;
    }

    public function touch(): bool
    {
        if (! $this->timestamps || ! $this->exists) {
            return false;
        }

        $column = $this->getUpdatedAtColumn();
        $this->setAttribute($column, $this->freshTimestampString());

        $value = $this->attributes[$column];
        $this->newModelQuery()->getQuery()->where($this->getKeyName(), '=', $this->getKey())->update([$column => $value]);
        $this->original[$column] = $value;

        return true;
    }

    public function fresh(): ?static
    {
        if (! $this->exists) {
            return null;
        }

        $model = $this->newModelQuery()->withoutGlobalScopes()->where($this->getQualifiedKeyName(), '=', $this->getKey())->first();

        return $model instanceof static ? $model : null;
    }

    public function refresh(): static
    {
        if (! $this->exists) {
            return $this;
        }

        $fresh = $this->fresh() ?? throw (new ModelNotFoundException())->setModel(static::class, [$this->getKey()]);

        $this->attributes = $fresh->attributes;
        $this->relations = [];
        $this->changes = [];
        $this->syncOriginal();

        return $this;
    }

    /**
     * Cópia sem chave primária nem timestamps (ainda não gravada).
     *
     * @param  list<string>|null  $except
     */
    public function replicate(?array $except = null): static
    {
        $except = array_merge(
            [$this->getKeyName()],
            $this->timestamps ? [$this->getCreatedAtColumn(), $this->getUpdatedAtColumn()] : [],
            $except ?? [],
        );

        $model = $this->newInstance();
        $model->setRawAttributes(array_diff_key($this->attributes, array_flip($except)));

        return $model;
    }

    public function is(?self $model): bool
    {
        return $model !== null
            && $this->getKey() !== null
            && (string) $this->getKey() === (string) $model->getKey()
            && $this->getTable() === $model->getTable()
            && $this->getConnectionName() === $model->getConnectionName();
    }

    public function isNot(?self $model): bool
    {
        return ! $this->is($model);
    }

    // -------------------------------------------------------------------------
    // Relações (lazy, sem eager loading)
    // -------------------------------------------------------------------------

    /**
     * @template TRelated of Model
     *
     * @param  class-string<TRelated>  $related
     */
    public function hasOne(string $related, ?string $foreignKey = null, ?string $localKey = null): HasOne
    {
        $instance = new $related();

        return new HasOne($instance->newQuery(), $this, $foreignKey ?? $this->defaultForeignKey(), $localKey ?? $this->getKeyName());
    }

    /**
     * @template TRelated of Model
     *
     * @param  class-string<TRelated>  $related
     */
    public function hasMany(string $related, ?string $foreignKey = null, ?string $localKey = null): HasMany
    {
        $instance = new $related();

        return new HasMany($instance->newQuery(), $this, $foreignKey ?? $this->defaultForeignKey(), $localKey ?? $this->getKeyName());
    }

    /**
     * @template TRelated of Model
     *
     * @param  class-string<TRelated>  $related
     */
    public function belongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null): BelongsTo
    {
        $instance = new $related();

        return new BelongsTo(
            $instance->newQuery(),
            $this,
            $foreignKey ?? Str::snake(Str::classBasename($related)).'_id',
            $ownerKey ?? $instance->getKeyName(),
        );
    }

    protected function defaultForeignKey(): string
    {
        return Str::snake(Str::classBasename(static::class)).'_id';
    }

    /**
     * Um método público declarado numa subclasse, sem parâmetros obrigatórios e com `Relation` como tipo de retorno.
     */
    protected function isRelation(string $key): bool
    {
        $cacheKey = static::class.'::'.$key;

        if (! array_key_exists($cacheKey, static::$relationMethods)) {
            static::$relationMethods[$cacheKey] = false;

            if (method_exists($this, $key)) {
                $method = new ReflectionMethod($this, $key);
                $type = $method->getReturnType();

                static::$relationMethods[$cacheKey] = $method->isPublic()
                    && ! $method->isStatic()
                    && $method->getNumberOfRequiredParameters() === 0
                    && $method->getDeclaringClass()->getName() !== self::class
                    && $type instanceof ReflectionNamedType
                    && is_a($type->getName(), Relation::class, true);
            }
        }

        return static::$relationMethods[$cacheKey];
    }

    public function getRelationValue(string $key): mixed
    {
        if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }

        if (! $this->isRelation($key)) {
            return null;
        }

        /** @var Relation $relation */
        $relation = $this->{$key}();

        return $this->relations[$key] = $relation->getResults();
    }

    /**
     * Carrega explicitamente relações (uma query por relação; não há eager loading em lote).
     *
     * @param  list<string>|string  $relations
     *
     * @throws RelationNotFoundException
     */
    public function load(array|string $relations): static
    {
        foreach ((array) $relations as $name) {
            if (! $this->isRelation($name)) {
                throw RelationNotFoundException::make($this, $name);
            }

            /** @var Relation $relation */
            $relation = $this->{$name}();
            $this->relations[$name] = $relation->getResults();
        }

        return $this;
    }

    public function setRelation(string $relation, mixed $value): static
    {
        $this->relations[$relation] = $value;

        return $this;
    }

    public function relationLoaded(string $key): bool
    {
        return array_key_exists($key, $this->relations);
    }

    public function unsetRelation(string $relation): static
    {
        unset($this->relations[$relation]);

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRelations(): array
    {
        return $this->relations;
    }

    // -------------------------------------------------------------------------
    // UrlRoutable
    // -------------------------------------------------------------------------

    public function getRouteKey(): mixed
    {
        return $this->getAttribute($this->getRouteKeyName());
    }

    public function getRouteKeyName(): string
    {
        return $this->getKeyName();
    }

    public function resolveRouteBinding(mixed $value, ?string $field = null): ?static
    {
        $model = $this->newQuery()->where($this->qualifyColumn($field ?? $this->getRouteKeyName()), '=', $value)->first();

        return $model instanceof static ? $model : null;
    }

    // -------------------------------------------------------------------------
    // Métodos mágicos e ArrayAccess
    // -------------------------------------------------------------------------

    public function __get(string $key): mixed
    {
        return $this->getAttribute($key);
    }

    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    public function __isset(string $key): bool
    {
        return $this->getAttribute($key) !== null;
    }

    public function __unset(string $key): void
    {
        unset($this->attributes[$key], $this->relations[$key]);
    }

    /**
     * @param  array<array-key, mixed>  $parameters
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->newQuery()->{$method}(...$parameters);
    }

    /**
     * @param  array<array-key, mixed>  $parameters
     */
    public static function __callStatic(string $method, array $parameters): mixed
    {
        return (new static())->{$method}(...$parameters);
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->__isset((string) $offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->getAttribute((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->setAttribute((string) $offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->__unset((string) $offset);
    }
}
