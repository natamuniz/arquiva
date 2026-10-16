## 1. Fase 1: Setup e qualidade (semana 1)
- [x] 1.1 Criar projeto Laravel com PHP 8.3+, `strict_types` e estrutura `app/Actions`, `app/Enums`
- [x] 1.2 Docker Compose com `app` (php-fpm), `nginx`, `postgres`, `queue`; `.env.example` funcionando com `docker compose up`; limites de upload do PHP e do nginx acima de 50 MB
- [x] 1.3 Instalar e configurar Pest, Pint, Larastan (nível 6+) e Scribe
- [x] 1.4 GitHub Actions: Pint, Larastan e Pest com serviço PostgreSQL
- [x] 1.5 Handler de erros JSON padronizado para `/api/*` (404 para invisível, 403 para sem permissão)

## 2. Fase 1: Acesso (semana 1)
- [x] 2.1 Migrations e models `units` e `users` (role, unit_id, clearance) com enums
- [x] 2.2 Login e logout com Sanctum, expiração de 24 h e limite de 5 tentativas por minuto (429)
- [x] 2.3 Policies base e helpers de visibilidade por sigilo (metadados x arquivo)
- [x] 2.4 Testes de todos os cenários de `access-control` que não dependem de documentos

## 3. Fase 1: Plano de classificação (semana 1)
- [x] 3.1 Migration e model `classifications` (parent_id, depth, active)
- [x] 3.2 CRUD com validação do formato de código, código e pai imutáveis, desativação (bloqueada com filhas ativas) e bloqueio de exclusão (409)
- [x] 3.3 Endpoint de árvore e subárvore com CTE recursiva
- [x] 3.4 Importação CSV tudo-ou-nada com erros por linha, UTF-8 com BOM e separador `,` ou `;`
- [x] 3.5 Testes de todos os cenários de `classification-plan`

## 4. Fase 2: Documentos e arquivos (semana 2)
- [x] 4.1 Migrations e models `documents` e `document_files` (UUID, índice único `(document_id, sha256)`)
- [x] 4.2 Rascunho: criar, editar, listar com filtros (incluindo classe + descendentes, busca sem acento), ordenação e visibilidade
- [x] 4.3 Upload privado com SHA-256, validação de extensão/tamanho, duplicidade (409) e limpeza em caso de falha; remoção de arquivo do rascunho
- [x] 4.4 Job de processamento despachado após o commit (mime real, `page_count`, status, `failure_reason`, 3 tentativas) e rota de reprocessamento
- [x] 4.5 Download autorizado por credencial de sigilo
- [x] 4.6 Exclusão de rascunho com remoção de arquivos após o commit
- [x] 4.7 Testes dos cenários de `documents` (exceto captura) e `file-storage` (exceto fixidez)

## 5. Fase 3: Temporalidade, captura e auditoria (semana 3)
- [x] 5.1 Migration `retention_rules` com índice único parcial `WHERE valid_to IS NULL` e índice único `(classification_id, version)`
- [x] 5.2 Criar regra, nova versão encerrando a anterior, histórico e consulta `?at=`
- [x] 5.3 Tratar violação do índice único como 409
- [x] 5.4 Captura com validação completa (todos os arquivos processados) e snapshot da regra; bloqueio de reclassificação; alteração de sigilo só por gestor/admin
- [x] 5.5 Migration e model `audit_events` imutável; Observers nos models; eventos de login, falha de login, logout e download
- [x] 5.6 Endpoints de trilha geral (respeitando a visibilidade dos documentos) e histórico do documento
- [x] 5.7 Testes dos cenários de `retention-schedule`, captura e `audit-trail`

## 6. Fase 4: Fixidez, documentação e entrega (semana 4)
- [x] 6.1 Comando `arquiva:check-fixity` em lotes via fila e agendamento diário
- [x] 6.2 Testes de fixidez (íntegro, alterado e ausente); comando `arquiva:prune-orphans` agendado diariamente, com testes
- [x] 6.3 Seeders com unidades, usuários de cada perfil, plano de classificação fictício e documentos de exemplo
- [x] 6.4 Gerar documentação OpenAPI com Scribe
- [x] 6.5 README: problema, funcionalidades, diagrama Mermaid, como rodar, usuários de teste, decisões técnicas (copiar de `design.md`), próximos passos
- [x] 6.6 Garantir CI verde e badge no README
- [ ] 6.7 Arquivar a change no OpenSpec após a entrega
