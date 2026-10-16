## Why
Criar o MVP do Arquiva: uma API de gestão documental que demonstra, em código público e testado, modelagem de regras de negócio, controle de acesso, integridade de arquivos, filas e auditoria em Laravel com PostgreSQL.

## What Changes
- **ADDED** `access-control`: autenticação por token, perfis, unidades e regras de acesso por sigilo (metadados x arquivo).
- **ADDED** `classification-plan`: plano de classificação hierárquico com importação via CSV.
- **ADDED** `retention-schedule`: tabela de temporalidade versionada por classe.
- **ADDED** `documents`: ciclo de vida do documento (rascunho → capturado), metadados, busca e exclusão segura.
- **ADDED** `file-storage`: upload privado, hash SHA-256, download autorizado e verificação de fixidez em fila.
- **ADDED** `audit-trail`: trilha de auditoria imutável.
- Infraestrutura: Docker Compose, CI no GitHub Actions, documentação OpenAPI e README.

## Impact
- Specs afetadas: todas novas (projeto do zero).
- Código: projeto Laravel novo.
- Fora de escopo: frontend, OCR, assinatura digital, multi-tenant, deploy em nuvem.
