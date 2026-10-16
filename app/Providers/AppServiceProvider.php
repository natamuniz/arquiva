<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Classification;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\RetentionRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Model::shouldBeStrict(! app()->isProduction());

        // Nomes estáveis na trilha de auditoria, independentes do nome das classes PHP.
        Relation::enforceMorphMap([
            'user' => User::class,
            'classification' => Classification::class,
            'retention_rule' => RetentionRule::class,
            'document' => Document::class,
            'file' => DocumentFile::class,
        ]);

        Gate::define('manage-classification-plan', fn (User $user): bool => $user->role->canManageClassificationPlan());
        Gate::define('view-audit-trail', fn (User $user): bool => $user->role->canManageClassificationPlan());

        Route::pattern('classification', '[0-9]+');
        Route::pattern('retentionRule', '[0-9]+');

        $this->bindVisibleDocuments();
    }

    /**
     * Documentos e arquivos são buscados já filtrados pela visibilidade do usuário.
     * O que ele não pode ver responde 404 em qualquer rota, sem revelar que existe.
     */
    private function bindVisibleDocuments(): void
    {
        Route::bind('document', function (string $value): Document {
            $user = Auth::user();

            if (! $user instanceof User || ! Str::isUuid($value)) {
                throw new NotFoundHttpException;
            }

            return Document::query()->visibleTo($user)->findOrFail($value);
        });

        Route::bind('file', function (string $value): DocumentFile {
            $user = Auth::user();

            if (! $user instanceof User || ! Str::isUuid($value)) {
                throw new NotFoundHttpException;
            }

            return DocumentFile::query()
                ->whereHas('document', fn ($query) => $query->visibleTo($user))
                ->with('document')
                ->findOrFail($value);
        });
    }
}
