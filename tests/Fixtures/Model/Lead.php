<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures\Model;

use Slendie\Model\Builder;
use Slendie\Model\Model;
use Slendie\Model\Relations\HasMany;
use Slendie\Model\Relations\HasOne;

class Lead extends Model
{
    protected array $fillable = ['name', 'email', 'votes', 'status', 'meta', 'active', 'born_at', 'birthday', 'balance'];

    protected array $casts = [
        'votes' => 'int',
        'meta' => 'array',
        'active' => 'bool',
        'born_at' => 'datetime',
        'birthday' => 'date',
        'status' => LeadStatus::class,
        'balance' => 'decimal:2',
    ];

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function scopeActive(Builder $query, bool $active = true): Builder
    {
        return $query->where('active', '=', $active ? 1 : 0);
    }

    public function scopeVotedAbove(Builder $query, int $votes): Builder
    {
        return $query->where('votes', '>', $votes);
    }
}
