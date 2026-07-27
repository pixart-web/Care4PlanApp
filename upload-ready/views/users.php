<?php
require_role(['ADMIN']);
$pdo = db();
$users = $pdo->query('SELECT users.*, carers.name AS carer_name FROM users LEFT JOIN carers ON carers.id = users.carer_id ORDER BY users.name')->fetchAll();
$carers = $pdo->query('SELECT * FROM carers ORDER BY name')->fetchAll();

layout('Utilizadores - Care4All', function () use ($users, $carers) { ?>
    <div class="page-head">
        <div>
            <h1>Utilizadores</h1>
            <p class="muted">Gestão dos acessos à plataforma.</p>
        </div>
    </div>

    <section class="card action-panel">
        <div class="action-panel-text">
            <h2>Gestão de utilizadores</h2>
            <p class="muted">Adicione, altere ou apague utilizadores através das opções abaixo.</p>
        </div>
        <div class="btn-row action-buttons">
            <button class="btn btn-primary" type="button" data-open-modal="add-user-modal">Adicionar</button>
            <button class="btn btn-secondary" type="button" data-open-modal="edit-user-modal" <?= empty($users) ? 'disabled' : '' ?>>Alterar</button>
            <button class="btn btn-danger" type="button" data-open-modal="delete-user-modal" <?= empty($users) ? 'disabled' : '' ?>>Apagar</button>
        </div>
    </section>

    <section class="card list-panel">
        <div class="list-panel-head">
            <div>
                <h2>Lista de utilizadores</h2>
                <p class="muted"><?= count($users) ?> utilizador<?= count($users) === 1 ? '' : 'es' ?> registado<?= count($users) === 1 ? '' : 's' ?>.</p>
            </div>
        </div>

        <?php if (empty($users)): ?>
            <div class="empty-state">
                <strong>Ainda não existem utilizadores.</strong>
                <p class="muted">Clique em Adicionar para criar o primeiro acesso.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap desktop-table">
                <table>
                    <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Nome de utilizador</th>
                        <th>Função</th>
                        <th>Cuidadora</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><strong><?= e($u['name']) ?></strong></td>
                            <td><?= e($u['username']) ?></td>
                            <td><span class="badge"><?= e($u['role']) ?></span></td>
                            <td><?= e($u['carer_name']) ?: '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="mobile-cards">
                <?php foreach ($users as $u): ?>
                    <article class="mobile-item">
                        <strong><?= e($u['name']) ?></strong>
                        <div class="mobile-line"><span>Nome de utilizador</span><span><?= e($u['username']) ?></span></div>
                        <div class="mobile-line"><span>Função</span><span><?= e($u['role']) ?></span></div>
                        <div class="mobile-line"><span>Cuidadora</span><span><?= e($u['carer_name']) ?: '-' ?></span></div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <div class="modal-backdrop" id="add-user-modal" hidden>
        <section class="modal-card" role="dialog" aria-modal="true" aria-labelledby="add-user-title">
            <div class="modal-head">
                <div>
                    <h2 id="add-user-title">Adicionar utilizador</h2>
                    <p class="muted">Crie um novo acesso à plataforma.</p>
                </div>
                <button class="modal-close" type="button" data-close-modal aria-label="Fechar">x</button>
            </div>
            <form method="post" class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="save_user">
                <input type="hidden" name="id" value="">
                <label class="field"><span class="label">Nome</span><input class="input" name="name" required></label>
                <label class="field"><span class="label">Nome de utilizador</span><input class="input" name="username" required></label>
                <label class="field"><span class="label">Função</span><select class="input" name="role"><?php foreach (['ADMIN','EDITOR','CARER'] as $role): ?><option value="<?= $role ?>"><?= $role ?></option><?php endforeach; ?></select></label>
                <label class="field"><span class="label">Cuidadora associada</span><select class="input" name="carer_id"><option value="">Sem associação</option><?php foreach ($carers as $carer): ?><option value="<?= $carer['id'] ?>"><?= e($carer['name']) ?></option><?php endforeach; ?></select></label>
                <label class="field"><span class="label">Palavra-passe</span><input class="input" name="password" type="password" required></label>
                <div class="btn-row modal-actions">
                    <button class="btn btn-primary">Adicionar</button>
                    <button class="btn btn-secondary" type="button" data-close-modal>Cancelar</button>
                </div>
            </form>
        </section>
    </div>

    <div class="modal-backdrop" id="edit-user-modal" hidden>
        <section class="modal-card" role="dialog" aria-modal="true" aria-labelledby="edit-user-title">
            <div class="modal-head">
                <div>
                    <h2 id="edit-user-title">Alterar utilizador</h2>
                    <p class="muted">Selecione um utilizador e atualize os dados.</p>
                </div>
                <button class="modal-close" type="button" data-close-modal aria-label="Fechar">x</button>
            </div>
            <form method="post" class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="save_user">
                <input type="hidden" name="id" value="">
                <label class="field">
                    <span class="label">Selecionar utilizador</span>
                    <select class="input" data-user-selector required>
                        <option value="">Escolher utilizador</option>
                        <?php foreach ($users as $u): ?>
                            <option
                                value="<?= $u['id'] ?>"
                                data-name="<?= e($u['name']) ?>"
                                data-username="<?= e($u['username']) ?>"
                                data-role="<?= e($u['role']) ?>"
                                data-carer-id="<?= e((string) ($u['carer_id'] ?? '')) ?>"
                            ><?= e($u['name']) ?> - <?= e($u['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field"><span class="label">Nome</span><input class="input" name="name" required></label>
                <label class="field"><span class="label">Nome de utilizador</span><input class="input" name="username" required></label>
                <label class="field"><span class="label">Função</span><select class="input" name="role"><?php foreach (['ADMIN','EDITOR','CARER'] as $role): ?><option value="<?= $role ?>"><?= $role ?></option><?php endforeach; ?></select></label>
                <label class="field"><span class="label">Cuidadora associada</span><select class="input" name="carer_id"><option value="">Sem associação</option><?php foreach ($carers as $carer): ?><option value="<?= $carer['id'] ?>"><?= e($carer['name']) ?></option><?php endforeach; ?></select></label>
                <label class="field">
                    <span class="label">Palavra-passe</span>
                    <input class="input" name="password" type="password">
                    <span class="subtitle">Deixe em branco para manter a palavra-passe atual.</span>
                </label>
                <div class="btn-row modal-actions">
                    <button class="btn btn-primary">Alterar</button>
                    <button class="btn btn-secondary" type="button" data-close-modal>Cancelar</button>
                </div>
            </form>
        </section>
    </div>

    <div class="modal-backdrop" id="delete-user-modal" hidden>
        <section class="modal-card modal-card-sm" role="dialog" aria-modal="true" aria-labelledby="delete-user-title">
            <div class="modal-head">
                <div>
                    <h2 id="delete-user-title">Apagar utilizador</h2>
                    <p class="muted">Selecione o utilizador que pretende apagar.</p>
                </div>
                <button class="modal-close" type="button" data-close-modal aria-label="Fechar">x</button>
            </div>
            <form method="post" class="modal-body" onsubmit="return confirm('Apagar este utilizador?')">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="delete_user">
                <label class="field">
                    <span class="label">Utilizador</span>
                    <select class="input" name="id" required>
                        <option value="">Escolher utilizador</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= e($u['name']) ?> - <?= e($u['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="btn-row modal-actions">
                    <button class="btn btn-danger">Apagar</button>
                    <button class="btn btn-secondary" type="button" data-close-modal>Cancelar</button>
                </div>
            </form>
        </section>
    </div>
<?php });