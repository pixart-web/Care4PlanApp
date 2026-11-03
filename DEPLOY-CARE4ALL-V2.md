# Publicação Care4All V2 No cPanel

Não foi efetuado deploy nem merge. Publicar apenas após aprovação da revisão.
O alvo é exclusivamente o conteúdo de `upload-ready/`.

## 1. Preparação

1. Criar um subdomínio de teste protegido, com HTTPS e PHP 8.1+ (`pdo_sqlite`). A ferramenta opcional de backup precisa também de `sqlite3`.
2. Confirmar que os ficheiros .htaccess são respeitados, `AllowOverride` está ativo e a pasta da base permite escrita ao utilizador PHP. Não usar permissões 777.
3. Registar a versão atual e guardar uma cópia dos ficheiros da aplicação fora de public_html.
4. Não publicar tests/, .runtime/, tools/, logs, .git/ ou pastas Laravel. Nunca incluir a base SQLite ou config-local.php num commit/ZIP de código.
5. Recomenda-se guardar a base fora de public_html, configurando DB_FILE em config-local.php. Não mover a base durante o primeiro upgrade sem um procedimento separado e validado.

## 2. Backup Consistente

Antes da primeira abertura da V2, bloquear temporariamente o acesso no cPanel (Directory Privacy ou regra Apache), incluindo acessos das cuidadoras. Aguardar o fim de pedidos em curso. Manter a aplicação indisponível durante backup, upload e validação inicial.

Opção sem SSH: pedir ao suporte um snapshot SQLite consistente ou executar uma única tarefa Cron no cPanel, usando a ferramenta CLI incluída. A tarefa não deve ficar recorrente:

```text
/usr/local/bin/php /home/CONTA/app/maintenance/backup.php --source=/home/CONTA/app/data/care4all.sqlite --destination=/home/CONTA/backups/care4all-pre-v2.sqlite
```

Substituir pelos caminhos reais e pelo executável PHP indicado pelo alojamento. O destino deve ser novo, privado e fora da pasta pública da aplicação. A ferramenta não corre pela web, não faz migrações, usa a API de backup SQLite (incluindo WAL confirmado), valida `PRAGMA integrity_check` e imprime um SHA256. Exige sqlite3 com backup API. Nunca sobrescreve backups.

Se Cron não estiver disponível, usar o suporte/cópia de segurança completa do cPanel com a aplicação totalmente parada e pedir validação da base restaurada. Não assumir que copiar um .sqlite ativo produz um backup consistente. Preservar também configuração privada e eventuais ficheiros -wal/-shm no snapshot congelado. Não remover estes ficheiros manualmente.

Guardar o backup e o hash fora da raiz pública. Confirmar a possibilidade de restaurar numa área isolada e testar login, contagens de serviços e integridade antes de avançar.

## 3. Upload

1. Extrair o pacote de código numa pasta temporária privada.
2. Conferir que não contém `care4all.sqlite`, configuração local, dados de testes ou logs.
3. Substituir de forma controlada os ficheiros da aplicação pelo conteúdo do pacote, incluindo assets/, lib/, views/, maintenance/ e todos os .htaccess.
4. Manter integralmente `data/` e `config-local.php` atuais. Não apagar a pasta de publicação inteira. O pacote contém apenas a proteção data/.htaccess, nunca a base.
5. Preservar o caminho da base atual. Não copiar config-local.example.php por cima de config-local.php.

Instalação nova apenas: definir uma palavra-passe inicial única com pelo menos 12 caracteres em `CARE4ALL_INITIAL_ADMIN_PASSWORD` ou config-local.php. Não existe password por defeito. Contas existentes não dependem desta configuração e nunca são redefinidas pelo seed.

## 4. Migração

A primeira abertura autenticada liga à base e executa migrações idempotentes. Acrescenta reports.deleted_at, tabelas auxiliares, índices e triggers. Não exige Composer, npm, SSH, artisan, tarefas residentes ou comando de migração.

Se ocorrer erro, manter o acesso bloqueado, consultar apenas a referência de erro e confirmar permissões/extensões/espaço em disco. Não eliminar a base para tentar resolver. Uma migração interrompida pode ser repetida porque os passos são aditivos e idempotentes.

## 5. Validação No Alojamento

- Confirmar HTTPS e redirecionamento HTTP para HTTPS no cPanel; cookies Secure em HTTPS, HttpOnly e SameSite=Lax. Não confiar em cabeçalhos de proxy fornecidos pelo cliente.
- Testar sem sessão pedidos a data/care4all.sqlite, data/care4all.sqlite-wal, config-local.php, bootstrap.php, lib/security.php e views/patients.php. Devem dar 403/404 e nunca conteúdo. Manter acesso bloqueado se alguma proteção falhar.
- Entrar com a password existente de um administrador e de uma cuidadora. Confirmar que hashes/passwords permanecem válidos após várias páginas.
- Comparar contagens e serviços existentes, valores de Pocket/finanças e relatórios antes/depois. Não usar dados reais num ambiente público de teste.
- Com registos fictícios em staging: criar uma escala, repetir segunda a sexta, verificar pré-visualização, conflitos noutro concelho, repetição do pedido, cancelamento/conclusão e respostas a relatórios.
- Validar calendário dia/semana/mês com utentes nas linhas e dias nas colunas, vários serviços por célula, filtros, detalhes ao passar o rato, edição com duplo clique, navegação mobile e calendário da cuidadora.
- Confirmar que utentes sem concelho continuam sem concelho e não foram convertidos arbitrariamente para Portimão.
- Verificar logs sem passwords, SQL ou dados clínicos. Reabrir acessos só depois de estes passos passarem.

## 6. Rollback

1. Bloquear acessos e aguardar pedidos em curso.
2. Guardar um novo backup consistente do estado V2, mesmo que se pretenda voltar atrás.
3. Restaurar os ficheiros e a configuração da versão anterior a partir da cópia privada.
4. Se ainda não houve novas escritas, restaurar a base validada pré-upgrade. Com a aplicação parada, restaurar um snapshot coerente; não misturar um .sqlite antigo com ficheiros WAL de outra versão. Pedir ao suporte esta operação quando necessário.
5. Se já houve escritas após a atualização, não substituir a base pelo backup antigo: perderia novas escalas/relatórios. Exportar/reconciliar os novos registos ou restaurar num ambiente isolado antes da decisão.
6. A V2 usa migrações aditivas; não há down migration destrutiva automática. A versão antiga tem um seed inseguro: restaurar código antigo sem corrigir esse seed pode voltar a redefinir passwords.
7. Validar integridade, contas, valores e permissões antes de reabrir.

## Limites Da Verificação Local

Os testes automáticos usam fixtures e PHP/Edge locais. Apache/cPanel, TLS, PHP 8.1 do alojamento, recuperação integral de conta e concorrência real exigem a validação acima. O procedimento não autoriza uma publicação automática.
