# Matriz de rotas e permissões

Rotas públicas da aplicação standalone: `index.php?page=...`; ações POST: campo `action`. Inspeção do router, `validate_action`, handlers e views, complementada por testes HTTP e de navegador.

## GET

| Página | Anónimo | ADMIN | EDITOR | CARER |
|---|---|---|---|---|
| home | → login | → dashboard | → dashboard | → dashboard |
| login | 200 | 200 | 200 | 200 |
| dashboard | → login | Operacional | Operacional | Próprio |
| scales | → login | Todas / filtros | Todas / filtros | Próprias |
| carers | → login | 200 | 200 | 403 |
| patients | → login | 200 | 200 | 403 |
| users | → login | 200 | 403 | 403 |
| finances | → login | 200 | 403 | 403 |
| reports | → login | Não arquivados | Não arquivados | Associados à cuidadora, não arquivados |
| settings | → login | 200, informativo | 200, informativo | 403 |
| pocket | → login | 403 | 403 | Próprio |
| profile | → login | 403 | 403 | Próprio |
| api.events | → login | Intervalo/filtros | Intervalo/filtros | Apenas própria cuidadora; filtro alheio não amplia acesso |
| rota desconhecida | → login | 404 | 404 | 404 |
| maintenance/backup.php | 404 | 404 | 404 | 404 |

Pedidos estruturalmente inválidos são rejeitados com 400 antes do router. Intervalo inválido da API retorna 400 JSON. O endpoint tem limite de intervalo de 366 dias. Os dados de eventos da cuidadora são: `id`, `title`, `start`, `end`, cores e `extendedProps` com `address`, `municipality`, `status`, `starts_at`, `ends_at`, `observations`, `has_observations`, `pocket_value`.

ADMIN/EDITOR recebem adicionalmente, nas propriedades, IDs do serviço/utente/cuidadora, nomes, cor e taxa horária. Nenhum perfil recebe timestamps de criação/atualização nem ID de quem concluiu por este endpoint. As páginas de calendário existentes continuam com o mesmo comportamento.

## POST

Todos os POSTs passam por CSRF (incluindo login/logout). Token ausente, incorreto ou array: 419. Pedidos malformados/ações desconhecidas: 400. Nas ações autenticadas, uma sessão ausente redireciona para login após a validação CSRF. Uma autorização negada retorna 403; rejeições funcionais existentes usam flash + 302.

| Ação | ADMIN | EDITOR | CARER | Condição / efeito |
|---|---|---|---|---|
| login | Sim | Sim | Sim | Também anónimo; credenciais válidas, limites por conta/IP; sessão regenerada |
| logout | Sim | Sim | Sim | Destrói sessão; pode ser usado sem login com CSRF válido |
| save_carer | Sim | Sim | Não | Criação/edição e conta ligada, password válida se nova/alterada, nome/cor/email sem conflito |
| delete_carer | Sim | Sim | Não | Sem serviços/relatórios e sem histórico da conta que impeça DELETE; transação |
| save_patient | Sim | Sim | Não | Campos válidos, gravação e movimento/auditoria atómicos |
| delete_patient | Sim | Sim | Não | Sem serviços/relatórios; trigger protege histórico |
| preview_service | Sim | Sim | Não | Validação de horários, refs, conflitos e recorrência; JSON; não grava serviços |
| save_service | Sim | Sim | Não | Preview atual para recorrência; conflitos, limites e idempotência existentes |
| cancel_service | Sim | Sim | Não | Cancela mantendo serviço; transação com auditoria |
| delete_service | Rejeitado | Rejeitado | Não | História preservada: pede cancelamento |
| done_service | Não | Não | Próprio | SCHEDULED → DONE; cancelado/alheio 403; repetição DONE sem regravar |
| undo_service | Não | Não | Próprio | DONE → SCHEDULED; cancelado/alheio 403; repetição SCHEDULED sem regravar |
| save_profile | Não | Não | Próprio | Idade/telefone/email, sem escolher ID de outra cuidadora |
| save_report | Sim | Sim | Restrito | Operacional escolhe cuidadora existente; CARER escolhe utente com serviço histórico não cancelado da própria cuidadora |
| mark_report_read | Sim | Sim | Restrito | Operacional marca relatórios de CARER; CARER marca relatório/respostas da própria cuidadora |
| reply_report | Sim | Sim | Restrito | Relatório existente não arquivado; CARER tem de estar associado; operacional exige cuidadora associada |
| delete_report | Sim | Sim | Só remetente | Arquivo lógico; CARER usa sender_user_id da própria conta; tentativa alheia é no-op sem auditoria de sucesso |
| save_user | Sim | Não | Não | Role permitido; username único; associação CARER válida e única; mantém pelo menos um ADMIN |
| delete_user | Sim | Não | Não | Não permite apagar a própria conta; histórico de relatórios/respostas impede eliminação |

## Navegação e limites de cobertura

Desktop 1440 px, tablet 768 px, mobile 390 px nos três perfis. Suite adicional percorreu também 320 px no calendário. Menus, links internos, reload, filtros/calendário, editor de serviço e modais foram verificados. Ações mutáveis foram exercidas em bases fictícias via HTTP; não se submeteram todas as combinações de campos de todos os formulários pelo navegador.

88 URLs internas verificadas por perfil (39 ADMIN, 37 EDITOR, 12 CARER) e 60 aberturas/fechos de modais (33/24/3). URLs iguais em perfis diferentes contam separadamente. A página externa [IPMA](https://www.ipma.pt/pt/otempo/prev.localidade.hora/#Faro&Portim%C3%A3o) e o [endpoint de previsão](https://api.ipma.pt/open-data/forecast/meteorology/cities/daily/1081100.json) responderam à consulta externa durante a auditoria; isso não garante disponibilidade contínua nem valida o serviço a partir da rede do alojamento. A previsão foi simulada nos testes determinísticos.

Proteção de ficheiros privados depende de Apache/AllowOverride. Não interpretar um teste do servidor PHP local como prova de bloqueio por `.htaccess`.
