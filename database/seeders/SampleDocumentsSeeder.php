<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Documents\CaptureDocument;
use App\Enums\DocumentStatus;
use App\Enums\ProcessingStatus;
use App\Enums\Secrecy;
use App\Models\Classification;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\User;
use Database\Seeders\Support\SamplePdf;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SampleDocumentsSeeder extends Seeder
{
    /** [e-mail do autor, classe, título, interessado, data, sigilo, capturar?] — tudo fictício. */
    private const DOCUMENTS = [
        ['operador.adm@arquiva.test', '010.1', 'Regimento interno 2024', 'Diretoria Administrativa', '2024-02-05', Secrecy::Publico, true],
        ['operador.adm@arquiva.test', '010.2.2', 'Relatório de gestão do 1º semestre', 'Presidência', '2024-07-15', Secrecy::Publico, true],
        ['operador.adm@arquiva.test', '020.1', 'Termo de posse de servidora', 'Maria Exemplo', '2024-03-01', Secrecy::Restrito, true],
        ['operador.adm@arquiva.test', '020.2', 'Folha de pagamento de março', 'Setor de Pessoal', '2024-03-31', Secrecy::Sigiloso, true],
        ['operador.adm@arquiva.test', '030.1.1', 'Edital de licitação 5/2024 - material de escritório', 'Comissão de Licitação', '2024-04-10', Secrecy::Publico, true],
        ['operador.adm@arquiva.test', '030.1.2', 'Contrato de prestação de serviços de limpeza', 'Limpa Tudo Serviços Ltda.', '2024-05-20', Secrecy::Restrito, true],
        ['operador.adm@arquiva.test', '030.2', 'Inventário patrimonial anual', 'Setor de Patrimônio', '2024-12-15', Secrecy::Restrito, false],
        ['operador.adm@arquiva.test', '040.1', 'Nota de empenho 123/2024', 'Papelaria Fictícia ME', '2024-04-22', Secrecy::Publico, true],
        ['operador.jur@arquiva.test', '050.1', 'Parecer sobre aditivo contratual', 'Diretoria Administrativa', '2024-06-03', Secrecy::Restrito, true],
        ['operador.jur@arquiva.test', '050.2', 'Processo judicial 0001234-56 (fictício)', 'João da Silva', '2023-11-08', Secrecy::Sigiloso, true],
        ['operador.jur@arquiva.test', '050.1', 'Minuta de parecer sobre convênio', 'Presidência', '2024-09-12', Secrecy::Restrito, false],
    ];

    public function run(CaptureDocument $capture): void
    {
        $disk = Storage::disk((string) config('arquiva.uploads.disk'));

        foreach (self::DOCUMENTS as [$email, $code, $title, $party, $producedAt, $secrecy, $shouldCapture]) {
            $user = User::query()->where('email', $email)->firstOrFail();
            Auth::setUser($user);

            $document = Document::query()->create([
                'title' => $title,
                'subject' => "Documento de exemplo: {$title}.",
                'interested_party' => $party,
                'produced_at' => $producedAt,
                'classification_id' => Classification::query()->where('code', $code)->value('id'),
                'unit_id' => $user->unit_id,
                'secrecy' => $secrecy,
                'status' => DocumentStatus::Rascunho,
                'created_by' => $user->id,
            ]);

            $pages = (strlen($title) % 4) + 1;
            $contents = SamplePdf::make($pages, $title);
            $id = (string) Str::uuid();
            $path = "documents/{$document->id}/{$id}";
            $disk->put($path, $contents);

            DocumentFile::query()->forceCreate([
                'id' => $id,
                'document_id' => $document->id,
                'original_name' => Str::slug($title).'.pdf',
                'storage_path' => $path,
                'mime_type' => 'application/pdf',
                'size_bytes' => strlen($contents),
                'sha256' => hash('sha256', $contents),
                'processing_status' => ProcessingStatus::Processado,
                'page_count' => $pages,
                'created_by' => $user->id,
            ]);

            if ($shouldCapture) {
                $capture->handle($document);
            }
        }

        Auth::forgetUser();
    }
}
