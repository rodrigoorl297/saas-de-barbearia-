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
- [ ] Senha do dono alterada no setup
- [ ] Logo e nome do **app do cliente** em Configurações
- [ ] Mercado Pago (se for vender planos)
- [ ] WhatsApp Meta (se for disparar campanhas)
- [ ] Pasta `data/` inacessível pela web (`.htaccess` já bloqueia)

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
- Demo aberta (opcional): `CLIENT_DEMO_OPEN=true` no `.env`

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
