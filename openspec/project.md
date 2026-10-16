# Project Context

## Purpose
**Arquiva** é uma API open-source de gestão documental escrita em Laravel. É um projeto de portfólio: demonstra modelagem de regras de negócio reais (plano de classificação, temporalidade versionada, controle de acesso por sigilo, trilha de auditoria e integridade de arquivos) com código limpo, testado e fácil de rodar.

O projeto é escrito do zero, com dados fictícios. Não reutiliza código, nomes de clientes ou dados de nenhuma empresa.

## Tech Stack
- PHP 8.3+ e Laravel 13
- PostgreSQL 16+
- Laravel Sanctum (autenticação por token)
- Filas com driver `database` (worker em container próprio)
- Pest (testes), Laravel Pint (estilo), Larastan nível 8 (análise estática)
- Scribe (documentação OpenAPI gerada a partir do código)
- Docker Compose: `app` (php-fpm), `nginx`, `postgres`, `queue`, `scheduler`
- GitHub Actions (CI)

## Project Conventions

### Code Style
- Código (classes, tabelas, colunas, rotas) em inglês; mensagens ao usuário, README e specs em português.
- `declare(strict_types=1);` em todos os arquivos PHP.
- Controllers finos: validação em Form Requests, regra de negócio em Actions/Services (`app/Actions`), autorização em Policies, saída em API Resources.
- Enums nativos do PHP para status, sigilo, perfis e destinação.
- Rodar `pint` antes de cada commit.

### Architecture Patterns
- API REST versionada em `/api/v1`, respostas JSON via API Resources, paginação padrão de 20 itens.
- Erros em formato consistente: `{ "message": "...", "errors": { ... } }` com status HTTP correto (401, 403, 404, 409, 422).
- Operações que mexem em banco e em arquivo usam transação; efeitos no disco só acontecem **depois do commit** (`DB::afterCommit` ou eventos `afterCommit`).
- Arquivos em disco privado (`storage/app/private`), nunca acessíveis por URL pública.
- IDs de documentos e arquivos em UUID.
- Datas em `timestamptz` (UTC) no banco, ISO 8601 na API; datas sem hora interpretadas no fuso `America/Sao_Paulo`.
- Recurso invisível para o usuário responde 404; recurso visível sem permissão para a ação responde 403.

### Testing Strategy
- Pest com banco PostgreSQL de teste (não SQLite, para validar índices parciais e JSONB).
- Todo cenário (`#### Scenario`) das specs deve ter pelo menos um teste Feature correspondente.
- Testes de autorização cobrem permitido e negado.
- CI falha se testes, Pint ou Larastan falharem.

### Git Workflow
- Branch `main` protegida; trabalho em branches por fase (`feat/fase-1-setup`, etc.).
- Commits pequenos no padrão Conventional Commits (`feat:`, `fix:`, `test:`, `docs:`, `chore:`).

## Domain Context
- **Plano de classificação:** árvore de classes com código hierárquico: raiz com 3 dígitos e filhas com o código do pai + `.` + 1 a 3 dígitos (ex.: `010` > `010.1` > `010.1.1`). Todo documento é classificado em uma classe.
- **Tabela de temporalidade:** para cada classe, define prazo na fase corrente, prazo na fase intermediária e destinação final (eliminação ou guarda permanente). As regras mudam com o tempo, por isso são **versionadas**: alterar cria uma nova versão e encerra a anterior.
- **Sigilo:** `publico`, `restrito`, `sigiloso`. Ver os metadados de um documento é diferente de baixar o arquivo.
- **Unidade:** setor dono do documento. Usuários pertencem a uma unidade.
- **Fixidez:** hash SHA-256 calculado na entrada e reverificado periodicamente para detectar corrupção.
- **Trilha de auditoria:** registro imutável de quem fez o quê, quando e o que mudou.

## Important Constraints
- Sem frontend no MVP (somente API documentada).
- Usuários e unidades são criados só por seeders no MVP (sem rotas de gestão).
- Sem OCR, assinatura digital ou multi-tenant no MVP.
- Dados de seed 100% fictícios.

## External Dependencies
Nenhuma além dos pacotes Composer listados na stack e de `smalot/pdfparser` (contagem de páginas de PDF).
