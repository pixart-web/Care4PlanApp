# Checklist de publicação cPanel — não executada

Esta intervenção não faz deploy nem merge. O pacote é uma atualização incremental da versão `c2fce01`; não é uma instalação nova e não deve substituir uma instalação desconhecida sem comparar versões.

## Antes da aprovação

- [ ] Rever PR, inventário de riscos e diff. Confirmar que se trata da instalação standalone em `upload-ready/`.
- [ ] Confirmar a versão PHP efetivamente selecionada no domínio e no CLI. Testado localmente: 8.5.8; código usa sintaxe PHP 8.1+. A combinação exata do alojamento deve passar os testes numa cópia isolada antes da publicação.
- [ ] Confirmar `PDO`, `pdo_sqlite`, `session`, `date`, `filter`, `json` e password hashing; para o backup CLI, extensão `sqlite3` com `SQLite3::backup`. Confirmar SQLite com suporte ao UPSERT já usado pela aplicação.
- [ ] Confirmar Apache 2.4, leitura de `.htaccess`/AllowOverride e configuração HTTPS. Não confiar na proteção de `.htaccess` em Nginx sem regras equivalentes.
- [ ] Identificar `DB_FILE` e `config-local.php` reais sem divulgar o seu conteúdo. Preferir base e backups fora de `public_html`; não alterar estes caminhos durante esta atualização.
- [ ] Confirmar proprietário/permissões: o processo PHP precisa de escrever no diretório privado da SQLite e nos ficheiros auxiliares WAL/SHM, sem permissões mundiais `777`. A política concreta depende do utilizador cPanel/PHP.
- [ ] Guardar cópia privada dos cinco ficheiros atuais e da configuração; registar hashes e versão anterior. A configuração não é parte do ZIP.

## Backup consistente e ensaio de recuperação

- [ ] Acordar uma janela de manutenção e impedir novas escritas durante a troca dos ficheiros.
- [ ] Executar o utilitário de backup existente com caminhos absolutos, origem real e destino **novo**, privado, fora de qualquer webroot da conta. Exemplo a adaptar pelo operador: `php maintenance/backup.php --source=/caminho/privado/care4all.sqlite --destination=/caminho/privado/backups/care4all-antes-atualizacao.sqlite`.
- [ ] Não copiar apenas o ficheiro `.sqlite` enquanto há escrita/WAL ativo. O backup usa a API SQLite, incluindo transações confirmadas em WAL.
- [ ] Numa cópia do backup, executar `PRAGMA integrity_check` (esperado `ok`) e `PRAGMA foreign_key_check` (esperado vazio); comparar contagens e alguns registos de referência sem publicar informação pessoal.
- [ ] Ensaiar abertura da cópia e acesso numa instalação privada de staging com a mesma versão PHP. Não executar `tests/run.php` sobre dados reais: os testes criam fixtures próprias e recusam configuração local.
- [ ] Confirmar armazenamento/retensão e acesso ao backup. O utilitário bloqueia o webroot da aplicação, mas o operador deve excluir também outros webroots da conta cPanel.

## Atualização após aprovação

- [ ] Validar o conteúdo do ZIP e o hash do ficheiro recebido. Não contém base, credenciais, config, assets, Laravel, logs, testes ou dependências.
- [ ] Publicar apenas `bootstrap.php`, `index.php`, `lib/security.php`, `lib/events.php` e `views/reports.php` nos respetivos locais da aplicação.
- [ ] Preferir staging dos ficheiros e substituição em manutenção. Publicar `lib/events.php` e `lib/security.php` antes de `index.php` se a troca não for atómica; não deixar pedidos correr com versões misturadas.
- [ ] Não enviar `documentacao/` para a raiz pública; guardar para o operador.
- [ ] Não substituir `config.php`, `config-local.php`, `data/` ou qualquer SQLite/WAL/SHM. Não executar seeds, scripts de limpeza ou migrações manuais em produção.
- [ ] Esta alteração não introduz DDL nem versões novas de esquema. O bootstrap existente continua a verificar compatibilidade; numa base antiga não auditada pode aplicar as migrações anteriores. Por isso a versão instalada deve ser confirmada primeiro.
- [ ] Invalidar/recarregar OPcache pelo mecanismo do alojamento se necessário, mantendo a configuração privada.

## Validação antes de reabrir

- [ ] Login/logout e três perfis, cookies Secure/HttpOnly/SameSite em HTTPS; verificar também eventual proxy e ausência de sessão na query string.
- [ ] Sem erros no log após login, calendário, utentes, cuidadoras, relatórios, utilizadores, finanças, Pocket e perfil.
- [ ] Testar bloqueio de pedidos a `config.php`, `config-local.php`, `bootstrap.php`, `/lib/`, `/views/`, `/data/` e a ficheiros fictícios com extensões SQLite/WAL/SHM/backup. Não criar cópias de dados reais públicas para testar.
- [ ] Confirmar que `/maintenance/backup.php` pela web responde 404 e que não há listagem de diretórios.
- [ ] Comparar contagens, valores financeiros e históricos anteriores usando consultas de leitura; nenhuma correção histórica está prevista.
- [ ] Verificar o desenho aprovado em desktop/mobile, abertura do serviço e campos do calendário CARER após a alteração da API.
- [ ] Só fazer testes de criação/cancelamento numa base de staging fictícia; não criar serviços de teste na produção.

## Reversão

- [ ] Voltar a manutenção e restaurar os cinco ficheiros PHP da versão anterior, como conjunto coerente. O novo `lib/events.php` pode permanecer sem ser carregado pela versão anterior; não é preciso apagar dados para reverter.
- [ ] Recarregar OPcache e validar login/calendário/relatórios. A reversão de código não requer reversão da base porque não há mudança de esquema nesta intervenção.
- [ ] **Não restaurar automaticamente um backup sobre a base ativa.** Isso perderia operações válidas feitas entretanto. Se houver dano confirmado nos dados, parar escritas, preservar o estado atual e elaborar recuperação separada com autorização e reconciliação.
- [ ] Registar versão/hora e resultado. Nenhum destes passos foi executado no servidor nesta auditoria.
