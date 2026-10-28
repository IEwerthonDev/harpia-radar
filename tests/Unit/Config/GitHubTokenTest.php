<?php

declare(strict_types=1);

use HarpiaRadar\Config\GitHubToken;
use HarpiaRadar\Config\MissingGitHubToken;

it('keeps the token value', function (): void {
    expect(GitHubToken::fromEnvironment('abc123')->value)->toBe('abc123');
});

it('fails with a clear message without a token', function (?string $value): void {
    GitHubToken::fromEnvironment($value);
})->throws(MissingGitHubToken::class, 'GITHUB_TOKEN')->with([
    'unset' => null,
    'empty' => '',
    'blank' => '   ',
]);

it('explains why the token is required', function (): void {
    try {
        GitHubToken::fromEnvironment(null);
    } catch (MissingGitHubToken $e) {
        expect($e->getMessage())->toContain('limite')->toContain('github.com/settings/personal-access-tokens');

        return;
    }

    throw new RuntimeException('Esperava MissingGitHubToken.');
});
