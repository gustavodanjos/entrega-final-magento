![Quality](https://github.com/gustavodanjos/entrega-final-magento/actions/workflows/quality.yml/badge.svg?branch=main)

# Ateliê Nuvem — Entrega Final (Magento Open Source)

Ambiente local do projeto da equipe, executado com Docker.

## Índice

- [Sobre o projeto](#sobre-o-projeto)
- [Estrutura do repositório](#estrutura-do-repositório)
- [Versões do ambiente](#versões-do-ambiente)
- [Como executar o projeto](#como-executar-o-projeto)
- [Regras do Time](#regras-do-time)
- [Padrões de Código e Desenvolvimento](#padrões-de-código-e-desenvolvimento)

## Sobre o projeto

**Cliente:** Nuvem Calçados (Franca, SP). Projeto fictício, exigência real.

A Nuvem quer uma loja Magento 2 onde a cliente monta o próprio tênis num estúdio visual, vê cada peça mudar de cor na hora, entende por que algumas combinações não existem, paga o preço certo e recebe o par em casa. A fábrica recebe uma ficha de produção por par vendido e o marketing monta a vitrine pelo Page Builder.

## Versões do ambiente

- **Magento Open Source:** `2.4.8` (fixado em `src/composer.json` e `src/composer.lock`)
- **PHP:** imagem `markoshust/magento-php:8.4-fpm-2`
- **Nginx:** `markoshust/magento-nginx:1.28-0`
- **OpenSearch:** `markoshust/magento-opensearch:3-0`
- **MariaDB:** `mariadb:11.4`
- **RabbitMQ:** `markoshust/magento-rabbitmq:4.2-0`
- **Valkey:** `valkey/valkey:8.1-alpine`

As imagens e versões dos serviços são definidas por `compose.versions.yaml`, gerado pelos scripts do projeto. O Docker Compose usado na máquina de cada integrante pode ter uma versão diferente.

## Como executar o projeto

Siga as etapas 1 a 5 em ordem para subir o ambiente do zero. As etapas 6 a 12 cobrem dados de demonstração, uso diário e solução de problemas.

### 1. Pré-requisitos

- Git, Docker e Docker Compose instalados e funcionando.
- Chaves de acesso do Magento Marketplace (public key e private key) para instalar as dependências privadas do Composer.
- Memória suficiente para executar os serviços do ambiente (recomendado reservar pelo menos 6 GB para o Docker).
- Portas necessárias livres.
- **Windows com WSL2:** clone e execute o projeto dentro do sistema de arquivos Linux do WSL, não em `/mnt/c`.

### 2. Clonar o projeto

```bash
git clone https://github.com/gustavodanjos/entrega-final-magento.git
cd entrega-final-magento
```

### 3. Iniciar os containers pela primeira vez

```bash
bin/start --no-dev
bin/copytocontainer --all
```

Use `--no-dev` na primeira inicialização para evitar problemas com arquivos de volume ainda não copiados para os containers.

### 4. Instalar as dependências PHP

Configure as chaves do Magento Marketplace no Composer quando solicitado e execute:

```bash
bin/composer install
```

O comando instala as dependências definidas em `src/composer.json` e `src/composer.lock`. A primeira execução pode demorar.

**Não versione chaves de acesso.** Não faça commit de `src/auth.json` nem de `src/app/etc/env.php`.

### 5. Instalar o Magento

```bash
bin/setup final-project.test
```

O script prepara a instalação local e pode solicitar permissão para adicionar o domínio ao arquivo de hosts do sistema.

Se o domínio não resolver automaticamente, adicione a seguinte linha ao arquivo de hosts da máquina:

```text
127.0.0.1 final-project.test
```

No Windows com WSL2, adicione-a também ao arquivo `C:\Windows\System32\drivers\etc\hosts` do Windows, caso o navegador seja executado no Windows.

### 6. Instalar os produtos de demonstração (Sample Data)

O projeto declara os pacotes de Sample Data no Composer, mas isso, por si só, **não garante que os dados de demonstração tenham sido carregados no banco**. Depois que a instalação inicial do Magento terminar, execute:

```bash
bin/magento sampledata:deploy
bin/magento setup:upgrade
bin/magento indexer:reindex
bin/magento cache:flush
```

Confirme que existem produtos no catálogo antes de começar a desenvolver funcionalidades que dependem deles.

### 7. Acessar a loja

- **Loja:** https://final-project.test
- **Admin:** https://final-project.test/admin
- **Usuário local padrão:** `john.smith`
- **Senha local padrão:** `password123`

As credenciais vêm de `env/magento.env` e são apenas valores padrão para o ambiente local. Não reutilize essas credenciais em ambientes públicos ou de produção.

### 8. Comandos do dia a dia

```bash
# Iniciar o ambiente
bin/start

# Parar os containers sem apagar os dados
bin/stop

# Atualizar dependências depois de mudanças no Composer
bin/composer install

# Aplicar atualizações de módulos e do banco
bin/magento setup:upgrade

# Reindexar e limpar o cache
bin/magento indexer:reindex
bin/magento cache:flush
```

Evite `bin/removeall`: ele remove o ambiente e pode apagar dados necessários, incluindo o banco local.

### 9. Depois de atualizar a branch

Após `git pull`, confira os arquivos alterados e execute os comandos necessários:

```bash
# Se composer.json ou composer.lock mudou
bin/composer install

# Se houve alterações de módulos ou estrutura do banco
bin/magento setup:upgrade

# Se src/app/etc/config.php mudou
bin/magento app:config:import

# Ao final
bin/magento cache:flush
```

Não é necessário executar todos os comandos cegamente a cada `git pull`; use os que correspondem às alterações recebidas.

### 10. Fluxo de trabalho da equipe

Crie uma branch para cada tarefa e abra um Pull Request. Não desenvolva diretamente na `main`. O padrão de nome das branches está em [Nomenclatura de Branch](#nomenclatura-de-branch) e as regras de revisão em [Pull Requests e revisão](#pull-requests-e-revisão).

```bash
git switch main
git pull origin main
git switch -c feat/f2.1-short-description
```

Antes de enviar as alterações:

```bash
git status
git diff --check
git add <arquivos>
git commit -m "<type>: <short description in English>"
git push -u origin feat/f2.1-short-description
```

Diretrizes:

- Código personalizado deve ficar em `src/app/code/Nuvem/` ou `src/app/design/frontend/Nuvem/`.
- `src/composer.json`, `src/composer.lock` e `src/app/etc/config.php` fazem parte do repositório.
- Não faça commit de `src/auth.json`, `src/app/etc/env.php`, senhas, chaves ou outros segredos.
- Se adicionar uma dependência Composer, valide e versione as mudanças necessárias em `composer.json` e `composer.lock`.
- O banco de dados local não é compartilhado pelo Git. Combine com a equipe antes de exportar ou importar dumps; não commite dumps com dados sensíveis.

### 11. Problemas comuns

#### Uma porta já está em uso

Encerre o processo ou o outro projeto que está usando a porta e tente iniciar novamente. Não encerre serviços do sistema sem antes confirmar que estão ocupando a porta necessária.

#### O Magento abre com erro 502/500 logo após iniciar

Confira se os containers estão saudáveis e aguarde os serviços de banco de dados e busca terminarem a inicialização:

```bash
bin/docker-compose ps
bin/docker-compose logs --tail=100
```

#### O Admin solicita autenticação de dois fatores e não permite entrar

Somente no ambiente local de desenvolvimento, se a equipe decidiu desabilitar o 2FA, execute:

```bash
bin/magento module:disable Magento_AdminAdobeImsTwoFactorAuth Magento_TwoFactorAuth
bin/magento cache:flush
```

#### A loja abre, mas não existem produtos

Os módulos de Sample Data instalados via Composer não significam que os dados já foram gravados no banco. Execute a etapa [6. Instalar os produtos de demonstração](#6-instalar-os-produtos-de-demonstração-sample-data) e confira se o banco utilizado é o do ambiente local correto.

#### O Git informa arquivos de volume ausentes ao trocar de modo

Se for necessário alternar entre o modo `--no-dev` e o modo de desenvolvimento, sincronize os arquivos do container conforme necessário e reinicie o ambiente:

```bash
bin/copyfromcontainer --all
bin/restart
```

### 12. Verificação rápida

```bash
bin/magento --version
bin/magento setup:db:status
bin/docker-compose ps
```

## Regras do Time

### Frentes de Trabalho

Cada frente é responsável pela sua própria pasta no repositório, evitando que duas pessoas editem o mesmo arquivo simultaneamente. A distribuição foi definida por sorteio.

| Frente   | Responsável     | Foco                                              |
| :------- | :-------------- | :------------------------------------------------ |
| Frente 1 | @samuelcosta-pd | Núcleo: tipo de produto, regras e API de carrinho |
| Frente 2 | @gustavodanjos  | Preço, estoque e pedido                           |
| Frente 3 | @jhonatanLobo   | O estúdio                                         |
| Frente 4 | @AntonniSMoraes | Bastidores                                        |
| Frente 5 | @Emiliano-Souza | Vitrine e integrações                             |

A Frente 0 (repositório, ambiente e regras do time) é responsabilidade de todos. O dono de cada módulo está registrado em [`docs/CODEOWNERS`](docs/CODEOWNERS).

### Pull Requests e revisão

- Ninguém dá push direto na `main`. Todo trabalho vai por branch e Pull Request.
- A `main` é protegida: o PR exige 1 aprovação, e o merge só acontece depois da revisão de outra frente, sempre de outra frente.
- O autor do PR não aprova o próprio PR.
- Antes de abrir o PR, rode `kit/scripts/prova-final.sh` e preencha no template o resultado ("Rodei a prova-final.sh e o resultado foi \_\_\_").
- O template do PR (`docs/pull_request_template.md`) inclui o checklist de marca. Preencha todos os itens.
- O código da Nuvem fica somente em `src/app/code/Nuvem/` e `src/app/design/frontend/Nuvem/`.

### Nomenclatura de Branch

```text
<type>/<task-id>-<short-description-in-english>
```

Os tipos são os mesmos dos [Conventional Commits](#commits-conventional-commits): `feat`, `fix`, `docs`, `refactor`, `test`, `chore` e `style`.

Exemplos (ilustrativos; use o ID real da tarefa no backlog):

```text
chore/f0.1-repository-and-team-rules
feat/f1.1-studio-svg-preview
fix/f1.1-studio-mobile-layout
feat/f2.3-price-calculator-service
test/f2.4-rule-validator-unit-tests
docs/f0.1-readme-team-rules
```

## Padrões de Código e Desenvolvimento

### Idioma e Nomenclatura

Todo o código (variáveis, classes, métodos, nomes de arquivos e mensagens de commit) é mantido **obrigatoriamente em inglês**, seguindo a convenção corporativa e garantindo que qualquer desenvolvedor consiga entender o repositório. A documentação (como este README) pode ser em português.

### Clean Code

Estas regras devem ser seguidas em todo o desenvolvimento:

- **KISS** (_Keep It Simple, Stupid_): soluções simples e diretas são preferidas a abstrações desnecessárias.
- **DRY** (_Don't Repeat Yourself_): lógica duplicada deve ser extraída para Services ou helpers reutilizáveis.
- **YAGNI** (_You Aren't Gonna Need It_): nenhuma funcionalidade é adicionada sem demanda real e justificada.
- **Separation of Concerns**: templates `.phtml` e layout XML cuidam da apresentação, Blocks e ViewModels fornecem os dados, e Services/Models concentram a lógica de negócio.
- **Responsabilidade Única**: cada método ou classe tem um único motivo para existir e mudar.

### Commits (Conventional Commits)

Todos os commits seguem o padrão **Conventional Commits**, com mensagem em inglês:

```text
<type>(<optional scope>): <short description in English>
```

| Tipo       | Uso                                             |
| :--------- | :---------------------------------------------- |
| `feat`     | Nova funcionalidade ou componente               |
| `fix`      | Correção de bug                                 |
| `docs`     | Alteração somente em documentação               |
| `refactor` | Melhoria de código sem alterar comportamento    |
| `test`     | Adição ou correção de testes                    |
| `chore`    | Configuração, build ou tarefas auxiliares       |
| `style`    | Formatação, espaçamento (sem impacto funcional) |

**Exemplos:**

```text
feat: add component grid to the admin panel
fix: correct engraving validation on add to cart
docs: document clean installation routine
chore: configure phpunit and static analysis
```
