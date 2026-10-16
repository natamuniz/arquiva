## ADDED Requirements

### Requirement: Regra de temporalidade por classe
Cada classe SHALL poder ter uma regra de temporalidade com: prazo na fase corrente (anos), prazo na fase intermediária (anos), destinação final (`eliminacao` ou `guarda_permanente`) e observações. Prazos MUST ser inteiros maiores ou iguais a zero. A primeira regra é criada em `POST /api/v1/classifications/{id}/retention-rules`.

#### Scenario: Criar primeira regra
- **WHEN** um `gestor` cria a regra de uma classe sem regra
- **THEN** recebe 201 com `version = 1`, `valid_from = agora` e `valid_to = null`

#### Scenario: Criar regra em classe que já tem regra vigente
- **WHEN** chama o `POST` de criação para uma classe que já tem regra vigente
- **THEN** recebe 409 indicando que a alteração é feita criando nova versão

### Requirement: Versionamento de regras
Alterar uma regra (`PUT /api/v1/retention-rules/{id}`) MUST criar uma nova versão e encerrar a anterior (`valid_to` preenchido com o mesmo instante do `valid_from` da nova), na mesma transação. Regras antigas MUST NOT ser editadas nem excluídas.

#### Scenario: Alterar regra vigente
- **WHEN** um `gestor` altera a regra vigente (versão 1)
- **THEN** a versão 1 recebe `valid_to`, uma versão 2 vigente é criada e ambas aparecem no histórico

#### Scenario: Tentar editar versão encerrada
- **WHEN** tenta alterar uma versão com `valid_to` preenchido
- **THEN** recebe 409

### Requirement: Unicidade garantida no banco
MUST existir no máximo uma versão vigente por classe, garantido por índice único parcial `(classification_id) WHERE valid_to IS NULL`. Números de versão MUST ser únicos por classe, garantido por índice único `(classification_id, version)`. Violação de qualquer um desses índices MUST retornar 409.

#### Scenario: Concorrência
- **WHEN** duas alterações simultâneas tentam criar versões vigentes para a mesma classe
- **THEN** apenas uma é gravada e a outra recebe 409

### Requirement: Histórico e consulta por data
O sistema SHALL listar todas as versões de uma classe (`GET /api/v1/classifications/{id}/retention-rules`) e informar qual regra estava vigente em um instante (`?at=`). O parâmetro `at` MUST aceitar data (`2025-01-10`, interpretada como 00:00 no fuso `America/Sao_Paulo`) ou data-hora ISO 8601. Uma versão está vigente em `at` quando `valid_from <= at` e (`valid_to` é nulo ou `at < valid_to`).

#### Scenario: Regra vigente em data passada
- **WHEN** chama `GET /api/v1/classifications/{id}/retention-rules?at=2025-01-10`
- **THEN** recebe a versão vigente naquela data

#### Scenario: Data anterior à primeira regra
- **WHEN** consulta uma data anterior ao `valid_from` da versão 1
- **THEN** recebe 404 indicando que não havia regra vigente
