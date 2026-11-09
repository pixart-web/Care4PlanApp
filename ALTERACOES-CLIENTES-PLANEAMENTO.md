# Care4All — Clientes, planeamento e perfis

Atualização de 4 de novembro de 2026. Aplicação PHP em `upload-ready/`.

## Funcionalidades

- Terminologia visível uniformizada para cliente/clientes. Os identificadores internos e tabelas existentes mantêm-se compatíveis.
- Duplicação de um mês para o seguinte, em todos os concelhos, mediante pré-visualização. Mantém a primeira/segunda/etc. ocorrência de cada dia da semana, horários, intervenientes e tarifas. Os serviços cancelados são excluídos; as cópias ficam agendadas. Uma quinta ocorrência inexistente no destino é indicada e exige consentimento para copiar apenas as correspondências. Não se inventam serviços para uma quinta ocorrência adicional no destino. Conflitos impedem a cópia inteira.
- Tarifas de dias úteis e de fins de semana/feriados nacionais portugueses nos perfis. O agendamento permite confirmar as tarifas da cuidadora. Novos serviços guardam as tarifas aplicadas; alterações posteriores ao perfil não recalculam esses serviços. Os serviços antigos mantêm o comportamento anterior até serem explicitamente editados. Períodos que atravessam a meia-noite dividem o cálculo por dia.
- Calendário com clientes sem serviços, opção de linhas por cuidadora e todos os concelhos. Clientes antigos sem concelho continuam visíveis em todos os concelhos/sem concelho; não se adivinha a sua localização.
- Fotografias opcionais JPEG, PNG e WebP até 2 MB e 6000 × 6000 píxeis. A cuidadora pode atualizar a própria fotografia; administradores e editores veem as fotografias dos perfis. Uma cuidadora não pode consultar fotografias de outros perfis.
- Agendamento a partir de Clientes → Agendar serviço, com cliente pré-selecionado e até três horários no mesmo dia. A repetição aplica-se aos horários escolhidos.
- Bloqueio de sobreposição de horários da mesma cuidadora em qualquer concelho; permite locais diferentes no mesmo dia quando não há sobreposição.
- Cor da cuidadora editável manualmente, com rejeição de cores exatamente iguais já atribuídas.
- Data de nascimento obrigatória ao criar/guardar o perfil da cuidadora. Os perfis antigos não recebem datas inventadas. Avisos dos aniversários do dia no painel geral para clientes e cuidadoras com data preenchida.
- Edição conjunta dos horários dos serviços agendados da mesma série e no mesmo horário original. Os outros horários do dia mantêm-se. Um conflito impede a edição conjunta inteira. Séries antigas são recuperadas quando existe registo do lote; serviços sem associação identificável não são ligados por aproximação.
- Estrutura visual, cores, imagens e folhas de estilo principais preservadas. Ajustes localizados aos campos novos, fotografias e calendário da cuidadora para evitar sobreposições.

Feriados: lista nacional do artigo 234.º do Código do Trabalho, incluindo Sexta-feira Santa, Páscoa e Corpo de Deus. Exclui Carnaval e feriados municipais. Fonte: https://diariodarepublica.pt/dr/legislacao-consolidada/lei/2009-34546475-46746675

## Dados e publicação no servidor

Não foi efetuada qualquer publicação nem acesso à base de dados de produção.

Esta versão acrescenta colunas opcionais às tabelas carers, patients e services, a tabela profile_photos, um índice de séries e gatilhos de limpeza de fotografias ao eliminar o respetivo perfil. A migração é automática e repetível, identificada por v3-client-planning. Não elimina nem substitui clientes, cuidadoras, utilizadores, serviços, movimentos ou credenciais existentes. As fotografias ficam na base de dados SQLite.

1. Efetuar backup consistente da base de dados (incluindo WAL através da ferramenta de backup existente) e dos ficheiros atuais. Guardar o backup fora da pasta pública.
2. Publicar durante uma janela sem gravações. Extrair o ZIP de atualização e copiar o conteúdo da pasta `upload-ready` para a pasta onde já estão index.php e bootstrap.php, respeitando assets, lib e views. Enviar todos os ficheiros do pacote, incluindo os novos, em conjunto.
3. Não substituir config-local.php, ficheiros SQLite, data ou configurações do servidor. O pacote não contém esses elementos.
4. Abrir a aplicação, autenticar como administrador, confirmar clientes/serviços existentes e verificar Clientes, Cuidadoras, Escalas, Pocket e Finanças. A primeira abertura aplica as adições ao esquema. Os scripts alterados têm versão de cache atualizada.
5. Confirmar um agendamento, pré-visualização de cópia mensal e fotografia num ambiente de ensaio antes de publicar em produção.

O ZIP é incremental sobre a versão auditada já integrada no main (2d28da8); não é uma instalação completa. A alteração dos campos requer os novos ficheiros PHP: não enviar apenas views ou JavaScript. Reverter o código antigo desativa as novas regras de tarifas; não usar essa reversão para continuar a faturação de serviços criados nesta versão. Se for necessário recuperar o backup, conciliar primeiro quaisquer registos criados depois dele.

## Verificação

- PHP: lint de 34 ficheiros, sem erros. JavaScript: validação sintática dos seis scripts da aplicação, sem erros.
- Testes de backend: migrações, autenticação, permissões, sobreposições, transações, idempotência, tarifas, feriados, três horários, séries, cópia mensal, fotografias e datas de nascimento; 65 aprovados, zero falhas.
- Navegador: nove cenários das novas funcionalidades e 16 verificações da suite de regressão, aprovados com dados fictícios.
- Navegação dos três perfis a 1440, 768 e 390 px: 57 visitas de páginas, 114 ligações internas verificadas e 66 aberturas/fechos de modais; sem erros de JavaScript ou respostas locais inesperadas de erro.
- Calendário móvel da cuidadora: sem transbordo horizontal e detalhe de serviço acessível; captura revista em output/planning-features/carer-calendar.png.
- Não foi testado no servidor de produção. Não existe dependência nova de bibliotecas externas ou de GD para as fotografias.

Testes reproduzíveis: `tools/php/php.exe tests/run.php`, `tests/planning-browser.cjs`, `tests/browser-qa.cjs` e `tests/technical-navigation.cjs`. Os testes de navegador exigem explicitamente um servidor local, uma base fictícia e as variáveis CARE4ALL_TEST_URL, CARE4ALL_TEST_PASSWORD e CARE4ALL_PLAYWRIGHT. Os ficheiros de testes não são necessários no servidor.
