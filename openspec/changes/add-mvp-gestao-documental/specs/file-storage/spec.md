## ADDED Requirements

### Requirement: Upload privado com hash
O sistema SHALL aceitar upload de arquivos (`POST /api/v1/documents/{id}/files`) com extensão PDF, PNG, JPEG ou TIFF, até 50 MB, em documentos `rascunho`. No upload, o tipo MUST ser validado só pela extensão; o conteúdo real é verificado no processamento em fila. O arquivo MUST ser salvo em disco privado com nome gerado (UUID), e o SHA-256 MUST ser calculado e gravado no upload. Se a gravação no banco falhar, o arquivo salvo MUST ser removido.

#### Scenario: Upload válido
- **WHEN** um `operador` envia um PDF para um rascunho da sua unidade
- **THEN** recebe 201 com id, nome original, tamanho, mime, sha256 e `processing_status = pendente`

#### Scenario: Tipo não permitido
- **WHEN** envia um `.exe`
- **THEN** recebe 422

#### Scenario: Arquivo acima do limite
- **WHEN** envia um PDF de 51 MB
- **THEN** recebe 422 (e não um erro do nginx ou do PHP)

#### Scenario: Upload em documento capturado
- **WHEN** envia um arquivo para um documento `capturado`
- **THEN** recebe 409

### Requirement: Arquivo único por documento
Um documento MUST NOT ter dois arquivos com o mesmo SHA-256, garantido por índice único `(document_id, sha256)` no banco. A violação do índice MUST retornar 409.

#### Scenario: Arquivo duplicado no mesmo documento
- **WHEN** envia um arquivo com o mesmo sha256 de outro arquivo do documento
- **THEN** recebe 409

### Requirement: Remoção de arquivo do rascunho
O sistema SHALL permitir remover um arquivo de um documento em `rascunho` (`DELETE /api/v1/files/{id}`). O registro MUST ser removido em transação e o arquivo físico apagado somente após o commit.

#### Scenario: Remover arquivo do rascunho
- **WHEN** o `operador` da unidade remove um arquivo de um rascunho
- **THEN** recebe 204 e o arquivo físico é apagado depois do commit

#### Scenario: Remover arquivo de documento capturado
- **WHEN** tenta remover um arquivo de documento `capturado`
- **THEN** recebe 409

### Requirement: Processamento em fila
Após o upload, o sistema SHALL despachar, somente depois do commit, um job que valida o mime real do conteúdo, extrai metadados técnicos (número de páginas para PDF, gravado em `page_count`) e marca `processing_status` como `processado` ou `falhou`. O job MUST ter tentativas limitadas (3) e gravar o motivo da falha em `failure_reason`. O sistema SHALL permitir reprocessar um arquivo `falhou` (`POST /api/v1/files/{id}/reprocess`).

#### Scenario: Processamento bem-sucedido
- **WHEN** o worker processa um PDF válido
- **THEN** o arquivo fica `processado` com `page_count` preenchido

#### Scenario: Conteúdo não condiz com a extensão
- **WHEN** o arquivo enviado como `.pdf` não é um PDF de verdade
- **THEN** o upload é aceito (201), e depois do processamento o arquivo fica `falhou` com o motivo em `failure_reason`

### Requirement: Download autorizado
O download MUST passar pela API (`GET /api/v1/files/{id}/download`), aplicar as regras de acesso por sigilo, ser registrado na auditoria e retornar o arquivo com o nome original.

#### Scenario: Download registrado
- **WHEN** um usuário autorizado baixa um arquivo
- **THEN** recebe o arquivo e um evento `file.downloaded` é gravado

### Requirement: Verificação de fixidez
O sistema SHALL ter o comando `arquiva:check-fixity` que recalcula o SHA-256 dos arquivos em lotes (via fila), atualiza `last_fixity_check_at` e `fixity_ok`, e registra evento de auditoria em caso de divergência. Antes da primeira verificação, `fixity_ok` MUST ser nulo (não verificado). O comando MUST ser agendado diariamente no scheduler.

#### Scenario: Arquivo íntegro
- **WHEN** a verificação roda sobre um arquivo sem alteração
- **THEN** o arquivo fica com `fixity_ok = true` e `last_fixity_check_at` atualizado

#### Scenario: Arquivo corrompido
- **WHEN** o conteúdo de um arquivo é alterado no disco e a verificação roda
- **THEN** o arquivo fica com `fixity_ok = false` e um evento `file.fixity_failed` é gravado

#### Scenario: Arquivo ausente
- **WHEN** o arquivo físico não existe mais
- **THEN** fica com `fixity_ok = false` e o evento registra "arquivo ausente"

### Requirement: Limpeza de arquivos órfãos
O sistema SHALL ter o comando `arquiva:prune-orphans`, agendado diariamente, que apaga do disco privado arquivos sem registro em `document_files` criados há mais de 24 horas (sobras de uploads interrompidos).

#### Scenario: Arquivo órfão antigo
- **WHEN** existe no disco um arquivo sem registro, criado há 2 dias
- **THEN** o comando o apaga e informa quantos arquivos removeu

#### Scenario: Upload em andamento
- **WHEN** existe no disco um arquivo sem registro, criado há 5 minutos
- **THEN** o comando não o apaga
