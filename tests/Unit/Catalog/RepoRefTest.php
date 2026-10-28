<?php

declare(strict_types=1);

use HarpiaRadar\Catalog\RepoRef;

it('parses a github reference', function (): void {
    $ref = RepoRef::parse('github:nfephp-org/sped-nfe');

    expect($ref->forge)->toBe('github')
        ->and($ref->owner)->toBe('nfephp-org')
        ->and($ref->name)->toBe('sped-nfe')
        ->and((string) $ref)->toBe('github:nfephp-org/sped-nfe');
});

it('rejects malformed references', function (string $value): void {
    RepoRef::parse($value);
})->throws(InvalidArgumentException::class)->with([
    'unknown forge' => 'gitlab:owner/name',
    'no prefix' => 'nfephp-org/sped-nfe',
    'no name' => 'github:nfephp-org',
    'extra segment' => 'github:a/b/c',
    'empty owner' => 'github:/sped-nfe',
]);
