## ADDED Requirements

### Requirement: Autenticação por token
O sistema SHALL autenticar usuários por e-mail e senha e emitir um token Sanctum com validade de 24 horas. Todas as rotas em `/api/v1`, exceto login, MUST exigir token válido. O login MUST limitar tentativas a 5 por minuto por combinação de e-mail e IP.

#### Scenario: Login com credenciais válidas
- **WHEN** o usuário envia e-mail e senha corretos para `POST /api/v1/auth/login`
- **THEN** recebe 200 com um token, a data de expiração e os dados do usuário (sem a senha)

#### Scenario: Login com credenciais inválidas
- **WHEN** a senha está errada
- **THEN** recebe 422 com mensagem genérica, sem indicar qual campo errou

#### Scenario: Excesso de tentativas de login
- **WHEN** o mesmo e-mail e IP erram a senha 5 vezes em um minuto e tentam de novo
- **THEN** recebe 429 com o cabeçalho `Retry-After`

#### Scenario: Acesso sem token
- **WHEN** uma rota protegida é chamada sem token
- **THEN** recebe 401

#### Scenario: Token expirado
- **WHEN** uma rota protegida é chamada com token emitido há mais de 24 horas
- **THEN** recebe 401

#### Scenario: Logout
- **WHEN** o usuário chama `POST /api/v1/auth/logout`
- **THEN** o token atual é revogado e deixa de funcionar

### Requirement: Perfis de usuário
O sistema SHALL ter os perfis `admin`, `gestor`, `operador` e `consulta`, com as permissões:
- `admin`: tudo.
- `gestor`: gerencia plano de classificação e temporalidade; cria, edita e captura documentos da própria unidade; altera o sigilo de documentos capturados da própria unidade.
- `operador`: cria, edita e captura documentos da própria unidade.
- `consulta`: somente leitura.

Usuários e unidades MUST ser criados somente por seeders no MVP; não existem rotas para gerenciá-los.

#### Scenario: Consulta tenta criar documento
- **WHEN** um usuário `consulta` chama `POST /api/v1/documents`
- **THEN** recebe 403

#### Scenario: Operador tenta alterar o plano de classificação
- **WHEN** um `operador` chama `POST /api/v1/classifications`
- **THEN** recebe 403

### Requirement: Acesso a metadados por sigilo
O sistema MUST permitir ver os metadados de um documento somente se: o usuário for `admin`; ou o documento for `publico`; ou o documento pertencer à unidade do usuário. Dentro da própria unidade, qualquer usuário vê os metadados, independentemente da credencial; a credencial só controla o download do arquivo.

#### Scenario: Documento restrito de outra unidade
- **WHEN** um usuário consulta um documento `restrito` de outra unidade
- **THEN** recebe 404 e o documento não aparece na listagem

#### Scenario: Documento público de outra unidade
- **WHEN** um usuário consulta um documento `publico` de outra unidade
- **THEN** recebe 200 com os metadados

### Requirement: Não revelar existência (404 x 403)
Documentos não visíveis MUST ficar fora das listagens e retornar 404 em qualquer rota que os referencie (leitura, edição, captura, exclusão, upload, histórico). Quando o usuário vê o documento mas não tem permissão para a ação, o sistema MUST retornar 403.

#### Scenario: Editar documento restrito de outra unidade
- **WHEN** um `operador` tenta editar documento `restrito` de outra unidade
- **THEN** recebe 404

#### Scenario: Editar documento público de outra unidade
- **WHEN** um `operador` tenta editar documento `publico` de outra unidade
- **THEN** recebe 403

### Requirement: Acesso ao arquivo por credencial de sigilo
Para baixar um arquivo, o usuário MUST poder ver os metadados do documento E ter credencial (`clearance`) maior ou igual ao sigilo do documento, na ordem `publico < restrito < sigiloso`. `admin` sempre pode.

#### Scenario: Vê metadados mas não baixa
- **WHEN** um usuário da mesma unidade, com credencial `restrito`, tenta baixar arquivo de documento `sigiloso`
- **THEN** consegue ver os metadados (200) mas o download retorna 403

#### Scenario: Download permitido
- **WHEN** um usuário com credencial `sigiloso` da mesma unidade baixa o arquivo
- **THEN** recebe o arquivo
