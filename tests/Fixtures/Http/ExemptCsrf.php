<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures\Http;

use Slendie\Http\Middleware\VerifyCsrfToken;

class ExemptCsrf extends VerifyCsrfToken
{
    protected array $except = ['webhooks/*'];
}
