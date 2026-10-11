<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures\Model;

use Slendie\Model\Model;
use Slendie\Model\Relations\BelongsTo;

class Note extends Model
{
    protected array $fillable = ['body'];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }
}
