# IASD Central

Site publico e sistema administrativo da IASD Central.

## Stack

- PHP 8.3 no host
- Apache no host
- MySQL 5.7 em Docker
- PHP nativo com PDO, sessoes PHP, CSRF e views PHP

## Estrutura

- `public/`: unica pasta publica do sistema
- `app/`: Core, controllers e repositories
- `resources/views/`: templates PHP
- `database/`: schema e seeds MySQL 5.7
- `menu.bin`: menu compativel com `appgm` (`gm-menu/v1`)

## Configuracao local

```bash
cd /home/carlos/dev/projetos/iasdaraxa
cp env.example.php env.php
```

Edite `env.php` com as credenciais locais do MySQL 5.7.

## Banco

Importe em uma base MySQL 5.7:

```bash
database/schema.sql
database/seeds.sql
```

Usuario inicial:

- E-mail: `admin@iasdaraxa.local`
- Senha: `IasdCentral@123`

Troque a senha inicial antes de expor o sistema.

## Apache local

URL esperada no ambiente local:

```text
http://localhost/iasdaraxa
```

Alias esperado, no mesmo estilo do `apprpg`:

```apache
RedirectMatch 301 ^/iasdaraxa$ /iasdaraxa/
Alias /iasdaraxa/ /home/carlos/dev/projetos/iasdaraxa/public/

<Directory /home/carlos/dev/projetos/iasdaraxa/public>
    Options FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
```

Esse alias deve expor somente `public/`, mantendo `app/`, `config/`, `database/`,
`resources/`, `routes/`, `storage/`, `env.php` e `menu.bin` fora da raiz publica.
