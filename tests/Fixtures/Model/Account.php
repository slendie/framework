<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures\Model;

use Slendie\Model\Model;

class Account extends Model
{
    protected array $fillable = ['name', 'email', 'password'];

    protected array $hidden = ['password'];

    protected array $appends = ['display_name'];

    protected bool $timestamps = false;

    public function getNameAttribute(?string $value): ?string
    {
        return $value === null ? null : ucfirst($value);
    }

    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password'] = 'hashed:'.$value;
    }

    public function getDisplayNameAttribute(): string
    {
        return strtoupper((string) $this->getAttributes()['name']);
    }
}
