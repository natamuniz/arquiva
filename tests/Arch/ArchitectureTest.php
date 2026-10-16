<?php

declare(strict_types=1);

// strict_types em todos os arquivos é garantido pelo Pint (regra declare_strict_types no pint.json).

arch('models estendem Eloquent')
    ->expect('App\Models')
    ->toExtend('Illuminate\Database\Eloquent\Model');

arch('regras de negócio não dependem da camada HTTP')
    ->expect('App\Actions')
    ->not->toUse(['Illuminate\Http\Request', 'Illuminate\Support\Facades\Request', 'App\Http']);

arch('controllers não acessam o banco direto')
    ->expect('App\Http\Controllers')
    ->not->toUse('Illuminate\Support\Facades\DB');

arch('sem funções de depuração esquecidas')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r'])
    ->not->toBeUsed();
