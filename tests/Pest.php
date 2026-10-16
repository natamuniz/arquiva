<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Enums\Secrecy;
use App\Models\Classification;
use App\Models\RetentionRule;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function (): void {
        Storage::fake((string) config('arquiva.uploads.disk'));
    })
    ->in('Feature');

pest()->extend(TestCase::class)->in('Arch', 'Unit');

/** Cria um usuário com o perfil, a unidade e a credencial informados. */
function makeUser(Role $role = Role::Operador, ?Unit $unit = null, Secrecy $clearance = Secrecy::Restrito): User
{
    return User::factory()
        ->role($role)
        ->clearance($clearance)
        ->state(['unit_id' => ($unit ?? Unit::factory()->create())->id])
        ->create();
}

/** Cria o usuário e autentica as próximas requisições com ele. */
function actingAsRole(Role $role = Role::Operador, ?Unit $unit = null, Secrecy $clearance = Secrecy::Restrito): User
{
    $user = makeUser($role, $unit, $clearance);
    Sanctum::actingAs($user);

    return $user;
}

/** Classe ativa com regra de temporalidade vigente, pronta para receber documentos capturáveis. */
function classificationWithRule(string $code = '010'): Classification
{
    $classification = Classification::factory()->create(['code' => $code]);
    RetentionRule::factory()->create(['classification_id' => $classification->id]);

    return $classification;
}
