<?php

declare(strict_types=1);

use Shared\Support\SearchPattern;
use Tests\TestCase;

uses(TestCase::class);

it('escapes the LIKE wildcards and the escape character itself', function (): void {
    expect(SearchPattern::escape('A_B%'))->toBe('A\\_B\\%')
        ->and(SearchPattern::escape('A\\B'))->toBe('A\\\\B');
});

it('treats the underscores in the real permission vocabulary as literals', function (): void {
    expect(SearchPattern::escape('VIEW_ANY_ROLE'))->toBe('VIEW\_ANY\_ROLE');
});
