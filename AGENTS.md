# AGENTS.md

## Escopo

Projeto PHP 8.3 com MySQL 5.7 para site publico e painel administrativo da IASD Central.

## Regras

- Consulte `/home/carlos/dev/projetos/CONTEXTO_DESENVOLVIMENTO.md` antes de alterar este projeto.
- Exponha somente `public/` no Apache.
- Nao versione credenciais reais; use `env.php` local privado.
- Use PDO preparado via repositories ou classes de infraestrutura.
- Mantenha CSRF em todas as operacoes POST.
- Escape saida HTML com `e()`.
- O menu deve permanecer compativel com `appgm` e o contrato `gm-menu/v1`.
- IDs publicados pelo `appgm` nao devem ser recriados ou reutilizados.
- Modulos devem compartilhar os mesmos usuarios, perfis, acessos e sessoes.

## Validacao

```bash
find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l
node --check public/assets/js/app.js
```

