# Correções implementadas

Referência: `AUDITORIA-TECNICA-FINAL.md`. Alterações confinadas à branch técnica; sem merge/deploy.

| Ficheiro | Alteração | Testes associados |
|---|---|---|
| `upload-ready/index.php` | Validação HTTP; wrapper de rollback; transações compostas; validação ADMIN dentro da transação; redirects de cuidadora antes de begin; ID de auditoria do utente; atualização de estados por proprietário/estado; extração da API. | Casos HTTP novos e suite anterior de CRUD, permissões, CSRF, recorrência, rollback e relatórios. |
| `upload-ready/lib/security.php` | Expiração inclusiva de rate limit, validação estrutural/IDs/valores finitos/datas; rejeição com rollback; evita escrita redundante da versão do esquema. | Boundary determinístico, arrays/IDs/NUL/ações desconhecidas, datas impossíveis, infinito, contenção de migração. |
| `upload-ready/bootstrap.php` | Seed não adquire lock de escrita quando já existem contas; dupla verificação mantida na primeira instalação. | Contenção com segunda ligação, seed inicial e preservação de hashes/esquema legado. |
| `upload-ready/lib/events.php` | Novo módulo com a rota existente; SELECT e listas de propriedades explícitas por perfil; rejeita normalização de datas impossíveis. | Contrato JSON, propriedades necessárias à UI, intervalo inválido e calendário CARER em navegador. |
| `upload-ready/views/reports.php` | Só o bloco PHP de consultas: remove duas consultas descartadas no perfil CARER e reduz campos dos dropdowns a ID/nome. | Permissões/relatórios HTTP; capturas antes/depois e navegação dos três perfis. |
| `tests/technical.php` | Regressões de rate limit, integridade, plano de consulta e dois cenários de contenção SQLite. | Executado por `tests/run.php`. |
| `tests/technical-http.php` | Pedidos malformados, contrato API, auditoria, falhas intermédias, estados repetidos, último administrador, permissões, datas e valores não finitos. | Executado por `tests/http.php`. |
| `tests/run.php`, `tests/http.php` | Integração dos novos testes sem substituir os anteriores. | Suite completa. |
| `tests/technical-visual.cjs` | Comparação dos três perfis em três dimensões, tempo fixo e IPMA offline controlado. | 57 vistas por versão e login. |
| `tests/technical-navigation.cjs` | Reload, navegação móvel, hiperligações internas e modais, sem submeter formulários de negócio. | 88 URLs verificadas e 60 aberturas/fechos de modais. |
| Cinco documentos da auditoria | Inventário, correções, matriz, testes e publicação/reversão. | Revisão de consistência com diff e resultados. |

## Preservação

Não há alterações em CSS, JavaScript de produção, imagens, HTML dos componentes, configurações privadas, esquema SQLite, fórmulas de Pocket/Finanças ou código Laravel. Não houve leitura/gravação da base real. Os testes de falha usam triggers exclusivamente nas bases fictícias e removem-nos no respetivo `finally`.

Os estados permitidos já eram os dos botões atuais: concluir apenas SCHEDULED, desfazer apenas DONE. Agora o servidor aplica essa mesma transição de forma atómica e ignora replay sem reescrever a data. Cancelamento operacional mantém o comportamento anterior.

Os novos erros 400 só afetam pedidos malformados. Mensagens novas para dados de perfil/datas inválidos são validação técnica, sem alterar os textos do percurso normal.

## Publicação incremental

O ZIP incremental contém apenas os cinco ficheiros PHP de produção, nos caminhos relativos à raiz pública da aplicação. Os documentos seguem em `documentacao/`. Não contém base de dados, `config.php`, `config-local.php`, ferramentas, fixtures ou assets. **É uma atualização da versão auditada, não uma instalação completa.** O novo `lib/events.php` é obrigatório e deve acompanhar `index.php`.
