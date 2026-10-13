<?php

declare(strict_types=1);

return [
    'default' => 'sqlite',
    'connections' => [
        'sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true],
    ],
];
