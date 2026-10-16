<?php

declare(strict_types=1);

use App\Enums\Secrecy;
use App\Models\Classification;
use App\Models\Document;
use App\Models\Unit;

beforeEach(function (): void {
    $this->user = actingAsRole();
});

it('filtra pela classe incluindo descendentes e respeitando a visibilidade', function (): void {
    $c010 = Classification::factory()->create(['code' => '010']);
    $c0101 = Classification::factory()->childOf($c010, 1)->create();
    $c01011 = Classification::factory()->childOf($c0101, 1)->create();
    $c020 = Classification::factory()->create(['code' => '020']);
    $otherUnit = Unit::factory()->create();

    $mine = collect([$c010, $c0101, $c01011])->map(fn (Classification $c) => Document::factory()->inUnit($this->user->unit)->create(['classification_id' => $c->id]));
    Document::factory()->inUnit($this->user->unit)->create(['classification_id' => $c020->id]);
    Document::factory()->inUnit($otherUnit)->secrecy(Secrecy::Restrito)->create(['classification_id' => $c0101->id]);
    $public = Document::factory()->inUnit($otherUnit)->secrecy(Secrecy::Publico)->create(['classification_id' => $c01011->id]);

    $ids = collect($this->getJson("/api/v1/documents?classification_id={$c010->id}&per_page=100")->assertOk()->json('data'))->pluck('id');

    expect($ids->sort()->values()->all())->toBe($mine->pluck('id')->push($public->id)->sort()->values()->all());
});

it('busca texto sem diferenciar acentos e maiúsculas', function (): void {
    $hit = Document::factory()->inUnit($this->user->unit)->create(['title' => 'Edital de Licitação 5/2024']);
    $byParty = Document::factory()->inUnit($this->user->unit)->create(['title' => 'Outro', 'interested_party' => 'Comissão de LICITAÇÃO']);
    Document::factory()->inUnit($this->user->unit)->create(['title' => 'Ata de reunião', 'subject' => 'Pauta', 'interested_party' => 'Diretoria']);

    $ids = collect($this->getJson('/api/v1/documents?q=licitacao')->assertOk()->json('data'))->pluck('id')->sort()->values();

    expect($ids->all())->toBe(collect([$hit->id, $byParty->id])->sort()->values()->all());
});

it('trata % e _ da busca como texto', function (): void {
    Document::factory()->inUnit($this->user->unit)->create(['title' => 'Qualquer coisa']);

    $this->getJson('/api/v1/documents?q=%25')->assertOk()->assertJsonCount(0, 'data');
});

it('filtra por sigilo, status e intervalo de data de produção', function (): void {
    Document::factory()->inUnit($this->user->unit)->secrecy(Secrecy::Sigiloso)->captured()->create(['produced_at' => '2024-02-10']);
    Document::factory()->inUnit($this->user->unit)->secrecy(Secrecy::Sigiloso)->create(['produced_at' => '2024-02-11']);
    Document::factory()->inUnit($this->user->unit)->secrecy(Secrecy::Restrito)->captured()->create(['produced_at' => '2024-02-12']);
    Document::factory()->inUnit($this->user->unit)->secrecy(Secrecy::Sigiloso)->captured()->create(['produced_at' => '2023-01-01']);

    $this->getJson('/api/v1/documents?secrecy=sigiloso&status=capturado&produced_from=2024-01-01&produced_to=2024-12-31')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.produced_at', '2024-02-10');
});

it('pagina com 20 itens por padrão e limita per_page a 100', function (): void {
    Document::factory()->count(25)->inUnit($this->user->unit)->create();

    $this->getJson('/api/v1/documents')->assertOk()
        ->assertJsonCount(20, 'data')
        ->assertJsonPath('meta.total', 25);

    $this->getJson('/api/v1/documents?per_page=101')->assertUnprocessable();
});

it('ordena por data de produção decrescente por padrão e aceita sort', function (): void {
    Document::factory()->inUnit($this->user->unit)->create(['title' => 'B', 'produced_at' => '2024-01-01']);
    Document::factory()->inUnit($this->user->unit)->create(['title' => 'A', 'produced_at' => '2024-06-01']);

    expect(array_column($this->getJson('/api/v1/documents')->json('data'), 'title'))->toBe(['A', 'B'])
        ->and(array_column($this->getJson('/api/v1/documents?sort=produced_at')->json('data'), 'title'))->toBe(['B', 'A']);

    $this->getJson('/api/v1/documents?sort=password')->assertUnprocessable();
});
