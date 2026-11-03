# Auditoria visual e UX Care4All

Data: 3 de novembro de 2026. Aplicação auditada: versão PHP em upload-ready.

## Âmbito e método

Inspeção do código e comparação da captura fornecida com a aplicação local.
Capturas antes/depois e verificações automáticas de 13 páginas em 1440, 768 e
390 px (39 combinações), mais regressão funcional a 320 px. Dados exclusivamente
fictícios. Inclui login, Dashboard, Escalas, Utentes, Cuidadoras, Utilizadores,
Relatórios, Finanças, Configurações e, no papel de cuidadora, Dashboard, Escalas,
Pocket, Perfil e Relatórios. Foram inspecionados os componentes partilhados,
estados vazios, navegação, editor de escalas e formulários.

A auditoria não é uma certificação WCAG nem um teste de todos os browsers.
O servidor de produção não foi acedido. A validação foi feita com PHP e Edge
locais; a publicação continua por realizar.

## Problemas encontrados e correções

| Prioridade | Constatação | Correção |
|---|---|---|
| Alta | Na captura enviada, o link de salto aparece permanentemente e faltam ícones; o problema não se reproduz com todos os assets locais atuais. Há indícios de assets incompletos/desatualizados no servidor. | Novo pacote inclui CSS base, JavaScript base e bibliotecas locais. URLs dos principais assets têm versão para invalidar a cache. Não é possível confirmar a causa exata sem inspecionar o servidor. |
| Alta | Tipografia serifada nos montantes e nos títulos operacionais criava competição visual e leitura irregular. | Tipografia sem serifa nos controlos, títulos e números; serifa reservada à marca e saudação. |
| Alta | Muitas regras CSS sobrepostas alteravam espaçamentos e cartões entre páginas. | Camada partilhada care-ui.css, com regras agrupadas para navegação, cartões, tabelas, formulários, modais e mobile. |
| Média | Navegação e conteúdo sem alinhamento consistente. | Barra lateral estável, margens comuns e cabeçalho com contexto à esquerda e hora/previsão à direita. |
| Média | Tabelas financeiras tinham linhas inteiras verdes e valores pouco alinhados. | Linhas neutras, números alinhados à direita e cor reservada às margens. Montantes e cálculos preservados. |
| Média | Ações e listas pareciam blocos desconectados. | Barras de ações compactas, cabeçalhos de lista próprios e botões com hierarquia constante. |
| Média | Configurações pareciam texto solto numa página vazia. | Painel delimitado e espaçamento consistente nas definições. |
| Média | O painel da cuidadora repetia a saudação e desperdiçava altura no telemóvel. | Saudação única, título “O meu dia”, dois indicadores lado a lado e Pocket em largura completa. |
| Média | Saudação fixa “Bom dia” também à noite. | Saudação dinâmica em Europe/Lisbon, relógio e sol/lua com nascer/pôr do sol calculados para Portimão. |
| Média | Faltava um elemento humano comum às gestoras e cuidadoras. | Dez mensagens curtas, seleção diária e botão acessível para ler outra. |
| Média | Finanças, Perfil e ações tinham alinhamentos diferentes em mobile. | Cabeçalhos à esquerda, ações adequadas à largura e cartões sem transbordo. |

## Identidade visual

Verde profundo #173d36 na navegação e títulos; verde-água #22776e nas ações;
aqua/sálvia nos estados suaves; lilás discreto como cor secundária e no ícone
noturno. Fundo neutro claro e cartões brancos. Logo e fotografia existentes
preservados. As cores específicas das cuidadoras mantêm-se intactas.

A matriz do calendário e as regras de agendamento não foram modificadas nesta
iteração. A folha de estilo nova exclui a tabela patient-calendar das regras
gerais de tabelas. Os dados, cálculos financeiros, autenticação e permissões não
foram alterados.

## Mensagens

1. O teu cuidado faz a diferença, mesmo nos gestos mais pequenos.
2. Hoje, oferece um sorriso. Pode ser o abraço de que alguém precisa.
3. Quem cuida também merece carinho. Guarda um momento para ti.
4. Um dia de cada vez, com calma, presença e coração.
5. Por trás de cada dia bem organizado, há uma equipa que se importa.
6. A tua presença traz conforto. Obrigada por estares desse lado.
7. Não precisas de fazer tudo sozinha. Juntas, cuidamos melhor.
8. Cada escuta atenta é uma forma bonita de cuidar.
9. Celebra as pequenas conquistas. Elas também fazem grandes dias.
10. Que hoje haja espaço para a gentileza, para os outros e para ti.

Sem animação automática nem troca contínua durante a leitura. A frase inicial
muda diariamente e o botão permite percorrer as dez. O relógio não usa anúncios
constantes para leitores de ecrã.

## Hora e temperatura

Hora e data em Europe/Lisbon, independentemente do fuso do dispositivo. O ícone
representa dia/noite em Portimão, não a previsão de céu limpo. Nascer e pôr do
sol calculados em PHP, com valores para o dia e sete dias seguintes; se uma
página ficar aberta por mais de oito dias sem recarregar, o indicador usa uma
aproximação 07:00–19:00 até ser recarregada.

A temperatura é a previsão mínima/máxima de hoje para Portimão, não uma medição
instantânea. Fonte e explicação disponíveis ao abrir o indicador. Consulta à
API pública do IPMA, sem envio de nomes, serviços, localização GPS ou cookies
da aplicação. A preferência não altera a base. Cache local de uma hora,
validação da data, timeout de oito segundos e estado “Indisponível” em caso de
falha. A hora e o resto da plataforma continuam a funcionar sem meteorologia.

Documentação: https://api.ipma.pt/
Endpoint: https://api.ipma.pt/open-data/forecast/meteorology/cities/daily/1081100.json
O IPMA solicita uma comunicação da utilização para fins estatísticos. Não foi
enviado qualquer email em nome da empresa.

## Verificação

- 35 testes de regressão PHP/HTTP aprovados.
- 16 verificações de browser aprovadas, incluindo criação recorrente, detalhes,
  duplo clique, editor, teclado e área da cuidadora.
- 39 combinações página/dimensão sem transbordo horizontal, sem erros JavaScript
  ou falhas de assets locais; link de salto oculto fora do foco.
- Testes específicos: dez frases distintas, hora com dispositivo em Nova Iorque,
  sol ao meio-dia, lua à noite, saudações, previsão válida, falha de rede,
  previsão desatualizada, Escape e acesso por Tab.
- Integração IPMA real observada no browser, além dos cenários simulados.

Capturas: output/visual-audit/before e output/visual-audit/after.
Os JSON de cada diretório registam o resultado das verificações de dimensão.

## Publicação

O ZIP desta revisão inclui todos os assets da interface (incluindo bibliotecas
locais), bem como as vistas layout, login, finances, placeholder e
carer-dashboard. É uma atualização sobre a versão com o calendário já resolvido.
Não inclui base, configuração, bootstrap, index.php, migrações ou dados fictícios.
Substituir apenas os ficheiros correspondentes; nunca apagar a instalação inteira.
Após publicar, confirmar os ícones, o cabeçalho, Finanças, login e o calendário.
