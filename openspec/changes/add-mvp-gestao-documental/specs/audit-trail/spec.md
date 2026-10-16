## ADDED Requirements

### Requirement: Registro automático de alterações
O sistema SHALL registrar evento de auditoria em toda criação, alteração e exclusão de classes, regras de temporalidade, documentos e arquivos, com: usuário, ação, entidade, valores antes e depois (somente campos alterados), IP e data/hora. Eventos gerados por jobs ou comandos (sem usuário) MUST ter `user_id` nulo.

#### Scenario: Alteração de metadado
- **WHEN** um usuário muda o título de um documento
- **THEN** é gravado um evento `document.updated` com `before.title` e `after.title`

#### Scenario: Captura
- **WHEN** um documento é capturado
- **THEN** é gravado um evento `document.captured`

### Requirement: Eventos de acesso
O sistema SHALL registrar login, falha de login, logout e download de arquivos.

#### Scenario: Login
- **WHEN** um usuário faz login
- **THEN** é gravado um evento `auth.login` com o IP

#### Scenario: Falha de login
- **WHEN** alguém erra a senha
- **THEN** é gravado um evento `auth.login_failed` com o e-mail informado, o IP e `user_id` nulo, sem gravar a senha

### Requirement: Imutabilidade
Eventos de auditoria MUST NOT ser alterados ou excluídos pela aplicação. MUST NOT existir rota de escrita para auditoria.

#### Scenario: Tentativa de alterar evento
- **WHEN** o código tenta fazer `update` ou `delete` em um `AuditEvent`
- **THEN** uma exceção é lançada e nada muda

### Requirement: Consulta da trilha
O sistema SHALL permitir que `admin` e `gestor` consultem a trilha, paginada, com filtros por usuário, ação, entidade e período, e que qualquer usuário com acesso a um documento veja o histórico daquele documento. Na trilha geral, eventos de documentos e arquivos MUST respeitar as regras de visibilidade de metadados: um `gestor` MUST NOT ver eventos de documentos que não pode ver.

#### Scenario: Histórico de um documento
- **WHEN** chama `GET /api/v1/documents/{id}/history`
- **THEN** recebe os eventos do documento e de seus arquivos em ordem cronológica

#### Scenario: Gestor não vê eventos de documento invisível
- **WHEN** um `gestor` consulta `GET /api/v1/audit-events` e existe evento de documento `sigiloso` de outra unidade
- **THEN** esse evento não aparece na resposta

#### Scenario: Operador consulta trilha geral
- **WHEN** um `operador` chama `GET /api/v1/audit-events`
- **THEN** recebe 403
