# Compatibilidade com appgm

O projeto nasce compativel com o contrato `gm-menu/v1`.

## Arquivo publicado

- Arquivo esperado: `menu.bin`
- Local no projeto: raiz de `/home/carlos/dev/projetos/iasdaraxa`
- Schema: `gm-menu/v1`
- Checksum: `sha256:` calculado sobre o array `items` com `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`

## IDs iniciais

- `1000`: Administrativo
- `1010`: Dashboard
- `1020`: Obras
- `1030`: Escalas
- `1040`: Tarefas
- `1050`: Agenda geral

Esses IDs devem permanecer estaveis porque `access_rules.menu_id` referencia `menus.id`.

## Configuracao sugerida no appgm

```json
{
  "name": "IASD Central",
  "slug": "iasdaraxa",
  "description": "Menu central do projeto IASD Central",
  "local_directory": "/home/carlos/dev/projetos/iasdaraxa",
  "base_path": "/iasdaraxa"
}
```

Depois de cadastrar o projeto no `appgm`, publique localmente para substituir `menu.bin`.
