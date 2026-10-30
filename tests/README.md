# Regressao Care4All standalone

Executar na raiz do repositorio, sem Composer:

```powershell
tools/php/php.exe tests/run.php
```

O runner recusa `upload-ready/config-local.php`, substitui `CARE4ALL_DB_FILE`
por um SQLite novo numa pasta temporaria privada e gera uma password aleatoria
no ambiente. Opcionalmente defina `CARE4ALL_TEST_PASSWORD` (minimo 12 caracteres).
Nao recebe caminhos de bases existentes. Nunca execute contra dados reais.

Requer PHP CLI com `pdo_sqlite`, `sqlite3`, `proc_open`, streams HTTP e ligacoes
loopback autorizadas. No sandbox Windows desta maquina foi necessario executar
fora do sandbox para ligar ao servidor local. O servidor usa uma porta aleatoria,
apenas `127.0.0.1`, e termina em `finally`. Caminhos de subprocessos sao relativos
para evitar conversao incorreta de caracteres no Windows. Nao usar PHP -S em
producao: ele nao aplica `.htaccess`.

Cobertura: seed/hashes, esquema legado ficticio, migracoes repetidas, integridade,
autenticacao HTTP, cookies, logout, CSRF, papeis, relatorios e ownership, handlers
save_patient/save_carer com falhas injetadas, regras Pocket/financas, recorrencia,
DST, conflitos, replay, preview obrigatorio, filtros e backup WAL com escrita nao
confirmada. Os testes de rollback criam triggers apenas na fixture.

Para QA visual persistente:

```powershell
New-Item -ItemType Directory -Force .runtime
$env:CARE4ALL_DB_FILE = Join-Path (Get-Location) '.runtime/fixture-browser.sqlite'
$env:CARE4ALL_TEST_PASSWORD = [Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(24))
tools/php/php.exe tests/browser-fixture.php
$env:CARE4ALL_INITIAL_ADMIN_PASSWORD = $env:CARE4ALL_TEST_PASSWORD
Set-Content -NoNewline -Path .runtime/browser-password.txt -Value $env:CARE4ALL_TEST_PASSWORD
tools/php/php.exe -S 127.0.0.1:8017 -t upload-ready
```

O nome do ficheiro tem de ser novo e corresponder a `.runtime/fixture-*.sqlite`.
Contas ficticias: `Andreia`, `fixture-editor`, `fixture-carer-1`; password apenas
no ambiente. Pare o servidor apos QA. Nao publique `.runtime`, `tests` nem dados
de fixture. Confirme que a porta esta livre ou escolha outra.

Noutro terminal, com Playwright instalado ou o caminho do pacote fornecido em
`CARE4ALL_PLAYWRIGHT`, executar `node tests/browser-qa.cjs`. Pode definir
`CARE4ALL_TEST_URL` para outra porta loopback. O script usa Edge headless,
exclusivamente a fixture local, grava capturas em output/playwright/ e testa
login, calendário, pré-visualização, gravação, duplicação, Escape e mobile.
Use uma fixture nova a cada execução; o teste cria cinco escalas fictícias.

Limites: nao valida Apache/cPanel, HTTPS/proxy ou concorrencia de processos de escrita em carga.
Os ficheiros de FullCalendar são agora locais, sem CDN no runtime.
O teste WAL cobre consistencia do backup, nao simula uma restauracao de conta
cPanel. A limpeza de ficheiros temporarios e best-effort (Windows pode manter
handles abertos); qualquer resido contem apenas dados sinteticos.
