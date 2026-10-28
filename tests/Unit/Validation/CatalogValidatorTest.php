<?php

declare(strict_types=1);

use HarpiaRadar\Validation\CatalogValidator;
use HarpiaRadar\Validation\ValidationError;

/**
 * @param list<ValidationError> $errors
 * @return list<string>
 */
function describeAll(array $errors): array
{
    return array_map(static fn (ValidationError $e): string => $e->describe(), $errors);
}

it('accepts a valid catalog', function (): void {
    expect((new CatalogValidator())->validate(validCatalogData()))->toBe([]);
});

it('rejects duplicated project ids', function (): void {
    $data = validCatalogData();
    $data['projects'][2]['id'] = 'sped-nfe';

    expect(describeAll((new CatalogValidator())->validate($data)))->toBe([
        'Projeto sped-nfe (#3), campo id: o id "sped-nfe" já é usado pelo projeto #1.',
    ]);
});

it('rejects duplicated category ids', function (): void {
    $data = validCatalogData();
    $data['categories'][1]['id'] = 'fiscal';
    $data['projects'][1]['category'] = 'fiscal';
    $data['projects'][2]['category'] = 'fiscal';

    expect(describeAll((new CatalogValidator())->validate($data)))->toBe([
        'Categoria #2, campo id: o id "fiscal" já é usado pela categoria #1.',
    ]);
});

it('rejects a project pointing to an undeclared category', function (): void {
    $data = validCatalogData();
    $data['projects'][1]['category'] = 'boletos';

    expect(describeAll((new CatalogValidator())->validate($data)))->toBe([
        'Projeto laravel-boleto, campo category: a categoria "boletos" não está declarada em categories.',
    ]);
});

it('ignores fields the schema already rejects', function (): void {
    $data = validCatalogData();
    unset($data['projects'][0]['id'], $data['projects'][1]['category']);
    $data['categories'][0]['id'] = 42;

    expect((new CatalogValidator())->validate($data))->toBe([]);
});
