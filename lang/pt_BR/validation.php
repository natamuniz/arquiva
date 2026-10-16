<?php

declare(strict_types=1);

// Mensagens usadas pela API. Regras ausentes caem no inglês (fallback_locale).
return [
    'array' => 'O campo :attribute deve ser uma lista.',
    'before_or_equal' => 'O campo :attribute deve ser uma data anterior ou igual a :date.',
    'boolean' => 'O campo :attribute deve ser verdadeiro ou falso.',
    'date' => 'O campo :attribute deve ser uma data válida.',
    'date_format' => 'O campo :attribute deve estar no formato :format.',
    'email' => 'O campo :attribute deve ser um e-mail válido.',
    'enum' => 'O valor informado em :attribute é inválido.',
    'exists' => 'O valor informado em :attribute não existe.',
    'extensions' => 'O arquivo deve ter uma das extensões: :values.',
    'file' => 'O campo :attribute deve ser um arquivo.',
    'in' => 'O valor informado em :attribute é inválido.',
    'integer' => 'O campo :attribute deve ser um número inteiro.',
    'max' => [
        'file' => 'O arquivo não pode ter mais de :max kilobytes.',
        'numeric' => 'O campo :attribute não pode ser maior que :max.',
        'string' => 'O campo :attribute não pode ter mais de :max caracteres.',
    ],
    'min' => [
        'numeric' => 'O campo :attribute deve ser no mínimo :min.',
        'string' => 'O campo :attribute deve ter no mínimo :min caracteres.',
    ],
    'prohibited' => 'O campo :attribute não pode ser informado.',
    'required' => 'O campo :attribute é obrigatório.',
    'required_if' => 'O campo :attribute é obrigatório quando :other é :value.',
    'string' => 'O campo :attribute deve ser um texto.',
    'unique' => 'O valor informado em :attribute já está em uso.',
    'uploaded' => 'Falha no envio do arquivo :attribute.',

    'attributes' => [
        'title' => 'título',
        'subject' => 'assunto',
        'interested_party' => 'interessado',
        'produced_at' => 'data de produção',
        'classification_id' => 'classe',
        'unit_id' => 'unidade',
        'secrecy' => 'sigilo',
        'email' => 'e-mail',
        'password' => 'senha',
        'code' => 'código',
        'name' => 'nome',
        'parent_id' => 'classe pai',
        'current_years' => 'prazo na fase corrente',
        'intermediate_years' => 'prazo na fase intermediária',
        'final_destination' => 'destinação final',
        'notes' => 'observações',
        'file' => 'arquivo',
        'per_page' => 'itens por página',
        'at' => 'data de referência',
    ],
];
