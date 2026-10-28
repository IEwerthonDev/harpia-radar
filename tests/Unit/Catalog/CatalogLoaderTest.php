<?php

declare(strict_types=1);

use HarpiaRadar\Catalog\CatalogLoader;
use HarpiaRadar\Catalog\InvalidCatalog;
use HarpiaRadar\Validation\ValidationError;

function loader(): CatalogLoader
{
    return new CatalogLoader(projectRoot('schema/projects.schema.json'));
}

/**
 * @return list<ValidationError>
 */
function loadErrors(string $path): array
{
    try {
        loader()->load($path);
    } catch (InvalidCatalog $e) {
        return $e->errors;
    }

    throw new RuntimeException('Esperava InvalidCatalog.');
}

it('loads every project of a valid catalog', function (): void {
    $catalog = loader()->load(fixturePath('catalog/valid.yaml'));

    expect($catalog->projects)->toHaveCount(3)
        ->and($catalog->categories)->toHaveCount(2);

    $first = $catalog->projects[0];
    expect($first->id)->toBe('sped-nfe')
        ->and($first->name)->toBe('sped-nfe')
        ->and((string) $first->repo)->toBe('github:nfephp-org/sped-nfe')
        ->and($first->category)->toBe('fiscal')
        ->and($first->description)->toBe('Emissão de NF-e e NFC-e.')
        ->and($first->language)->toBe('PHP')
        ->and($first->replaces)->toBe('Emissores fiscais pagos')
        ->and($first->tags)->toBe(['nf-e', 'nfc-e']);

    $second = $catalog->projects[1];
    expect($second->language)->toBeNull()
        ->and($second->replaces)->toBeNull()
        ->and($second->tags)->toBe([]);

    expect($catalog->categories[1]->id)->toBe('payments')
        ->and($catalog->categories[1]->name)->toBe('Pagamentos, boleto e Pix');
});

it('says which project and field are missing', function (string $field): void {
    $data = validCatalogData();
    unset($data['projects'][1][$field]);

    $errors = loadErrors(writeCatalog($data));

    expect($errors)->toHaveCount(1)
        ->and($errors[0]->project)->toBe('laravel-boleto')
        ->and($errors[0]->field)->toBe($field)
        ->and($errors[0]->describe())
        ->toBe("Projeto laravel-boleto, campo {$field}: campo obrigatório ausente.");
})->with(['name', 'repo', 'category', 'description']);

it('names a project without id by its position', function (): void {
    $data = validCatalogData();
    unset($data['projects'][1]['id']);

    expect(loadErrors(writeCatalog($data))[0]->describe())
        ->toBe('Projeto #2, campo id: campo obrigatório ausente.');
});

it('explains format errors in plain words', function (string $field, string $value, string $message): void {
    $data = validCatalogData();
    $data['projects'][0][$field] = $value;

    expect(loadErrors(writeCatalog($data))[0]->describe())->toBe($message);
})->with([
    'id' => ['id', 'SpedNFe', 'Projeto #1, campo id: use letras minúsculas, números e hífens (ex.: sped-nfe).'],
    'repo' => ['repo', 'nfephp-org/sped-nfe', 'Projeto sped-nfe, campo repo: use o formato github:dono/repositório.'],
]);

it('reports schema and catalog problems together', function (): void {
    $data = validCatalogData();
    unset($data['projects'][0]['description']);
    $data['projects'][1]['category'] = 'boletos';
    $data['projects'][2]['id'] = 'laravel-boleto';

    expect(array_map(static fn (ValidationError $e): string => $e->describe(), loadErrors(writeCatalog($data))))
        ->toEqualCanonicalizing([
            'Projeto sped-nfe, campo description: campo obrigatório ausente.',
            'Projeto laravel-boleto, campo category: a categoria "boletos" não está declarada em categories.',
            'Projeto laravel-boleto (#3), campo id: o id "laravel-boleto" já é usado pelo projeto #2.',
        ]);
});

it('fails clearly when the file does not exist', function (): void {
    expect(loadErrors('/nao/existe/projects.yaml')[0]->describe())
        ->toBe('Arquivo não encontrado: /nao/existe/projects.yaml.');
});

it('fails clearly on invalid YAML', function (): void {
    expect(loadErrors(fixturePath('catalog/invalid-syntax.yaml'))[0]->describe())
        ->toStartWith('YAML inválido:');
});

it('fails clearly when the YAML is not a map', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'radar-catalog-');
    file_put_contents($path, "- só uma lista\n");

    expect(loadErrors($path)[0]->describe())
        ->toBe('O arquivo precisa ter as chaves categories e projects.');
});
