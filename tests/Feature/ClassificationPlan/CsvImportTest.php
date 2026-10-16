<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Classification;
use Illuminate\Http\UploadedFile;

function csvUpload(string $contents, string $name = 'plano.csv'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $contents);
}

beforeEach(function (): void {
    actingAsRole(Role::Gestor);
});

it('importa CSV válido com 50 classes, com pais depois dos filhos', function (): void {
    $lines = ['code,name,parent_code'];

    // Filhos primeiro, de propósito.
    foreach (range(1, 4) as $root) {
        foreach (range(1, 10) as $child) {
            $lines[] = sprintf('0%d0.%d,Subclasse %d.%d,0%d0', $root, $child, $root, $child, $root);
        }
    }
    foreach (range(1, 4) as $root) {
        $lines[] = sprintf('0%d0,Classe %d,', $root, $root);
    }
    foreach (range(1, 6) as $leaf) {
        $lines[] = "010.1.{$leaf},Item {$leaf},010.1";
    }

    expect(count($lines) - 1)->toBe(50);

    $this->post('/api/v1/classifications/import', ['file' => csvUpload(implode("\n", $lines))], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('imported', 50);

    expect(Classification::query()->count())->toBe(50)
        ->and(Classification::query()->where('code', '010.1.6')->value('depth'))->toBe(3);

    $tree = $this->getJson('/api/v1/classifications')->json('data');
    expect($tree)->toHaveCount(4)
        ->and($tree[0]['children'])->toHaveCount(10)
        ->and($tree[0]['children'][0]['children'])->toHaveCount(6);
});

it('recusa o CSV inteiro e lista os erros por linha', function (): void {
    $lines = ['code,name,parent_code', '010,Administração,'];
    foreach (range(1, 4) as $i) {
        $lines[] = "010.{$i},Sub {$i},010";
    }
    $lines[] = '010.5,Pai inexistente,999';          // linha 7
    foreach (range(6, 9) as $i) {
        $lines[] = "010.{$i},Sub {$i},010";            // linhas 8 a 11
    }
    $lines[] = '010.2,Código repetido,010';           // linha 12

    $this->post('/api/v1/classifications/import', ['file' => csvUpload(implode("\n", $lines))], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'O arquivo tem 2 linha(s) com erro. Nenhuma classe foi importada.')
        ->assertJsonStructure(['errors' => ['linha 7', 'linha 12']])
        ->assertJsonCount(2, 'errors');

    expect(Classification::query()->count())->toBe(0);
});

it('aceita CSV do Excel: BOM e separador ponto e vírgula', function (): void {
    $contents = "\xEF\xBB\xBFcode;name;parent_code\r\n010;Administração;\r\n010.1;\"Normas; regulamentos\";010\r\n";

    $this->post('/api/v1/classifications/import', ['file' => csvUpload($contents)], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('imported', 2);

    expect(Classification::query()->where('code', '010.1')->value('name'))->toBe('Normas; regulamentos');
});

it('trata código já existente no plano como erro da linha', function (): void {
    Classification::factory()->create(['code' => '010']);

    $this->post('/api/v1/classifications/import', ['file' => csvUpload("code,name,parent_code\n010,Repetida,\n020,Nova,")], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonStructure(['errors' => ['linha 2']]);

    expect(Classification::query()->count())->toBe(1);
});

it('aceita pai que já existe no plano', function (): void {
    Classification::factory()->create(['code' => '010', 'depth' => 1]);

    $this->post('/api/v1/classifications/import', ['file' => csvUpload("code,name,parent_code\n010.1,Filha,010")], ['Accept' => 'application/json'])
        ->assertCreated();

    expect(Classification::query()->where('code', '010.1')->value('depth'))->toBe(2);
});

it('recusa cabeçalho inválido', function (): void {
    $this->post('/api/v1/classifications/import', ['file' => csvUpload("codigo,nome\n010,X")], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonStructure(['errors' => ['linha 1']]);
});

it('impede o operador de importar', function (): void {
    actingAsRole(Role::Operador);

    $this->post('/api/v1/classifications/import', ['file' => csvUpload("code,name,parent_code\n010,X,")], ['Accept' => 'application/json'])
        ->assertForbidden();
});
