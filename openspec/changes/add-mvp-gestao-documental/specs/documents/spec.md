## ADDED Requirements

### Requirement: Rascunho de documento
O sistema SHALL permitir criar documentos em status `rascunho` (`POST /api/v1/documents`) com: título, assunto, interessado, data de produção, classe, sigilo. A unidade MUST ser a do usuário que cria (exceto `admin`, que informa a unidade). Rascunhos podem estar incompletos, mas os campos enviados MUST ser válidos. Sigilo não informado MUST assumir `restrito`.

#### Scenario: Criar rascunho
- **WHEN** um `operador` envia título e classe
- **THEN** recebe 201 com status `rascunho`, sigilo `restrito` e unidade igual à dele

### Requirement: Captura com validação
Capturar um documento (`POST /api/v1/documents/{id}/capture`) MUST exigir: título, data de produção (não futura), classe ativa com regra de temporalidade vigente, pelo menos um arquivo e todos os arquivos com `processing_status = processado` (arquivos `pendente` ou `falhou` impedem a captura e devem ser reprocessados ou removidos). Ao capturar, o sistema SHALL gravar `captured_at` e o `retention_rule_id` vigente (snapshot).

#### Scenario: Captura válida
- **WHEN** um rascunho completo é enviado para `POST /api/v1/documents/{id}/capture`
- **THEN** o status vira `capturado` e o documento guarda a versão vigente da regra

#### Scenario: Captura incompleta
- **WHEN** o rascunho não tem arquivo e não tem data de produção
- **THEN** recebe 422 listando todas as pendências de uma vez

#### Scenario: Arquivo com falha impede captura
- **WHEN** o rascunho tem um arquivo `processado` e outro `falhou`
- **THEN** recebe 422 indicando o arquivo com falha

#### Scenario: Regra muda depois da captura
- **WHEN** a regra da classe ganha nova versão após a captura
- **THEN** o documento continua apontando para a versão da data de captura

### Requirement: Edição após captura
Após a captura, classe e unidade MUST NOT ser alteradas. O sigilo só MAY ser alterado por `gestor` da unidade ou `admin`. Os demais metadados podem ser editados por quem tem permissão. Toda edição é auditada.

#### Scenario: Reclassificar documento capturado
- **WHEN** tenta mudar a classe de um documento capturado
- **THEN** recebe 409

#### Scenario: Operador altera sigilo de documento capturado
- **WHEN** um `operador` tenta mudar o sigilo de um documento capturado da sua unidade
- **THEN** recebe 403

### Requirement: Busca e listagem
O sistema SHALL listar documentos visíveis ao usuário (`GET /api/v1/documents`), paginados (20 por página, `per_page` até 100), com filtros por texto (título/assunto/interessado, sem diferenciar maiúsculas e acentos), classe (incluindo descendentes), unidade, sigilo, status e intervalo de data de produção. A ordenação padrão MUST ser por data de produção decrescente, com parâmetro `sort` para título, data de produção e data de captura.

#### Scenario: Filtrar por classe incluindo descendentes
- **WHEN** filtra pela classe `010`
- **THEN** recebe documentos de `010`, `010.1`, `010.1.1` etc., respeitando as regras de visibilidade

#### Scenario: Busca sem acento
- **WHEN** busca por `licitacao`
- **THEN** recebe documentos com "Licitação" no título, assunto ou interessado

### Requirement: Exclusão segura
Somente documentos em `rascunho` MAY ser excluídos. A exclusão MUST remover o registro em transação e apagar os arquivos físicos somente após o commit.

#### Scenario: Excluir rascunho
- **WHEN** o criador exclui um rascunho com arquivos
- **THEN** recebe 204, o registro some e os arquivos são apagados depois do commit

#### Scenario: Falha no banco durante a exclusão
- **WHEN** a transação falha
- **THEN** nenhum arquivo físico é apagado

#### Scenario: Excluir documento capturado
- **WHEN** tenta excluir documento `capturado`
- **THEN** recebe 409
