<?php

declare(strict_types=1);

use HarpiaRadar\Validation\SchemaValidator;
use HarpiaRadar\Validation\SchemaViolation;

beforeEach(function (): void {
    $this->validator = new SchemaValidator();
    $this->schema = projectRoot('schema/projects.schema.json');
});

/**
 * @param list<SchemaViolation> $violations
 * @return list<array{0: string, 1: string}>
 */
function pathsAndKeywords(array $violations): array
{
    return array_map(
        static fn (SchemaViolation $v): array => [implode('.', $v->path), $v->keyword],
        $violations,
    );
}

it('accepts a valid catalog', function (): void {
    expect($this->validator->validate(validCatalogData(), $this->schema))->toBe([]);
});

it('points a missing required field to its own path', function (): void {
    $data = validCatalogData();
    unset($data['projects'][1]['category']);

    expect(pathsAndKeywords($this->validator->validate($data, $this->schema)))
        ->toBe([['projects.1.category', 'required']]);
});

it('rejects an id that is not kebab-case', function (): void {
    $data = validCatalogData();
    $data['projects'][0]['id'] = 'Sped_NFe';

    expect(pathsAndKeywords($this->validator->validate($data, $this->schema)))
        ->toBe([['projects.0.id', 'pattern']]);
});

it('rejects a repo without a known forge prefix', function (): void {
    $data = validCatalogData();
    $data['projects'][0]['repo'] = 'nfephp-org/sped-nfe';

    expect(pathsAndKeywords($this->validator->validate($data, $this->schema)))
        ->toBe([['projects.0.repo', 'pattern']]);
});

it('points an unknown field to its own path', function (): void {
    $data = validCatalogData();
    $data['projects'][2]['stars'] = 10;

    expect(pathsAndKeywords($this->validator->validate($data, $this->schema)))
        ->toBe([['projects.2.stars', 'additionalProperties']]);
});

it('rejects a value of the wrong type', function (): void {
    $data = validCatalogData();
    $data['projects'][0]['tags'] = 'nf-e';

    expect(pathsAndKeywords($this->validator->validate($data, $this->schema)))
        ->toBe([['projects.0.tags', 'type']]);
});

it('reports every violation at once', function (): void {
    $data = validCatalogData();
    unset($data['projects'][0]['name'], $data['projects'][2]['description']);
    $data['categories'][1]['id'] = 'Pagamentos';

    expect(pathsAndKeywords($this->validator->validate($data, $this->schema)))
        ->toEqualCanonicalizing([
            ['categories.1.id', 'pattern'],
            ['projects.0.name', 'required'],
            ['projects.2.description', 'required'],
        ]);
});

it('writes messages in portuguese', function (): void {
    $data = validCatalogData();
    unset($data['projects'][1]['category']);

    expect($this->validator->validate($data, $this->schema)[0]->message)
        ->toBe('campo obrigatório ausente');
});
