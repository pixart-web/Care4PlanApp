# Correções técnicas Care4All

Base 94f3832. Três correções internas para revisão.

1. O handler de pré-visualização obtém o resumo a partir do mesmo plano e dos mesmos conflitos devolvidos. A gravação compara esse resumo sob BEGIN IMMEDIATE, antes de escrever. Uma repetição de um lote já confirmado continua a devolver o resultado guardado quando o plano corresponde. A verificação de validade temporal do token continua no handler.
2. A confirmação de visitas partilhadas usa HMAC com segredo aleatório da sessão. A mensagem inclui chave de idempotência, plano e visitas em conflito. Alterar qualquer desses elementos exige uma nova confirmação. O token CSRF continua obrigatório.
3. O login conclui a atualização opcional do hash, a limpeza das tentativas e a escrita da auditoria na mesma transação, antes de autenticar a sessão. O evento recebe explicitamente o ID da pessoa autenticada. O teste injeta falha de escrita e verifica que o dashboard continua a exigir autenticação.

Código de produção: upload-ready/index.php, upload-ready/lib/scheduling.php e upload-ready/lib/security.php. Testes: tests/planning-features.php, tests/technical-http.php e tests/backup.php. Documentação: este ficheiro, AUDITORIA-TECNICA-CARE4ALL.md, RELATORIO-REGRESSAO-CARE4ALL.md e DEPLOY-MANUTENCAO-CARE4ALL.md.

O esquema SQLite, as fórmulas e os ficheiros de interface não foram modificados. A alteração de assinatura interna adiciona parâmetros opcionais, conservando os chamadores existentes. Uma pré-visualização ou confirmação iniciada na versão anterior poderá ter de ser repetida depois de publicar.
