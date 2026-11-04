# Relatório de testes — auditoria técnica Care4All

Data: 03/11/2026. Base inicial: `c2fce01`. Ambiente: Windows, PHP CLI **8.5.8**, Node **24.19.0**, Playwright com Microsoft Edge headless, HTTP em loopback. Nenhum teste apontou à produção.

## Resultados

| Verificação | Resultado |
|---|---|
| Suite inicial sem alterações | **35 passaram, 0 falharam** |
| Primeiras regressões, antes das correções | **36 passaram, 6 falharam**, reproduzindo rate limit, input malformado, campos API, ID de auditoria, relatório parcial e auditoria de cancelamento falso |
| Seed com outra ligação a manter BEGIN IMMEDIATE | Falha reproduzida: `database is locked`; passa após retirar lock desnecessário |
| Migração já aplicada sob o mesmo bloqueio | Falha reproduzida: `database is locked`; passa após evitar INSERT do marcador existente |
| Datas impossíveis na API e utente | Duas falhas reproduzidas (200 em vez de 400; data inválida persistida); corrigidas |
| Suite final PHP/HTTP/backup | **52 passaram, 0 falharam** |
| Lint PHP | **29 ficheiros**, sem erro de sintaxe |
| `git diff --check` | Sem erros de whitespace |
| `tests/browser-qa.cjs` | **16 verificações aprovadas** |
| `tests/daily-companion.cjs` | Frases, relógio Lisboa em dispositivo com outro fuso, sol/lua, saudação, previsão, dados antigos/offline, Escape e skip-link aprovados |
| `tests/technical-navigation.cjs` | **57 vistas**, **88 URLs internas**, **60 aberturas/fechos de modais**, sem erros JS/HTTP inesperados |
| `tests/technical-visual.cjs` | **57 vistas por versão + login**, três perfis, 1440/768/390 px; sem overflow de página ou recursos locais em falta |
| Backup SQLite | WAL confirmado incluído, integridade válida; overwrite, origem inexistente, destino público e corrupção rejeitados |

### Conteúdo da suite final

Preservação do esquema legado e passwords; primeira instalação; escaping/CSRF; relatórios por utente autorizado; conflitos entre concelhos e adjacência; fórmulas e inclusão Pocket/Finanças; DST; recorrência/limites/replay/rollback; triggers de histórico; rate limit no limite temporal; integridade e índice; contenção de seed/migração; arrays/IDs/ações/NUL inválidos; allowlist de eventos e filtro sem alargamento CARER; IDs de auditoria; falhas intermédias; repetição de conclusão; último ADMIN; permissões; valores não finitos/datas impossíveis; CRUD de cuidadora e conta associada; sessões/cookies; logout; cinco falhas de login; backup CLI.

As contagens são de casos da suite, não uma alegação de cobertura exaustiva de todos os inputs. Alguns casos incluem várias asserções/pedidos.

### Comparação visual

Código original extraído com `git archive c2fce01 upload-ready` para diretório privado de testes; original e branch atual foram servidos em portas locais distintas e comparados sequencialmente sobre a mesma fixture. Relógio fixado no navegador e IPMA simulado como indisponível; imagens e fontes aguardadas antes da captura.

58 PNG por versão: **53 idênticos byte a byte**. Nos cinco restantes, comparação perceptual `pixelmatch` com limiar 0,1 deu zero pixels diferentes em quatro; no ecrã EDITOR/cuidadoras sobraram 16 pixels em 1.801.440, localizados num pequeno detalhe de hover. As diferenças brutas restantes eram pequenas variações de rasterização da imagem. Não se alteraram CSS, assets, HTML ou dimensões dos componentes. Inspeção visual e diff confirmam preservação; não se afirma igualdade binária de todos os PNG.

Uma execução intermédia da comparação, em paralelo com a navegação sobre a mesma SQLite, terminou num erro PDO no login do código original e timeout consequente. Os ensaios finais foram sequenciais. Não se ocultou esse resultado nem se usou retry para o transformar em aprovação. O caso de contenção foi reproduzido separadamente na suite PHP; os testes não constituem ensaio de carga do cPanel.

## Evidência local

Resultados em `output/technical-audit/`: `baseline.txt`, `reproduction.txt`, `seed-reproduction.txt`, `migration-reproduction.txt`, `dates-reproduction.txt`, `final-php.txt`, `navigation.json`, `visual/before/`, `visual/after/` e `visual/comparison.json`. Este diretório é ignorado pelo Git e não integra o pacote público. As falhas contêm somente dados fictícios; snapshots não são recolhidos da produção.

## Como repetir

1. Usar checkout isolado, sem `upload-ready/config-local.php`. Executar `php tests/run.php`; o runner cria uma pasta imprevisível na temporária, define `CARE4ALL_DB_FILE`, cria dados fictícios e limpa apenas os ficheiros que criou. Recusa configuração local para não atingir uma base real.
2. Para navegador, definir `CARE4ALL_TEST_PASSWORD` de teste (12+ caracteres), `CARE4ALL_DB_FILE` para um **novo** `.runtime/fixture-NOME.sqlite` e executar `php tests/browser-fixture.php`. Não reutilizar uma fixture já modificada para o teste que cria a mesma recorrência.
3. Com a mesma variável DB_FILE, iniciar `php -S 127.0.0.1:8031 -t upload-ready` e definir `CARE4ALL_TEST_URL=http://127.0.0.1:8031` no runner. Definir `CARE4ALL_PLAYWRIGHT` para o módulo instalado, se não estiver no NODE_PATH.
4. Executar `node tests/technical-navigation.cjs`, `node tests/technical-visual.cjs`, `node tests/browser-qa.cjs` e `node tests/daily-companion.cjs` **sequencialmente**. A comparação antes/depois deve ocorrer antes de testes mutáveis ou usar cópias equivalentes. `AUDIT_PHASE=before`/`after` escolhe o diretório de capturas.
5. O teste existente `daily-companion.cjs` usa datas de 03/11/2026; noutra data exige adaptação da fixture/relógio do teste para corresponder à previsão e efemérides do dia. Isto não exige mudar código de produção.
6. Lint de todos os `.php` de `upload-ready/` e `tests/` com `php -l`; `git diff --check`.

Não executar suites mutáveis na instalação pública, mesmo que só se pretenda verificar rapidamente o login.

## Limitações e gates pendentes

- Não executado PHP 8.1, Linux/Apache ou cPanel real. A ausência de erro em PHP 8.5.8 não certifica a versão instalada no alojamento.
- `.htaccess`, Secure sob HTTPS/proxy, permissões Unix, OPcache e recuperação operacional de backup precisam do checklist de publicação.
- Navegador Edge/Chromium headless; não foram executados Safari, Firefox ou dispositivos físicos. Viewports não equivalem a testes em hardware real.
- Não houve teste de carga, pentest externo, scanner de dependências completo ou exploração de todas as corridas multiprocesso. Proteção do último ADMIN foi inspecionada transacionalmente e testada sequencialmente.
- O backup foi validado e aberto em bases fictícias; não se restaurou uma base real.
- Testes financeiros preservam o comportamento atual, inclusive diferenças de DST e regras de inclusão. Não validam uma regra financeira nova.
- Os serviços externos foram consultados uma vez e simulados nos testes determinísticos; disponibilidade futura não garantida.

Para riscos que dependem de política de negócio, consultar `AUDITORIA-TECNICA-FINAL.md`. Não foram corrigidos silenciosamente.
