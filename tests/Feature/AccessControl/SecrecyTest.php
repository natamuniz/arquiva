<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Enums\Secrecy;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\Unit;

beforeEach(function (): void {
    $this->otherUnit = Unit::factory()->create();
});

describe('metadados', function (): void {
    it('esconde documento restrito de outra unidade: 404 e fora da listagem', function (): void {
        actingAsRole(Role::Operador);
        $document = Document::factory()->inUnit($this->otherUnit)->secrecy(Secrecy::Restrito)->create();

        $this->getJson("/api/v1/documents/{$document->id}")
            ->assertNotFound()
            ->assertJsonPath('message', 'Recurso não encontrado.');

        $this->getJson('/api/v1/documents')->assertOk()->assertJsonCount(0, 'data');
    });

    it('mostra documento público de outra unidade', function (): void {
        actingAsRole(Role::Operador);
        $document = Document::factory()->inUnit($this->otherUnit)->secrecy(Secrecy::Publico)->create();

        $this->getJson("/api/v1/documents/{$document->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $document->id);
    });

    it('mostra documento sigiloso da própria unidade mesmo com credencial menor', function (): void {
        $user = actingAsRole(Role::Consulta, clearance: Secrecy::Publico);
        $document = Document::factory()->inUnit($user->unit)->secrecy(Secrecy::Sigiloso)->create();

        $this->getJson("/api/v1/documents/{$document->id}")->assertOk();
    });

    it('responde 404 para id que não é UUID', function (): void {
        actingAsRole();

        $this->getJson('/api/v1/documents/123')->assertNotFound();
    });
});

describe('404 x 403', function (): void {
    it('responde 404 ao editar documento restrito de outra unidade', function (): void {
        actingAsRole(Role::Operador);
        $document = Document::factory()->inUnit($this->otherUnit)->secrecy(Secrecy::Restrito)->create();

        $this->patchJson("/api/v1/documents/{$document->id}", ['title' => 'Alterado'])->assertNotFound();
    });

    it('responde 403 ao editar documento público de outra unidade', function (): void {
        actingAsRole(Role::Operador);
        $document = Document::factory()->inUnit($this->otherUnit)->secrecy(Secrecy::Publico)->create();

        $this->patchJson("/api/v1/documents/{$document->id}", ['title' => 'Alterado'])->assertForbidden();
    });

    it('responde 404 em todas as rotas de documento invisível', function (): void {
        actingAsRole(Role::Operador);
        $document = Document::factory()->inUnit($this->otherUnit)->secrecy(Secrecy::Sigiloso)->create();
        $file = DocumentFile::factory()->create(['document_id' => $document->id]);

        $this->deleteJson("/api/v1/documents/{$document->id}")->assertNotFound();
        $this->postJson("/api/v1/documents/{$document->id}/capture")->assertNotFound();
        $this->getJson("/api/v1/documents/{$document->id}/history")->assertNotFound();
        $this->postJson("/api/v1/documents/{$document->id}/files")->assertNotFound();
        $this->getJson("/api/v1/files/{$file->id}")->assertNotFound();
        $this->getJson("/api/v1/files/{$file->id}/download")->assertNotFound();
        $this->deleteJson("/api/v1/files/{$file->id}")->assertNotFound();
    });
});

describe('download por credencial', function (): void {
    it('vê os metadados mas não baixa documento sigiloso com credencial restrito', function (): void {
        $user = actingAsRole(Role::Operador, clearance: Secrecy::Restrito);
        $document = Document::factory()->inUnit($user->unit)->secrecy(Secrecy::Sigiloso)->create();
        $file = DocumentFile::factory()->create(['document_id' => $document->id]);

        $this->getJson("/api/v1/documents/{$document->id}")->assertOk();
        $this->get("/api/v1/files/{$file->id}/download")->assertForbidden();
    });

    it('baixa o arquivo com credencial sigiloso da mesma unidade', function (): void {
        $user = actingAsRole(Role::Operador, clearance: Secrecy::Sigiloso);
        $document = Document::factory()->inUnit($user->unit)->secrecy(Secrecy::Sigiloso)->create();
        $file = DocumentFile::factory()->create(['document_id' => $document->id, 'original_name' => 'contrato.pdf']);

        $this->get("/api/v1/files/{$file->id}/download")
            ->assertOk()
            ->assertDownload('contrato.pdf');
    });

    it('baixa arquivo de documento público de outra unidade com credencial público', function (): void {
        actingAsRole(Role::Consulta, clearance: Secrecy::Publico);
        $document = Document::factory()->inUnit($this->otherUnit)->secrecy(Secrecy::Publico)->create();
        $file = DocumentFile::factory()->create(['document_id' => $document->id]);

        $this->get("/api/v1/files/{$file->id}/download")->assertOk();
    });

    it('deixa o admin baixar qualquer arquivo', function (): void {
        actingAsRole(Role::Admin, clearance: Secrecy::Publico);
        $document = Document::factory()->inUnit($this->otherUnit)->secrecy(Secrecy::Sigiloso)->create();
        $file = DocumentFile::factory()->create(['document_id' => $document->id]);

        $this->get("/api/v1/files/{$file->id}/download")->assertOk();
    });
});
