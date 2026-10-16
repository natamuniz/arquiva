## ADDED Requirements

### Requirement: Classes hierárquicas
O sistema SHALL manter um plano de classificação em árvore. Cada classe MUST ter código único e nome. O código MUST seguir o formato:
- classe raiz: exatamente 3 dígitos (regex `^\d{3}$`), ex.: `010`;
- classe filha: código do pai + `.` + 1 a 3 dígitos (regex `^<código do pai>\.\d{1,3}$`), ex.: `010` > `010.1` > `010.1.1`.

#### Scenario: Criar classe filha válida
- **WHEN** um `gestor` cria a classe `010.1` com pai `010`
- **THEN** recebe 201 e a classe tem `depth` igual ao do pai + 1

#### Scenario: Código incompatível com o pai
- **WHEN** tenta criar a classe `020.1` com pai `010`
- **THEN** recebe 422 explicando a incompatibilidade

#### Scenario: Código sem separador
- **WHEN** tenta criar a classe `0101` com pai `010`
- **THEN** recebe 422 explicando o formato esperado

#### Scenario: Código duplicado
- **WHEN** tenta criar uma classe com código já existente
- **THEN** recebe 422

### Requirement: Edição de classe
O sistema SHALL permitir editar o nome de uma classe. Código e pai MUST NOT ser alterados depois da criação, porque isso quebraria os códigos das filhas e a classificação dos documentos.

#### Scenario: Renomear classe
- **WHEN** um `gestor` altera o nome da classe `010`
- **THEN** recebe 200 com o novo nome

#### Scenario: Alterar código ou pai
- **WHEN** tenta alterar o código ou o pai de uma classe existente
- **THEN** recebe 409

### Requirement: Consulta da árvore
O sistema SHALL listar o plano como árvore aninhada e permitir obter a subárvore de uma classe.

#### Scenario: Obter subárvore
- **WHEN** chama `GET /api/v1/classifications/{id}/tree`
- **THEN** recebe a classe com todas as descendentes aninhadas, ordenadas por código

### Requirement: Desativação em vez de exclusão
Uma classe com filhas ou documentos MUST NOT ser excluída; o sistema SHALL permitir desativá-la. Uma classe com filhas ativas MUST NOT ser desativada (as filhas são desativadas antes). Classes inativas MUST NOT receber novos documentos.

#### Scenario: Excluir classe com documentos
- **WHEN** tenta excluir classe que possui documentos
- **THEN** recebe 409 sugerindo desativar

#### Scenario: Desativar classe com filhas ativas
- **WHEN** tenta desativar a classe `010` enquanto `010.1` está ativa
- **THEN** recebe 409 listando as filhas ativas

#### Scenario: Classificar em classe inativa
- **WHEN** tenta criar documento em classe inativa
- **THEN** recebe 422

### Requirement: Importação por CSV
O sistema SHALL importar classes de um CSV com colunas `code,name,parent_code`, em UTF-8 (com ou sem BOM) e separador `,` ou `;` (detectado pelo cabeçalho). A importação MUST ser tudo-ou-nada: se qualquer linha for inválida, nada é gravado e a resposta lista os erros por número de linha. Pais podem aparecer depois dos filhos no arquivo. Código que já existe no banco MUST ser tratado como erro da linha.

#### Scenario: CSV válido
- **WHEN** um `gestor` envia um CSV válido com 50 classes
- **THEN** recebe 201 com o total importado e a árvore fica completa

#### Scenario: CSV exportado do Excel
- **WHEN** envia um CSV com BOM e separador `;`
- **THEN** a importação funciona como em um CSV com `,`

#### Scenario: CSV com erros
- **WHEN** o CSV tem a linha 7 com pai inexistente e a linha 12 com código duplicado
- **THEN** recebe 422 com erros nas linhas 7 e 12 e nenhuma classe é gravada
