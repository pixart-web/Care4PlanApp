# Auditoria técnica Care4All — 3 de novembro de 2026

## Âmbito e conclusão

Base auditada: `c2fce01ae840decfa4296d58e68ac3e3dc2871cb`, branch `codex/technical-audit-stabilization`. Aplicação ativa: `upload-ready/`. Foram lidos o README, a auditoria V2, o procedimento de deploy e os testes existentes. O código Laravel histórico não foi alterado.

Não foi confirmado um problema P0. Foram corrigidas falhas de atomicidade, validação, exposição excessiva de campos e contenção desnecessária de escrita. Há riscos residuais abaixo; esta auditoria não certifica uma instalação cPanel à qual não se acedeu. Não houve deploy, merge, acesso à base de produção, redefinição de passwords existentes ou alteração de esquema.

Só cinco ficheiros de produção mudam: `index.php`, `bootstrap.php`, `lib/security.php`, `lib/events.php` (novo) e o bloco de consultas de `views/reports.php`. CSS, JavaScript, imagens, HTML dos componentes e fórmulas financeiras permanecem iguais. A API foi extraída do controlador para tornar explícito o seu contrato por perfil, sem uma refatorização geral.

## Problemas confirmados

| ID / gravidade | Evidência e impacto | Correção e estado |
|---|---|---|
| T01 — P1 | `index.php`: criação de relatório, perfil, cancelamento, conclusão, arquivo e marcação de leitura não tinham uma transação abrangendo todas as escritas. Um trigger de falha em auditoria deixou um relatório gravado apesar de HTTP 500; falhar o cancelamento deixou uma auditoria sem alteração correspondente. | Transações nas operações compostas, rollback explícito no dispatcher e rejeições. Testes de falha intermédia para relatório, perfil, cancelamento, conclusão e leitura. **Corrigido.** |
| T02 — P2 | Parâmetros `page[]=scales` e arrays em campos escalares causavam 500; casts de IDs podiam transformar `1oops` em `1`. Ações desconhecidas passavam silenciosamente. | Validação na fronteira HTTP, IDs inteiros, listas de recorrência planas, rejeição de NUL, limites de tamanho e ações conhecidas. 400 sem mutação em pedidos malformados. **Corrigido.** |
| T03 — P2 | `events_api()` selecionava `services.*` e copiava tudo para `extendedProps`: `created_at`, `updated_at`, `done_by_user_id` e identificadores internos desnecessários chegavam à cuidadora. | SELECT explícito e allowlist diferente para CARER e ADMIN/EDITOR. Mantidos os campos efetivamente consumidos pelo calendário. **Corrigido.** |
| T04 — P2 | `login_failed()` eliminava janelas com `<`, mas o bloqueio só considerava `>`. No segundo exato de expiração, uma janela com 5 tentativas era incrementada para 6 sem reiniciar o tempo e ficava fora do bloqueio. Não era uma falha permanente de todas as janelas expiradas. | Limpeza inclusiva `<=`; relógio injetável apenas na função interna para teste determinístico. Nova janela começa em 1, tentativas dentro dela não prolongam o início. **Corrigido.** |
| T05 — P2 | `save_patient`: `lastInsertId()` era lido depois do movimento, registando o ID do movimento como se fosse o do utente. Reproduzido com IDs 3 e 5. | Captura imediata do ID do utente. Arquivo sem alteração já não gera auditoria de sucesso; eliminação efetiva de utilizador passa a ter auditoria na mesma transação. **Corrigido para novas operações; histórico não reescrito.** |
| T06 — P2 | `save_carer`: redirects de username duplicado/password em falta entre begin e commit; exceções dependiam da destruição da ligação para desfazer. | Validação antes de abrir a transação; rollback explícito em exceções. Mantidas validações e mensagens existentes. **Corrigido.** |
| T07 — P1 | Verificação do último ADMIN em `validate_action()` ocorria fora da transação de `save_user`, permitindo decisões sobre uma contagem desatualizada entre operações concorrentes. | Validação e escrita na mesma transação/snapshot SQLite; eliminação de utilizador também transacional e autodeleção continua bloqueada. Testado último administrador e autodeleção; não executado ensaio de carga concorrente multiprocesso. **Corrigido por inspeção da fronteira transacional e regressão sequencial; limitação de carga documentada.** |
| T08 — P2 | Conclusão usava autorização por SELECT, mas UPDATE só por ID. Repetir `done_service` reescrevia a data de conclusão. | Mesma transação, predicados de proprietário e estado no UPDATE. `SCHEDULED → DONE` e `DONE → SCHEDULED`, como os botões já permitiam; repetição é um no-op; cancelados continuam 403. **Corrigido, sem atualizar serviços antigos.** |
| T09 — P2 | `is_numeric('1e309')` aceitava um valor que se converte em infinito; datas impossíveis eram aceites em utentes e normalizadas na API. | Rejeição de valores não finitos e de datas impossíveis antes de escrever/consultar. Os intervalos e fórmulas monetárias válidos não mudaram. **Corrigido.** |
| T10 — P2 | `seed()` executava BEGIN IMMEDIATE em instalações preenchidas; a migração V2 executava INSERT OR IGNORE em cada pedido. Ambos falharam sob um bloqueio de escrita fictício de outra ligação, mesmo sem necessidade de atualizar nada. | Consulta prévia no seed, mantendo a segunda verificação dentro da transação inicial; registo da versão só se ausente. Dois testes de contenção passaram após a correção. **Corrigido.** |
| T11 — P3 | `views/reports.php` carregava todos os campos de todas as cuidadoras e utentes antes de descartar os resultados para CARER. | Eliminadas duas consultas completas por acesso CARER; dropdowns operacionais só selecionam ID/nome. Sem mudança no HTML. **Corrigido.** |

## Inventário arquitetural e de segurança

| Área | Resultado |
|---|---|
| Configuração | `config.php` admite variável de ambiente/configuração privada; não foi alterado. A localização efetiva da base no cPanel precisa de confirmação pelo operador. |
| Autenticação | Hash de password, regeneração de sessão e CSRF novo no login; logout elimina sessão e cookie. Mensagem de falha genérica; limitação por conta/IP e por IP mantida. |
| Cookies | HttpOnly e SameSite=Lax; Secure depende de `HTTPS` reconhecido no PHP. Servidor de testes usa HTTP local, não prova Secure em cPanel/proxy. |
| Autorização | Aplicada no servidor; ADMIN/EDITOR operacionais, gestão de utilizadores/finanças só ADMIN, Pocket/perfil/conclusão só CARER. Matriz completa em documento próprio. |
| SQL / XSS | Valores SQL parametrizados nos caminhos inspecionados; SQL dinâmico de colunas/tabelas usa listas internas. Conteúdo HTML/atributos passa por `e()`, detalhes JS usam `textContent`. Não foi identificado SQL injection ou XSS explorável nestes caminhos. Isto não substitui um pentest independente. |
| Ficheiros e uploads | Não existe funcionalidade de upload na aplicação ativa. `.htaccess` bloqueia config/bootstrap/dados e diretórios internos. O servidor PHP local não interpreta `.htaccess`; falta verificar Apache real. |
| Erros e logs | HTTP 500 genérico com referência; log da aplicação só regista referência/classe. Não acrescentado logging de pedidos, SQL, dados clínicos ou credenciais. Logs de acesso do alojamento são configuração externa. |
| Recorrências | Pré-visualização ligada ao payload/sessão, limites, conflitos entre concelhos, BEGIN IMMEDIATE, replay por chave idempotente e rollback já estavam implementados. Mantidos e retestados. |
| Recursos | Assets visuais locais; único serviço externo funcional encontrado foi IPMA. Offline/dados antigos têm fallback. Sem nova dependência de produção. |
| Código extenso | `index.php` continua concentrado, mas a API saiu para módulo próprio. Não removido código histórico/dead code por conveniência. `preview.html` é artefacto de desenvolvimento, excluído do pacote incremental. |

## Base de dados e concorrência

Tabelas inspecionadas: `users`, `carers`, `patients`, `services`, `reports`, `report_replies`, `entity_movements`, `schema_migrations`, `login_attempts`, `audit_events`, `service_batches`.

Chaves de serviços para utentes/cuidadoras; relatórios para remetentes/utentes/cuidadoras; respostas para relatório/remetente. Triggers protegem histórico contra DELETE em utentes, cuidadoras e autores. `done_by_user_id` admite SET NULL ao remover uma conta sem histórico de relatórios; comportamento existente preservado. Roles/estados/direções têm CHECK e há UNIQUE de username/cor/horário inicial da cuidadora e chave idempotente.

Índices existentes cobrem intervalo de escalas, cuidadora/intervalo, concelho/tempo e relatórios por cuidadora. EXPLAIN QUERY PLAN confirmou utilização de índice na consulta por cuidadora e intervalo. Não foram acrescentados índices sem medição. O teste de esquema legado fictício conserva IDs, hashes e serviços após migrações repetidas; `integrity_check` retorna `ok`, `foreign_key_check` não devolve violações.

O seed mantém a proteção de primeira instalação concorrente. Verificações de esquema continuam em cada bootstrap por compatibilidade; agora não escrevem o marcador quando já existe. Transações SQLite podem ainda retornar erro de bloqueio sob carga; não se adicionou retry automático de escritas ou alteração global de journal mode. Operações falhadas devem ser repetidas conscientemente, com a proteção idempotente já existente nas recorrências.

O backup CLI usa a API SQLite de backup, origem readonly, destino novo privado e integrity_check. Teste confirma captura de dados confirmados em WAL, rejeição de overwrite/destino público/corrupção. Recuperação em produção não foi ensaiada.

## Riscos residuais e decisões pendentes

| ID / gravidade | Evidência / impacto | Estado e encaminhamento |
|---|---|---|
| R01 — P2 | Duração SQLite com `julianday` representa horas de relógio local; `service_pocket_value()` usa timestamps PHP e pode divergir ao atravessar horário de verão. Finanças incluem planeados não cancelados; Pocket inclui terminados há 15 min mesmo sem DONE. Taxa do utente é a atual, não uma taxa histórica por serviço. | **Preservado e caracterizado por testes.** Alterar fórmulas, períodos, arredondamentos ou política de taxas exige aprovação explícita. |
| R02 — P2 | Uma cuidadora pode criar relatórios sobre utentes com qualquer serviço histórico não cancelado. | **Política existente mantida.** Limitar por data/serviço atual exige decisão de negócio. |
| R03 — P2 | Sessão sem prazo absoluto/idle explícito no código; depende da configuração PHP. Caminho de login de conta inexistente não calcula password_verify, podendo diferir em tempo. | **Residual.** Rate limiting/genérico mantidos. Definir duração de sessão, revogação após password e eventual uniformização temporal numa intervenção própria; não alegada resistência a análise temporal. |
| R04 — P2 | `users.carer_id` e `carers.user_id` não têm FKs recíprocas; `save_user` não sincroniza o espelho `carers.user_id`. Fluxos ativos leem `users.carer_id`, mas bases antigas podem ter inconsistências. | **Documentado, sem reparar dados automaticamente.** Validar relações numa cópia e aprovar eventual migração incremental; não se recriaram tabelas. |
| R05 — P2 | Não verificados PHP 8.1 real, Apache/cPanel, proxy HTTPS, permissões Unix, backups operacionais e configurações do servidor. | **Gate de publicação pendente**, descrito no checklist. Testado PHP 8.5.8/Windows e Edge; sintaxe usa `never` (PHP 8.1+), sem certificação executada de todas as versões. |
| R06 — P2 | Relatórios/CRUD não têm chaves de idempotência gerais. Dois POSTs válidos independentes podem criar duas entradas. Paginação/retensão de relatórios, auditoria e lotes não existe. | **Residual, sem nova funcionalidade.** PRG e atomicidade reduzem retries após falha; idempotência genérica/retensão requer desenho e aprovação, sem limpeza de histórico nesta intervenção. |
| R07 — P3 | Pedidos diretos de leitura/arquivo podem atualizar marcadores de um relatório já arquivado; GET não modifica marcadores. | **Comportamento existente preservado.** Definir política de imutabilidade após arquivo antes de mudar o ciclo de vida. |
| R08 — P3 | N+1 do Pocket nas cuidadoras, contagens repetidas e consultas de esquema por pedido; dados extensos ainda carregados nas listagens. | **Sem otimização especulativa.** Medir numa cópia representativa antes de paginação, novos índices ou cache. |

Nenhum destes pontos autoriza alterações a dados reais. As verificações automatizadas usam bases fictícias temporárias ou `.runtime/fixture-technical.sqlite`. Os documentos complementares discriminam testes, limitações, permissões, ficheiros e publicação.
