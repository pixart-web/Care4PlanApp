# Care4All upload-ready

Esta pasta contem uma versao auto-contida do Care4All para alojamento partilhado/cPanel.

Nao precisa de Composer, npm, Laravel, artisan ou instalacao no servidor.

## Requisitos do servidor

- PHP 8.1 ou superior
- Extensao PHP `pdo_sqlite` ativa
- Permissao de escrita na pasta `data`

## Como publicar

1. Envie todo o conteudo da pasta `upload-ready` para a pasta do dominio/subdominio.
   - Exemplo: `public_html/care4all`
   - Ou diretamente para o document root do subdominio.
2. Confirme que a pasta `data` tem permissoes de escrita.
3. Abra o dominio no browser.
4. A base de dados `data/care4all.sqlite` sera criada automaticamente no primeiro acesso.

## Login inicial

- Nome de utilizador: `Tiago`
- Nome de utilizador: `Andreia`
- Nome de utilizador: `Fernanda`
- Palavra-passe para os 3: defina `CARE4ALL_INITIAL_ADMIN_PASSWORD` no servidor ou crie `config-local.php` a partir de `config-local.example.php` antes da primeira execução.

Altere a password depois do primeiro login em Utilizadores. Não envie o ficheiro `config-local.php` para Git.

## Dados e backups

Todos os dados ficam no ficheiro:

```text
data/care4all.sqlite
```

Para fazer backup, descarregue esse ficheiro regularmente.

## Seguranca

As pastas incluem `.htaccess` para bloquear acesso direto ao ficheiro SQLite em servidores Apache/cPanel.
Se o servidor não usar Apache, proteja manualmente a pasta `data`.

## FullCalendar

O calendario usa FullCalendar por CDN. Isto evita build/npm no servidor.
Se quiser funcionar 100% offline, descarregue os ficheiros FullCalendar e ajuste os links em `views/scales.php`.



