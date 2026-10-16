<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Sem frontend no MVP: a raiz leva à documentação da API.
Route::redirect('/', '/docs');
