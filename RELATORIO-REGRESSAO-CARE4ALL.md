# Relatório de regressão Care4All

Base 94f3832. PHP CLI 8.5, PDO SQLite, Edge com Playwright em Windows. O runner gera um diretório temporário novo, define explicitamente DB_FILE, confirma o caminho e recusa config-local.php. Browser em 127.0.0.1:8048, criado por tests/browser-fixture.php com nome novo autorizado dentro de .runtime. O servidor de ensaio usou exclusivamente dados sintéticos.

|Execução|Resultado|
|---|---|
|tests/run.php antes das alterações|69 aprovados, 0 falhas|
|tests/run.php final|74 aprovados, 0 falhas|
|Lint PHP da aplicação e testes|0 erros|
|tests/planning-browser.cjs / tests/browser-qa.cjs|9 + 16 verificações aprovadas|
|tests/technical-navigation.cjs|Três perfis, 57 combinações página/largura, 116 links internos e 66 aberturas/fechos de modais|
|tests/technical-visual.cjs|57 combinações antes e depois; sem erros JS ou de recursos locais|
|tests/visual-audit.cjs|39 combinações aprovadas|
|tests/daily-companion.cjs|Falha na previsão IPMA: timeout à espera da temperatura simulada. A política connect-src self impede a origem existente. Correção da permissão externa pendente.|

As 58 capturas incluem login. A comparação de píxeis encontrou 49 idênticas e nove com diferenças, todas com as mesmas dimensões. Sem diferenças nas vistas, recursos CSS ou JS entre as versões. Não se atribuiu a variação de píxeis a uma causa específica nem se certifica equivalência pixel a pixel dessas nove capturas.

Cobertura herdada: sessões, cookies, CSRF, limites de tentativas, ADMIN/EDITOR/CARER, escrita de clientes e cuidadoras com rollback, fotografias e permissões, permissões de relatórios, respostas, conclusão e anulação, cancelamento, séries, conflitos entre concelhos, cópia mensal, feriados, mudança de hora, Pocket e Finanças. Os testes de lotes cobrem repetição de submissão e erro parcial. Os testes de SQLite cobrem esquema legado, seed, integridade, índices e bloqueio por outro escritor.

Cobertura ampliada: confirmação partilhada deixa de servir uma nova chave; conflito surgido depois da pré-visualização recusa o lote e preserva as linhas; sexta/sábado a 10,02 por hora gera duas parcelas de 2,51; falha da auditoria de login deixa a sessão anónima; extrato apenas para ADMIN e planilhas de dia/semana/mês acessíveis a EDITOR; restauro de cópia com verificação de relações e comparação com o original sintético.

Não foram executados testes em Apache/cPanel real, nem uma matriz de versões PHP, nem um teste de carga sob tráfego de produção. O teste da alteração entre pré-visualização e gravação simula deterministicamente a ordem das escritas, não é um benchmark de pedidos paralelos. O ensaio de restauro verifica SQLite em diretório temporário. Os resultados não substituem um ensaio com a configuração efetiva do alojamento.
