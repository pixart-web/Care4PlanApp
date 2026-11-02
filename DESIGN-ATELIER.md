# Care4All Atelier

Redesign aplicado exclusivamente à aplicação PHP `upload-ready`, em 30/10/2026.
O mockup fornecido foi usado como direção visual: não foram copiados textos,
números, fotografias, nomes nem funcionalidades inexistentes.

## Implementação

- Tokens visuais pedidos em `assets/atelier.css`: floresta, marfim, branco quente, sálvia, terracota, grafite e bordas neutras. Georgia nos títulos e system sans na interface, com algarismos tabulares.
- Sidebar de 190px, ícones Lucide locais e cabeçalho contextual. As permissões e destinos de navegação foram mantidos.
- Calendário prioritário, barra compacta com concelho/data/vista e filtros adicionais recolhíveis. Detalhes num painel lateral acessível; edição separada com formulário e pré-visualização em duas colunas no desktop, sequencial em mobile.
- Dia mantém 24 horas e paginação de cuidadoras; semana, mês e lista preservados. O mês apresenta dias alinhados de segunda a domingo. Conflitos são calculados por IDs e intervalos, incluindo concelhos diferentes.
- Agenda da cuidadora cronológica em mobile, ação Concluir destacada e calendário completo opcional. Mantidos os POST existentes para concluir/desfazer; não foi inventado um registo de ponto nem um novo estado financeiro.
- Listagens, formulários, finanças, dashboards e modais usam os mesmos tokens. Fundos de lucro/prejuízo mantidos.
- Sem alterações de esquema, hashes, cálculos financeiros ou dados. Sem frameworks, builds ou dependências de servidor adicionais. Sem publicação no servidor ou merge.

## Ficheiros

Tema e shell: `assets/atelier.css`, `views/layout.php`, `views/login.php`, `assets/app.js`.
Planeamento: `views/scales.php`, `views/scales-workspace.php`, `assets/atelier-scales.css`, `assets/scales-workspace.js`, `assets/scales-v2.js`.
Cuidadora: `views/carer-services.php`, `views/carer-dashboard.php`, `assets/carer-services.js`.
Ícones: `assets/vendor/lucide-0.468.0.min.js`, licença local.

## Imagem Original Do Login

`upload-ready/assets/care-at-home.webp`, 1536×1024, aproximadamente 156 KB.
Gerada com a ferramenta integrada image_gen; não representa clientes nem
colaboradoras reais. Não usada em dashboards ou calendários. O PNG gerado foi
convertido para WebP para reduzir a transferência, sem alterar a composição.

Prompt utilizado:

> Use case: photorealistic-natural. Asset type: background photograph for a Portuguese domiciliary-care management application's login screen. A quiet candid editorial photograph in a real-looking contemporary Algarve home: an older woman around 78 years old seated comfortably next to a large window, a female professional home carer around 40 wearing a plain muted sage shirt sitting beside her and listening attentively, a hand gently resting on the older woman's hand. Respectful, warm, natural, dignified, no exaggerated smiles, no medical equipment. The setting is bright but detailed, forest-green and ivory textiles, a wooden side table, soft late-afternoon daylight. Wide landscape 3:2 composition; people are on the right half; the left half has a calm deep forest-green shadowed interior with negative space for a login form overlay. Premium documentary hospitality photography, natural skin texture, 50mm lens, human and grounded, not stock advertisement. No text, no logos, no watermark, no UI. These are entirely fictional people; do not use the conversation's reference image or reproduce its people or layout.

## Validação

- 35 testes de regressão passaram, sem falhas, com SQLite fictício isolado.
- 16 verificações Playwright/Edge passaram: login, calendário 24h, filtros recolhíveis, recorrência, confirmação invalidada por alterações, cinco serviços criados, semana, mês, painel de detalhes, duplicação, Escape, páginas admin em mobile e agenda/calendário da cuidadora.
- Viewports: 1440, 768, 390 e 320 píxeis; sem overflow horizontal da página nos cenários testados. Formulário mobile sequencial e formulário desktop com duas colunas verificados.
- Capturas inspecionadas em `output/playwright`. Ficheiros PHP e JavaScript próprios sem erros de sintaxe; sem erros de página ou HTTP 500 no teste do browser.
- O ambiente de demonstração usa apenas fixtures. Apache/cPanel, dispositivos físicos e tecnologias de assistência não foram testados nesta passagem visual.

O guia de publicação e backup da V2 continua aplicável. Não publicar a pasta
`.runtime` nem qualquer ficheiro de dados/configuração local.
