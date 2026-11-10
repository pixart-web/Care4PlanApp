# Auditoria Care4All V2

Data: 30/10/2026. Alvo: `upload-ready/`, PHP standalone com SQLite.
As pastas Laravel foram excluídas do upgrade. Nenhuma base real foi usada nos testes.

## Revisão de segurança de 02/11/2026

Foi feita uma nova revisão estática e dinâmica da aplicação atual, novamente sem
usar uma base real. Foram corrigidos os seguintes pontos adicionais:

- headers endurecidos com CSP restritiva, bloqueio de framing, Permissions-Policy,
  COOP, no-store e HSTS quando o pedido chega diretamente por HTTPS;
- remoção dos handlers JavaScript inline para permitir `script-src 'self'`;
- sessões autenticadas com 30 minutos de inatividade, máximo absoluto de 8 horas
  e rotação periódica do identificador;
- login com limites explícitos de entrada, custo equivalente para utilizadores
  inexistentes, rehash transparente, limpeza do contador da conta após sucesso e
  auditoria de logout;
- pedidos com arrays inesperados, ações desconhecidas ou corpo superior a 3 MB
  são recusados antes de chegar à lógica de negócio;
- requisito mínimo elevado para PHP 8.3 e Axios histórico elevado para 1.20.0,
  devido ao fim de suporte de PHP 8.1 e vulnerabilidades publicadas nas versões
  antigas de Axios.

Validação final desta revisão: lint de todos os PHP, sintaxe de todos os JavaScript
próprios, 67 testes de regressão e 16 verificações no Edge headless, incluindo os
novos headers, pedidos malformados, limpeza do rate limit após login e ausência de
erros CSP/JavaScript. Runtime local: PHP 8.5.12 oficial, com arquivo validado por
SHA-256.

## Inventário Preservado

| Fluxo | Implementação |
| --- | --- |
| Login, logout, ADMIN/EDITOR/CARER | index.php, bootstrap.php, lib/security.php |
| Utentes e ficha clínica completa | views/patients.php, save_patient |
| Cuidadoras, cor, conta e ajuste Pocket | views/carers.php, save_carer |
| Utilizadores | views/users.php, save_user |
| Escalas, cancelamento, conclusão e desfazer | views/scales.php, lib/scheduling.php |
| Pocket e finanças | bootstrap.php, views/pocket.php, views/finances.php |
| Relatórios, respostas e notificações | views/reports.php, dashboards, handlers |
| Entradas e saídas mensais | entity_movements e dashboard |

## Problemas E Correções

| Prioridade | Evidência anterior | Correção |
| --- | --- | --- |
| P0 | seed() em bootstrap.php executava UPDATE de passwords e eliminava o utilizador admin em pedidos posteriores | Seed transacional apenas se users estiver vazia; nenhum UPDATE/DELETE de contas existentes |
| P1 | config.php aceitava password inicial previsível | Sem password por defeito; instalação nova exige configuração privada com 12+ caracteres; contas existentes permanecem válidas |
| P1 | Login sem regeneração de sessão nem limitação | session.use_strict_mode, ID regenerado, cookies HttpOnly/SameSite/Secure em HTTPS, logout completo; limitação por conta/IP e por IP |
| P1 | save_report aceitava qualquer patient_id da cuidadora | Autorização no servidor baseada em serviços não cancelados; seletor limitado aos utentes associados |
| P1 | Eliminação de fichas/utilizadores podia apagar histórico por cascata | Verificações de dependências e triggers de proteção; escalas com histórico são canceladas, não eliminadas; relatórios arquivados logicamente |
| P1 | Operações compostas de fichas e contas sem atomicidade | Transações em save_carer, save_patient, save_user, respostas; rollback testado com falha injetada |
| P1 | Verificação de sobreposição separada da inserção | BEGIN IMMEDIATE na gravação de escalas, nova verificação dentro da transação e chave de idempotência persistente |
| P1 | Erros PDO podiam expor SQL, caminhos e dados | Mensagem genérica com referência; logs apenas referência e classe de erro |
| P1 | CSRF com tokens vazios e arrays | Exigência de token de sessão não vazio, tipo string e comparação hash_equals |
| P2 | Migração atribuía Portimão arbitrariamente a serviços antigos | Removido o backfill automático; valores atuais mantidos, incluindo NULL; filtro Sem concelho |
| P2 | sync_carer_usernames reescrevia contas em cada pedido | Removida a chamada na inicialização; atualização de nomes apenas no fluxo explícito |
| P2 | GET de relatórios marcava respostas como lidas | Marcação por POST com CSRF ao abrir histórico ou usar o botão explícito |
| P2 | Cuidadora podia concluir uma escala cancelada | A verificação de propriedade exclui CANCELLED |
| P2 | Calendarização sem validação de data/hora legal | Parser Europe/Lisbon rejeita horas inexistentes ou ambíguas; recorrência mantém o horário local por ocorrência |
| P2 | Calendário longo e pouco operacional | Dia com 8 cuidadoras por página, horas verticais, conflitos em faixas; semana, mês e lista; filtros no servidor e lista mobile |
| P2 | FullCalendar CSS e tradução apontavam para URLs 404 | Bundle e locale oficial 6.1.11 incluídos localmente, com licença |
| P2 | Modais sem controlo de foco e navegação mobile incompleta | Foco contido/restaurado, Escape, fundo inert, menu completo e estados de foco |
| P2 | Proteção de ficheiros dependia de regra incompleta | Regras Apache para config, dados, auxiliares SQLite, backups, logs e views/lib; índices de diretório desativados |
| P3 | Configurações era uma página de placeholder | Painel real de configuração da aplicação, sem revelar caminhos privados |

## Regras Financeiras Mantidas

- Pocket: serviços cujo início pertence ao mês atual, não cancelados, com fim até agora menos 15 minutos; inclui SCHEDULED e DONE. Soma o ajuste manual da cuidadora.
- Pocket agregado: duração pelo SQLite `julianday`. O detalhe de cada serviço usa diferença de timestamps PHP. Esta diferença anterior pode produzir divergências num serviço que atravesse uma mudança de hora. Não foi alterada silenciosamente.
- Finanças: receita planeada = tarifa atual do utente vezes horas; custo planeado = tarifa da escala vezes horas. Inclui serviços futuros não cancelados com início no mês.
- Margem = receita menos custo. Percentagem = margem/receita; custo sem receita produz -100%; ambos zero produzem 0%.
- As vistas mensais mudam de intervalo no dia 1; não apagam valores. O ajuste manual não é automaticamente reinicializado. Alterar a tarifa do utente continua a afetar a projeção mensal, como anteriormente.

## Alterações De Esquema

Migrações incrementais e idempotentes: coluna nullable `reports.deleted_at`; tabelas `schema_migrations`, `login_attempts`, `audit_events`, `service_batches`; índices temporais e por cuidadora/concelho/relatórios; triggers de proteção de histórico. Nenhuma tabela original é apagada ou recriada. Não há alteração de hashes nem normalização automática de concelhos.

## Validação Executada

- Lint de todos os PHP de upload-ready e sintaxe dos JavaScript próprios.
- `tests/run.php`: 67 testes passaram, 0 falhas, na execução final integrada. Inclui alteração real de password pelo handler HTTP, proteção de cascatas, pedidos malformados, headers de segurança, rate limit, perfis, tarifas e planeamento mensal.
- Inclui esquema legado fictício, integridade e chaves estrangeiras, seed/hashes, login/logout, cookies, CSRF, perfis, relatórios, CRUD com falhas injetadas, Pocket/finanças, recorrência, DST, conflitos e replay, filtros e backup consistente em WAL.
- `tests/browser-qa.cjs`: 16 verificações passaram no Edge headless: login, dia 24h, semana, mês, pré-visualização, criação de cinco serviços, invalidação da confirmação, duplicação, Escape, desktop/tablet/mobile e calendário da cuidadora com locale local.
- Capturas inspecionadas em `output/playwright/`; dados exclusivamente fictícios. Não entram no pacote de produção nem no Git.
- Browser e PHP CLI locais não substituem testes de Apache, TLS ou da versão PHP exata do alojamento. O runtime desta revisão foi PHP 8.5.12.

## Riscos Residuais E Limites

1. Apache/cPanel, certificado HTTPS, proxies e permissões reais têm de ser verificados no alojamento. O servidor PHP de desenvolvimento ignora .htaccess. Não foi feito deploy.
2. Sem MFA nem recuperação autónoma por email. A limitação de login não é um sistema distribuído contra ataques volumétricos.
3. A auditoria guarda identificadores, ação e hora; não é um registo inviolável nem um arquivo clínico regulamentar. Não guarda texto de relatórios nem passwords.
4. Novas horas ambíguas/inexistentes são rejeitadas; não há seletor para o primeiro/segundo instante da hora repetida. Serviços legados são preservados.
5. A restrição UNIQUE(carer_id, starts_at) original abrange também cancelados. Uma nova escala no mesmo instante de início exige editar a ocorrência existente; a pré-visualização identifica o duplicado cancelado.
6. Lotes têm máximo de 120 ocorrências; não há edição em massa de séries. Edita-se uma ocorrência por defeito. A chave impede repetição do mesmo pedido, e conflitos impedem duplicação com nova chave.
7. A autorização de relatório permite um utente com qualquer serviço não cancelado da cuidadora, incluindo histórico; não limita por dia. A empresa deve confirmar a política de retenção/acesso pretendida.
8. Preservar histórico impede apagar fichas com dependências. Pode usar-se o estado Terminado do utente. Relatórios arquivados continuam na base para recuperação administrativa, sem interface de recuperação nesta versão.
9. Não foram feitos ensaios de carga concorrente com 60 sessões nem avaliação jurídica/RGPD. As regras de retenção, consentimentos e backups externos pertencem à operação da empresa.
10. Credenciais antigas potencialmente divulgadas no histórico anterior devem ser alteradas explicitamente pelos administradores. Este upgrade não as redefine.

## Ficheiros Principais

Segurança e integração: config.php, bootstrap.php, index.php, lib/security.php e .htaccess.
Escalas: lib/scheduling.php, views/scales.php, assets/scales-v2.js.
Interface: assets/app.css, assets/app.js, views/layout.php, views/login.php, dashboards e configurações.
Relatórios: views/reports.php e handlers. Operação: maintenance/backup.php, README-UPLOAD.md, DEPLOY-CARE4ALL-V2.md. Regressão: tests/.
