# Barba Flow — sistema para barbearias

Produto comercializável em **instalação única por barbearia** (hospedagem compartilhada PHP).

## O que inclui

1. **App do cliente** (`/cliente/`) — agendamento, planos, conta  
2. **Painel do dono** (`/dono/`) — agenda, serviços, financeiro, marketing, configs  
3. **Painel do barbeiro** (`/barbeiro/`) — agenda do dia  

A marca do **painel** (software) é fixa. Logo/nome da **barbearia** valem só no app do cliente.

---

## Requisitos

- PHP **8.1+**
- Extensões: `json`, `session`, `curl`, `pdo_mysql` (produção)
- Apache com `mod_rewrite` (ou Nginx equivalente bloqueando `/data` e `.env`)

### Banco de dados (escala)

- **1 banco MySQL por barbearia** (ex.: `barbaflow`, `bf_outra_loja`)
- Schema: [`sql/barbearia_schema.sql`](sql/barbearia_schema.sql)
- Cadastro de lojas (opcional): [`sql/plataforma_barbearias.sql`](sql/plataforma_barbearias.sql)
- Tabelas: `usuarios` (visões `donos` / `barbeiros` / `clientes`), `produtos`, `servicos`, `agendamentos`, `configuracoes`, etc.

No `.env` da hospedagem:

```env
DB_ENABLED=true
DB_HOST=localhost
DB_NAME=barbaflow
DB_USER=barbaflow
DB_PASS=sua_senha
```

Depois abra `/instalar-banco.php` uma vez (cria tabelas + importa JSON se o banco estiver vazio) e **apague** o arquivo.

---

## Instalação (hospedagem)

1. Envie os arquivos para a pasta pública (`public_html` / `www`)
2. Copie `.env.example` → `.env` e ajuste
3. Garanta permissão de escrita em `data/` e `uploads/`
4. Acesse `/dono/login.php`
5. Na **primeira vez**, complete o **setup** (nome da loja + senha do dono)

### Checklist de produção

- [ ] `CLIENT_DEMO_OPEN=false` no `.env`
- [ ] `APP_ENV=production` no `.env`
- [ ] `APP_KEY` configurada (`php -r "echo base64_encode(random_bytes(32));"`) — criptografa tokens de MP/WhatsApp salvos em Configurações
- [ ] Senha do dono alterada no setup
- [ ] Senha do barbeiro seed (`barbeiro123`) alterada
- [ ] `instalar-banco.php` removido do servidor após instalação
- [ ] Logo e nome do **app do cliente** em Configurações
- [ ] Mercado Pago configurado (se for vender planos) — ver [Cobrança recorrente de planos](#cobrança-recorrente-de-planos) abaixo
- [ ] WhatsApp Meta (se for disparar campanhas)
- [ ] Pastas `data/`, `sql/` e `cron/` inacessíveis pela web (`.htaccess` já bloqueia)

---

## Configuração (`.env`)

```env
PRODUCT_NAME=Barba Flow
CLIENT_DEMO_OPEN=false
MP_PUBLIC_KEY=
MP_ACCESS_TOKEN=
WA_PHONE_NUMBER_ID=
WA_ACCESS_TOKEN=
```

Chaves de MP e WhatsApp também podem ser salvas em **Configurações** no painel.

---

## Acesso inicial (instalação nova)

Após o seed automático:

| Perfil   | E-mail              | Senha inicial |
|----------|---------------------|---------------|
| Dono     | `admin@loja.local`  | `dono123`     |
| Barbeiro | `barbeiro1@loja.local` | `barbeiro123` |

Troque no setup / perfil antes de entregar ao cliente.

---

## Local (desenvolvimento)

```bash
copy .env.example .env
php -S localhost:8080 router.php
```

- Cliente: http://localhost:8080/cliente/  
- Dono: http://localhost:8080/dono/login.php  
- Demo aberta (opcional): `APP_ENV=development` **e** `CLIENT_DEMO_OPEN=true` no `.env` (por segurança, `CLIENT_DEMO_OPEN` é sempre ignorado quando `APP_ENV=production`, mesmo que esteja `true`)

---

## Cobrança recorrente de planos

Assinaturas de plano (`/cliente/agendar-plano.php`) renovam mensalmente, mas a cobrança
**não é automática por padrão** — é preciso agendar o script de cron:

```bash
# crontab da hospedagem, uma vez ao dia
0 6 * * * php /caminho/do/site/cron/cobrar-renovacoes.php >> /caminho/do/site/data/cron.log 2>&1
```

O script cobra apenas assinaturas ativas com `renews_at` vencido, é seguro rodar mais
de uma vez por dia (idempotente — não cobra duas vezes o mesmo ciclo) e nunca roda
duas instâncias em paralelo. Sem esse cron configurado, o cliente vê "Será renovado
em..." na conta dele, mas o cartão nunca é cobrado de novo automaticamente.

---

## Programa de fidelidade

Configurado em **Dono > Fidelidade**: pontos por R$ gasto, faixas de nível
(Bronze/Prata/Ouro), multiplicador de pontos por nível e recompensas resgatáveis.
O cliente acompanha pontos, nível, progresso e recompensas em **Conta**.

Três mecânicas dependem de um cron diário (todas opcionais — com os campos zerados
em *Regras do programa*, o script não faz nada):

```bash
# crontab da hospedagem, uma vez ao dia
0 7 * * * php /caminho/do/site/cron/fidelidade-diaria.php >> /caminho/do/site/data/cron.log 2>&1
```

| Mecânica | Como funciona |
|---|---|
| Aniversário | Credita os pontos no dia, uma vez por ano, para clientes com data de nascimento cadastrada |
| Expiração | Zera pontos após N dias sem pontuar e avisa o cliente 15 dias antes |
| Indicação | Não usa cron: o código sai na Conta do cliente e o bônus cai para os dois lados quando o indicado conclui o 1º atendimento |

---

## Modelo de venda

- **1 barbearia = 1 instalação** (ZIP / FTP)
- Não é multi-tenant na mesma pasta
- White-label do app do cliente via Configurações
- Marca do software permanece a do produto

---

## Observações

- **WhatsApp:** exige conta Meta Cloud API + templates aprovados; sem token, Disparar não envia
- **CSRF** ativo em formulários POST
- Login do painel bloqueia após 5 tentativas (15 min)

---

## Arquitetura (para quem for dar manutenção)

Guia rápido para um dev entendendo o projeto pela primeira vez — não é um guia de uso,
é sobre como o código está organizado.

### Estilo geral

PHP procedural, sem framework: cada página em `dono/`, `barbeiro/` e `cliente/` é um
arquivo único que lida com o POST, aplica a regra de negócio e imprime o HTML, tudo
junto (não há controllers/services separados). A lógica reutilizável entre páginas
fica em `includes/` (funções globais) e `config/database.php` (acesso a dados). Não
há build step no frontend — `assets/js/*.js` é JS puro, `assets/css/app.css` é CSS puro.

### Persistência dupla (JSON ou MySQL)

O mesmo código roda em dois modos, controlados por `DB_ENABLED` no `.env`:

- **Padrão (`DB_ENABLED=false`)**: cada "tabela" é um arquivo `data/{tabela}.json`
  (array de linhas). `store_read()`/`store_write()` em `config/database.php` leem/escrevem
  o arquivo inteiro a cada operação — simples, mas **reescreve o arquivo todo a cada
  gravação** (sem update parcial de linha).
- **MySQL (`DB_ENABLED=true`)**: `config/mysql.php` mapeia essas mesmas "tabelas
  lógicas" para tabelas reais via `db_table_map()`. `db_replace_all()` também
  **apaga e reinsere todas as linhas da tabela a cada escrita** (dentro de uma
  transação) — o mesmo padrão de "reescrever tudo", só que em SQL.

Por causa disso, **duas escritas concorrentes na mesma tabela lógica podem se
sobrescrever** (a segunda escrita, que leu o estado antes da primeira salvar,
sobrescreve o resultado da primeira ao salvar por cima). O agendamento (`cliente/confirmar.php`)
já tem uma trava (`acquire_barber_agenda_lock()`) para o caso mais crítico
(dois clientes reservando o mesmo horário), mas esse padrão de "reescrever a
tabela inteira" continua valendo para as outras tabelas — uma refatoração maior
para update/insert/delete direto por linha resolveria isso de vez, se algum dia
o volume de escritas simultâneas justificar o esforço.

Toda função que lê/escreve dados (`save_appointment`, `save_user`, `settings()` etc.)
funciona nos dois modos sem saber qual está ativo — a escolha acontece dentro de
`store_read()`/`store_write()`.

### Roteamento

Não há um router de verdade. `router.php` só existe para dar uma URL bonita
(`/{slug-da-loja}/...`) ao app do cliente — ele reescreve internamente para
`cliente/...` e segue a execução normal (sem redirect de verdade). Todo o resto
(`dono/`, `barbeiro/`, `api/`) é acessado pelo caminho real do arquivo.

### Onde procurar cada coisa

| O quê | Onde |
|---|---|
| Autenticação (staff e cliente) | `includes/auth.php` |
| Regras de negócio (agenda, clientes, slugs) | `includes/functions.php` |
| Acesso a dados (JSON e MySQL) | `config/database.php`, `config/mysql.php` |
| Layout/HTML compartilhado | `includes/layout.php` |
| Integração Mercado Pago | `includes/mercadopago.php` |
| Integração WhatsApp | `includes/whatsapp.php` |
| Cobrança recorrente (cron) | `cron/cobrar-renovacoes.php` |
| Rotinas de fidelidade (cron) | `cron/fidelidade-diaria.php` |
| Schema MySQL | `sql/barbearia_schema.sql` |
