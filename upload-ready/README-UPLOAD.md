# Care4All upload-ready

Esta pasta contém a Care4All V2, em PHP e SQLite, para alojamento partilhado/cPanel.

Nao precisa de Composer, npm, Laravel, artisan ou instalacao no servidor.

## Requisitos do servidor

- PHP 8.3 ou superior, numa versão ainda suportada pelo PHP
- Extensao PHP `pdo_sqlite` ativa
- Permissao de escrita na pasta `data`

## Como publicar

Para atualizar uma instalação existente, siga primeiro `DEPLOY-CARE4ALL-V2.md`
na raiz do repositório. Preserve a base de dados e `config-local.php`.
O ZIP de atualização contém apenas código. Não foi executado deploy automático.

1. Envie o conteúdo desta pasta para o domínio/subdomínio, após backup validado e bloqueio temporário de acessos.
   - Exemplo: `public_html/care4all`
   - Ou diretamente para o document root do subdominio.
2. Confirme que a pasta `data` tem permissoes de escrita.
3. Abra o dominio no browser.
4. Numa instalação nova, a base é criada após configurar uma palavra-passe inicial única (mínimo de 12 caracteres). Numa atualização, a base existente recebe apenas alterações incrementais.

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

Não copie apenas um SQLite ativo: pode haver dados confirmados no WAL.
Use a API de backup SQLite por `maintenance/backup.php` (apenas CLI, tarefa única
no Cron cPanel ou suporte), ou um snapshot consistente com a aplicação parada.
O script valida a integridade e recusa sobrescrever ficheiros. Consulte o guia
de publicação para validação e recuperação. Os backups ficam fora da raiz pública.

## Seguranca

As pastas incluem `.htaccess` para bloquear acesso direto ao ficheiro SQLite em servidores Apache/cPanel.
Se o servidor não usar Apache, proteja manualmente a pasta `data`.

## FullCalendar

O calendário da cuidadora usa FullCalendar 6.1.11 incluído em assets/vendor,
com tradução PT-PT e licença. Não depende de CDN nem de build no servidor.
O calendário operacional do admin, a lista mobile e a repetição são locais.

## Escalas E Histórico

O dia apresenta até oito cuidadoras por página e todas as 24 horas. Existem
também semana, mês e lista, com filtros de concelho, estado, cliente, cuidadora
e pesquisa. A repetição permite dias úteis, dias selecionados e intervalos,
com pré-visualização obrigatória e máximo de 120 ocorrências por lote.

Pedidos repetidos não duplicam serviços; conflitos são verificados novamente
durante a gravação. Pode escolher criar apenas as ocorrências sem conflito.
Horas inexistentes ou ambíguas na mudança de hora legal são recusadas.

Para preservar histórico, fichas com serviços/relatórios não são eliminadas.
As escalas devem ser canceladas. Os relatórios apagados são arquivados na base.
As regras de Pocket e finanças foram preservadas e estão descritas na auditoria.



