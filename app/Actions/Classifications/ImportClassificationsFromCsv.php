<?php

declare(strict_types=1);

namespace App\Actions\Classifications;

use App\Exceptions\UnprocessableException;
use App\Models\Classification;
use Illuminate\Support\Facades\DB;

/**
 * Importa classes de um CSV "code,name,parent_code" em uma única transação (tudo ou nada).
 *
 * - Aceita UTF-8 com ou sem BOM e separador "," ou ";" (detectado pelo cabeçalho).
 * - Pais podem aparecer depois dos filhos no arquivo.
 * - Os números de linha nos erros contam o cabeçalho como linha 1.
 */
class ImportClassificationsFromCsv
{
    private const COLUMNS = ['code', 'name', 'parent_code'];

    /** @var array<int, list<string>> erros por número de linha */
    private array $errors = [];

    public function handle(string $contents): int
    {
        $this->errors = [];

        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;
        $lines = preg_split('/\r\n|\r|\n/', $contents) ?: [];

        $header = $lines[0] ?? '';
        $delimiter = substr_count($header, ';') > substr_count($header, ',') ? ';' : ',';
        $columns = $this->mapHeader(str_getcsv($header, $delimiter, '"', ''));

        $rows = $this->parseRows($lines, $delimiter, $columns);
        $this->validateRows($rows);

        if ($this->errors !== []) {
            ksort($this->errors);
            $errors = [];
            foreach ($this->errors as $line => $messages) {
                $errors["linha {$line}"] = $messages;
            }

            $count = count($this->errors);

            throw new UnprocessableException(
                "O arquivo tem {$count} linha(s) com erro. Nenhuma classe foi importada.",
                $errors,
            );
        }

        return $this->persist($rows);
    }

    /**
     * @param  list<string|null>  $header
     * @return array<string, int>|null posição de cada coluna
     */
    private function mapHeader(array $header): ?array
    {
        $normalized = array_map(fn (?string $h): string => strtolower(trim((string) $h)), $header);
        $positions = [];

        foreach (self::COLUMNS as $column) {
            $index = array_search($column, $normalized, true);
            if ($index === false) {
                $this->errors[1][] = 'Cabeçalho inválido: as colunas esperadas são code, name e parent_code.';

                return null;
            }
            $positions[$column] = (int) $index;
        }

        return $positions;
    }

    /**
     * @param  list<string>  $lines
     * @param  array<string, int>|null  $columns
     * @return array<string, array{line: int, code: string, name: string, parent_code: string|null}>
     */
    private function parseRows(array $lines, string $delimiter, ?array $columns): array
    {
        if ($columns === null) {
            return [];
        }

        $rows = [];

        foreach (array_slice($lines, 1, null, true) as $index => $raw) {
            $line = $index + 1;

            if (trim($raw) === '') {
                continue;
            }

            $fields = str_getcsv($raw, $delimiter, '"', '');
            $code = trim((string) ($fields[$columns['code']] ?? ''));
            $name = trim((string) ($fields[$columns['name']] ?? ''));
            $parent = trim((string) ($fields[$columns['parent_code']] ?? ''));

            if ($code === '' || $name === '') {
                $this->errors[$line][] = 'Código e nome são obrigatórios.';

                continue;
            }

            if (isset($rows[$code])) {
                $this->errors[$line][] = "Código {$code} duplicado: já aparece na linha {$rows[$code]['line']}.";

                continue;
            }

            $rows[$code] = ['line' => $line, 'code' => $code, 'name' => $name, 'parent_code' => $parent === '' ? null : $parent];
        }

        return $rows;
    }

    /**
     * @param  array<string, array{line: int, code: string, name: string, parent_code: string|null}>  $rows
     */
    private function validateRows(array $rows): void
    {
        $codes = array_map('strval', array_keys($rows));
        $parentCodes = array_values(array_filter(array_column($rows, 'parent_code')));

        $existing = Classification::query()
            ->whereIn('code', array_merge($codes, $parentCodes))
            ->pluck('code')
            ->flip();

        foreach ($rows as $row) {
            $code = $row['code'];
            $parent = $row['parent_code'];

            if ($existing->has($code)) {
                $this->errors[$row['line']][] = "Código {$code} duplicado: já existe no plano de classificação.";
            }

            if ($parent !== null && ! isset($rows[$parent]) && ! $existing->has($parent)) {
                $this->errors[$row['line']][] = "Classe pai {$parent} não existe nem no arquivo nem no plano.";

                continue;
            }

            if (! Classification::isValidCode($code, $parent)) {
                $this->errors[$row['line']][] = $parent === null
                    ? "Código {$code} inválido: uma classe raiz deve ter exatamente 3 dígitos."
                    : "Código {$code} incompatível com a classe pai {$parent}: esperado {$parent}.N, com N de 1 a 3 dígitos.";
            }
        }
    }

    /**
     * @param  array<string, array{line: int, code: string, name: string, parent_code: string|null}>  $rows
     */
    private function persist(array $rows): int
    {
        // Pais antes dos filhos: o código do pai sempre tem menos segmentos.
        uasort($rows, fn (array $a, array $b): int => substr_count($a['code'], '.') <=> substr_count($b['code'], '.'));

        return DB::transaction(function () use ($rows): int {
            $parentCodes = array_values(array_filter(array_column($rows, 'parent_code')));

            /** @var array<string, array{id: int, depth: int}> $known */
            $known = Classification::query()
                ->whereIn('code', $parentCodes)
                ->get(['id', 'code', 'depth'])
                ->mapWithKeys(fn (Classification $c): array => [$c->code => ['id' => $c->id, 'depth' => $c->depth]])
                ->all();

            foreach ($rows as $row) {
                $parent = $row['parent_code'] === null ? null : $known[$row['parent_code']];

                $created = Classification::create([
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'parent_id' => $parent['id'] ?? null,
                    'depth' => $parent === null ? 1 : $parent['depth'] + 1,
                    'active' => true,
                ]);

                $known[$created->code] = ['id' => $created->id, 'depth' => $created->depth];
            }

            return count($rows);
        });
    }
}
