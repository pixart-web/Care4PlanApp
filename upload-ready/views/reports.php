<?php
$user = require_auth();
$pdo = db();
$isCarer = $user['role'] === 'CARER';
$carers = $pdo->query('SELECT * FROM carers ORDER BY name')->fetchAll();
$patients = $pdo->query('SELECT * FROM patients ORDER BY name')->fetchAll();

$selectSql = "SELECT reports.*, carers.name AS carer_name, patients.name AS patient_name, users.name AS sender_name,
        COALESCE(reply_counts.total_replies, 0) AS reply_count
    FROM reports
    LEFT JOIN carers ON carers.id = reports.carer_id
    LEFT JOIN patients ON patients.id = reports.patient_id
    LEFT JOIN users ON users.id = reports.sender_user_id
    LEFT JOIN (
        SELECT report_id, COUNT(*) AS total_replies
        FROM report_replies
        GROUP BY report_id
    ) reply_counts ON reply_counts.report_id = reports.id";

if ($isCarer) {
    $stmt = $pdo->prepare($selectSql . " WHERE reports.carer_id = ? ORDER BY reports.updated_at DESC, reports.created_at DESC");
    $stmt->execute([(int) $user['carer_id']]);
} else {
    require_role(['ADMIN', 'EDITOR']);
    $stmt = $pdo->query($selectSql . " ORDER BY reports.updated_at DESC, reports.created_at DESC");
}
$reports = $stmt->fetchAll();
if ($isCarer) {
    $markRepliesRead = $pdo->prepare("UPDATE report_replies
        SET read_by_carer_at = COALESCE(read_by_carer_at, ?)
        WHERE sender_role != 'CARER'
            AND read_by_carer_at IS NULL
            AND report_id IN (SELECT id FROM reports WHERE carer_id = ? AND sender_role = 'CARER')");
    $markRepliesRead->execute([now(), (int) $user['carer_id']]);
}
$replyHistory = [];
if (! empty($reports)) {
    $ids = array_map(fn($report) => (int) $report['id'], $reports);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $replyStmt = $pdo->prepare("SELECT report_replies.*, users.name AS sender_name
        FROM report_replies
        LEFT JOIN users ON users.id = report_replies.sender_user_id
        WHERE report_replies.report_id IN ({$placeholders})
        ORDER BY report_replies.created_at ASC");
    $replyStmt->execute($ids);
    foreach ($replyStmt->fetchAll() as $reply) {
        $replyHistory[(int) $reply['report_id']][] = $reply;
    }
}

layout('Relatórios - Care4All', function () use ($isCarer, $carers, $patients, $reports, $replyHistory) { ?>
    <div class="page-head">
        <div>
            <h1>Relatórios</h1>
            <p class="muted"><?= $isCarer ? 'Crie relatórios associados a utentes.' : 'Crie relatórios para cuidadoras e acompanhe os relatórios recebidos.' ?></p>
        </div>
    </div>

    <section class="card action-panel">
        <div class="action-panel-text">
            <h2>Criar relatório</h2>
            <p class="muted"><?= $isCarer ? 'Associe o relatório a um utente.' : 'Associe o relatório a uma cuidadora.' ?></p>
        </div>
        <div class="btn-row action-buttons">
            <button class="btn btn-primary" type="button" data-open-modal="add-report-modal">Criar</button>
        </div>
    </section>

    <section class="card list-panel">
        <div class="list-panel-head">
            <div>
                <h2>Lista de relatórios</h2>
                <p class="muted"><?= count($reports) ?> relatório<?= count($reports) === 1 ? '' : 's' ?> registado<?= count($reports) === 1 ? '' : 's' ?>.</p>
            </div>
        </div>
        <?php if (empty($reports)): ?>
            <div class="empty-state"><strong>Ainda não existem relatórios.</strong><p class="muted">Clique em Criar para adicionar o primeiro relatório.</p></div>
        <?php else: ?>
            <div class="report-list">
                <?php foreach ($reports as $report):
                    $fromCarer = $report['sender_role'] === 'CARER';
                    $isUnread = $isCarer ? ($report['sender_role'] !== 'CARER' && empty($report['read_by_carer_at'])) : ($fromCarer && empty($report['read_by_admin_at']));
                    $senderLabel = $fromCarer ? 'Cuidadora: ' . ($report['carer_name'] ?? $report['sender_name'] ?? '-') : 'Admin: ' . ($report['sender_name'] ?? '-');
                    $replies = $replyHistory[(int) $report['id']] ?? [];
                ?>
                    <article class="report-item <?= $isUnread ? 'unread' : '' ?>">
                        <div class="report-head">
                            <div>
                                <h3>
                                    <?= e($report['title']) ?>
                                    <?php if ($isUnread): ?><span class="badge badge-alert">Novo</span><?php endif; ?>
                                    <?php if ((int) $report['reply_count'] > 0): ?><span class="badge badge-ok">Respondido</span><?php endif; ?>
                                </h3>
                                <p class="muted">
                                    <?= date('d/m/Y H:i', strtotime($report['created_at'])) ?> · <?= e($senderLabel) ?>
                                    <?php if (! $fromCarer && ! $isCarer): ?> · Para: <?= e($report['carer_name'] ?? '-') ?><?php endif; ?>
                                    <?php if (! empty($report['patient_name'])): ?> · Utente: <?= e($report['patient_name']) ?><?php endif; ?>
                                </p>
                            </div>
                            <div class="btn-row report-actions">
                                <?php if ($isUnread): ?>
                                    <form method="post">
                                        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="action" value="mark_report_read">
                                        <input type="hidden" name="id" value="<?= $report['id'] ?>">
                                        <button class="btn btn-secondary">Marcar como lido</button>
                                    </form>
                                <?php endif; ?>
                                <button class="btn btn-secondary" type="button" data-open-modal="reply-report-modal" data-reply-report-id="<?= $report['id'] ?>" data-reply-report-title="<?= e($report['title']) ?>">Responder</button>
                                <form method="post" onsubmit="return confirm('Tem a certeza que pretende apagar este relatório?');">
                                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                                    <input type="hidden" name="action" value="delete_report">
                                    <input type="hidden" name="id" value="<?= $report['id'] ?>">
                                    <button class="btn btn-danger">Apagar</button>
                                </form>
                            </div>
                        </div>
                        <details class="report-history">
                            <summary>Abrir histórico</summary>
                            <div class="history-thread">
                                <div class="history-entry">
                                    <div class="history-meta"><strong><?= e($senderLabel) ?></strong><span><?= date('d/m/Y H:i', strtotime($report['created_at'])) ?></span></div>
                                    <p class="report-body"><?= nl2br(e($report['body'])) ?></p>
                                </div>
                                <?php foreach ($replies as $reply):
                                    $replyFromCarer = $reply['sender_role'] === 'CARER';
                                    $replySender = $replyFromCarer ? 'Cuidadora' : 'Admin';
                                ?>
                                    <div class="history-entry reply-entry">
                                        <div class="history-meta"><strong><?= e($replySender . ': ' . ($reply['sender_name'] ?? '-')) ?></strong><span><?= date('d/m/Y H:i', strtotime($reply['created_at'])) ?></span></div>
                                        <p class="report-body"><?= nl2br(e($reply['body'])) ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </details>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <div class="modal-backdrop" id="add-report-modal" hidden>
        <section class="modal-card" role="dialog" aria-modal="true" aria-labelledby="add-report-title">
            <div class="modal-head">
                <div>
                    <h2 id="add-report-title">Criar relatório</h2>
                    <p class="muted"><?= $isCarer ? 'Selecione o utente e escreva o relatório.' : 'Selecione a cuidadora e escreva o relatório.' ?></p>
                </div>
                <button class="modal-close" type="button" data-close-modal aria-label="Fechar">x</button>
            </div>
            <form method="post" class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="save_report">
                <?php if ($isCarer): ?>
                    <label class="field"><span class="label">Utente</span><select class="input" name="patient_id" required><option value="">Selecionar</option><?php foreach ($patients as $patient): ?><option value="<?= $patient['id'] ?>"><?= e($patient['name']) ?></option><?php endforeach; ?></select></label>
                <?php else: ?>
                    <label class="field"><span class="label">Cuidadora</span><select class="input" name="carer_id" required><option value="">Selecionar</option><?php foreach ($carers as $carer): ?><option value="<?= $carer['id'] ?>"><?= e($carer['name']) ?></option><?php endforeach; ?></select></label>
                <?php endif; ?>
                <label class="field"><span class="label">Título</span><input class="input" name="title" required></label>
                <label class="field"><span class="label">Relatório</span><textarea class="input textarea" name="body" rows="7" required></textarea></label>
                <div class="btn-row modal-actions"><button class="btn btn-primary">Criar</button><button class="btn btn-secondary" type="button" data-close-modal>Cancelar</button></div>
            </form>
        </section>
    </div>

    <div class="modal-backdrop" id="reply-report-modal" hidden>
        <section class="modal-card modal-card-sm" role="dialog" aria-modal="true" aria-labelledby="reply-report-title">
            <div class="modal-head">
                <div>
                    <h2 id="reply-report-title">Responder</h2>
                    <p class="muted">Resposta ao relatório: <strong data-reply-title></strong></p>
                </div>
                <button class="modal-close" type="button" data-close-modal aria-label="Fechar">x</button>
            </div>
            <form method="post" class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="reply_report">
                <input type="hidden" name="id" value="" data-reply-id>
                <label class="field"><span class="label">Resposta</span><textarea class="input textarea" name="body" rows="7" required></textarea></label>
                <div class="btn-row modal-actions"><button class="btn btn-primary">Enviar resposta</button><button class="btn btn-secondary" type="button" data-close-modal>Cancelar</button></div>
            </form>
        </section>
    </div>
<?php });