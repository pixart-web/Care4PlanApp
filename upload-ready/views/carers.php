<?php
require_role(['ADMIN', 'EDITOR']);
$pdo = db();
$carers = $pdo->query('SELECT * FROM carers ORDER BY name')->fetchAll();

layout('Cuidadoras - Care4All', function () use ($carers) { ?>
    <div class="page-head">
        <div>
            <h1>Cuidadoras</h1>
            <p class="muted">Equipa, estado atual e pocket do mês.</p>
        </div>
    </div>

    <?php foreach ($carers as $c): ?><span data-used-color="<?= e($c['color_hex']) ?>" hidden></span><?php endforeach; ?>

    <section class="card action-panel">
        <div class="action-panel-text">
            <h2>Gestão de cuidadoras</h2>
            <p class="muted">Adicione, altere ou apague cuidadoras através das opções abaixo.</p>
        </div>
        <div class="btn-row action-buttons">
            <button class="btn btn-primary" type="button" data-open-modal="add-carer-modal">Adicionar</button>
            <button class="btn btn-secondary" type="button" data-open-modal="edit-carer-modal" <?= empty($carers) ? 'disabled' : '' ?>>Alterar</button>
            <button class="btn btn-danger" type="button" data-open-modal="delete-carer-modal" <?= empty($carers) ? 'disabled' : '' ?>>Apagar</button>
        </div>
    </section>

    <section class="card list-panel">
        <div class="list-panel-head">
            <div>
                <h2>Lista de cuidadoras</h2>
                <p class="muted"><?= count($carers) ?> cuidadora<?= count($carers) === 1 ? '' : 's' ?> registada<?= count($carers) === 1 ? '' : 's' ?>.</p>
            </div>
        </div>

        <?php if (empty($carers)): ?>
            <div class="empty-state">
                <strong>Ainda não existem cuidadoras.</strong>
                <p class="muted">Clique em Adicionar cuidadora para criar o primeiro registo.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap desktop-table">
                <table>
                    <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Telefone</th>
                        <th>Email</th>
                        <th>Estado</th>
                        <th>Pocket</th>
                        <th>Cor</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($carers as $carer): ?>
                        <tr>
                            <td><strong><?php profile_photo_thumb('carer',(int)$carer['id']); ?><?= e($carer['name']) ?></strong></td>
                            <td><?= e($carer['phone']) ?></td>
                            <td><?= e($carer['email']) ?: '-' ?></td>
                            <td><span class="badge"><?= carer_status((int) $carer['id']) ?></span></td>
                            <td><?= money(pocket_for_carer((int) $carer['id'])) ?></td>
                            <td><span class="swatch" style="background:<?= e($carer['color_hex']) ?>" title="Cor da cuidadora"></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="mobile-cards">
                <?php foreach ($carers as $carer): ?>
                    <article class="mobile-item">
                        <strong><?php profile_photo_thumb('carer',(int)$carer['id']); ?><?= e($carer['name']) ?></strong>
                        <div class="mobile-line"><span>Telefone</span><span><?= e($carer['phone']) ?></span></div>
                        <div class="mobile-line"><span>Email</span><span><?= e($carer['email']) ?: '-' ?></span></div>
                        <div class="mobile-line"><span>Estado</span><span><?= carer_status((int) $carer['id']) ?></span></div>
                        <div class="mobile-line"><span>Pocket</span><span><?= money(pocket_for_carer((int) $carer['id'])) ?></span></div>
                        <div class="mobile-line"><span>Cor</span><span class="swatch" style="background:<?= e($carer['color_hex']) ?>"></span></div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <div class="modal-backdrop" id="add-carer-modal" hidden>
        <section class="modal-card" role="dialog" aria-modal="true" aria-labelledby="add-carer-title">
            <div class="modal-head">
                <div>
                    <h2 id="add-carer-title">Adicionar cuidadora</h2>
                    <p class="muted">Cria também o acesso da cuidadora ao sistema.</p>
                </div>
                <button class="modal-close" type="button" data-close-modal aria-label="Fechar">x</button>
            </div>
            <form method="post" enctype="multipart/form-data" class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="save_carer">
                <input type="hidden" name="id" value="">
                <?php profile_photo_field('carer'); ?>
                <label class="field"><span class="label">Data de nascimento</span><input class="input" name="birth_date" type="date" max="<?= date('Y-m-d') ?>" required></label>
                <label class="field"><span class="label">Pocket/hora — dias úteis</span><input class="input" name="hourly_rate_weekday" type="number" min="0" max="10000" step="0.01"></label>
                <label class="field"><span class="label">Pocket/hora — fins de semana e feriados</span><input class="input" name="hourly_rate_special" type="number" min="0" max="10000" step="0.01"><span class="subtitle">Em branco: aplica-se o valor dos dias úteis.</span></label>
                <label class="field"><span class="label">Nome</span><input class="input" name="name" required></label>
                <label class="field"><span class="label">Telefone</span><input class="input" name="phone" required></label>
                <label class="field"><span class="label">Email</span><input class="input" name="email" type="email" required></label>
                <label class="field"><span class="label">Palavra-passe</span><input class="input" name="password" type="password" required></label>
                <label class="field"><span class="label">Cor da cuidadora</span><input class="input" type="color" name="color_hex" value="#22776e" required></label>
                <input type="hidden" name="pocket_adjustment" value="0">
                <div class="btn-row color-row">
                    <span class="swatch" data-color-preview style="background:#ffffff" aria-label="Cor da cuidadora"></span>
                    <button class="btn btn-secondary" type="button" data-generate-color>Gerar cor</button>
                </div>
                <div class="btn-row modal-actions">
                    <button class="btn btn-primary">Adicionar</button>
                    <button class="btn btn-secondary" type="button" data-close-modal>Cancelar</button>
                </div>
            </form>
        </section>
    </div>

    <div class="modal-backdrop" id="edit-carer-modal" hidden>
        <section class="modal-card" role="dialog" aria-modal="true" aria-labelledby="edit-carer-title">
            <div class="modal-head">
                <div>
                    <h2 id="edit-carer-title">Alterar cuidadora</h2>
                    <p class="muted">Selecione uma cuidadora e atualize os dados.</p>
                </div>
                <button class="modal-close" type="button" data-close-modal aria-label="Fechar">x</button>
            </div>
            <form method="post" class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="save_carer">
                <input type="hidden" name="id" value="">
                <label class="field">
                    <span class="label">Selecionar cuidadora</span>
                    <select class="input" data-carer-selector required>
                        <option value="">Escolher cuidadora</option>
                        <?php foreach ($carers as $carer): ?>
                            <option
                                value="<?= $carer['id'] ?>"
                                data-name="<?= e($carer['name']) ?>" data-has-photo="<?= has_profile_photo('carer',(int)$carer['id'])?'1':'0' ?>" data-birth-date="<?= e($carer['birth_date'] ?? '') ?>" data-hourly-rate-weekday="<?= e((string)($carer['hourly_rate_weekday'] ?? '')) ?>" data-hourly-rate-special="<?= e((string)($carer['hourly_rate_special'] ?? '')) ?>"
                                data-phone="<?= e($carer['phone']) ?>"
                                data-email="<?= e($carer['email']) ?>"
                                data-color="<?= e($carer['color_hex']) ?>"
                                data-pocket-adjustment="<?= e((string) ($carer['pocket_adjustment'] ?? 0)) ?>"
                            ><?= e($carer['name']) ?> - <?= e($carer['phone']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php profile_photo_field('carer'); ?>
                <label class="field"><span class="label">Data de nascimento</span><input class="input" name="birth_date" type="date" max="<?= date('Y-m-d') ?>" required></label>
                <label class="field"><span class="label">Pocket/hora — dias úteis</span><input class="input" name="hourly_rate_weekday" type="number" min="0" max="10000" step="0.01"></label>
                <label class="field"><span class="label">Pocket/hora — fins de semana e feriados</span><input class="input" name="hourly_rate_special" type="number" min="0" max="10000" step="0.01"><span class="subtitle">Em branco: aplica-se o valor dos dias úteis.</span></label>
                <label class="field"><span class="label">Nome</span><input class="input" name="name" required></label>
                <label class="field"><span class="label">Telefone</span><input class="input" name="phone" required></label>
                <label class="field"><span class="label">Email</span><input class="input" name="email" type="email" required></label>
                <label class="field">
                    <span class="label">Palavra-passe</span>
                    <input class="input" name="password" type="password">
                    <span class="subtitle">Deixe em branco para manter a palavra-passe atual.</span>
                </label>
                <label class="field"><span class="label">Cor da cuidadora</span><input class="input" type="color" name="color_hex" value="#22776e" required></label>
                <label class="field"><span class="label">Ajuste manual do pocket</span><input class="input" name="pocket_adjustment" type="number" step="0.01" value="0"><span class="subtitle">Valor somado ao pocket mensal desta cuidadora.</span></label>
                <div class="btn-row color-row">
                    <span class="swatch" data-color-preview style="background:#ffffff" aria-label="Cor da cuidadora"></span>
                    <button class="btn btn-secondary" type="button" data-generate-color>Gerar cor</button>
                </div>
                <div class="btn-row modal-actions">
                    <button class="btn btn-primary">Alterar</button>
                    <button class="btn btn-secondary" type="button" data-close-modal>Cancelar</button>
                </div>
            </form>
        </section>
    </div>

    <div class="modal-backdrop" id="delete-carer-modal" hidden>
        <section class="modal-card modal-card-sm" role="dialog" aria-modal="true" aria-labelledby="delete-carer-title">
            <div class="modal-head">
                <div>
                    <h2 id="delete-carer-title">Apagar cuidadora</h2>
                    <p class="muted">Selecione a cuidadora que pretende apagar.</p>
                </div>
                <button class="modal-close" type="button" data-close-modal aria-label="Fechar">x</button>
            </div>
            <form method="post" class="modal-body" data-confirm="Apagar esta cuidadora?">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="delete_carer">
                <label class="field">
                    <span class="label">Cuidadora</span>
                    <select class="input" name="id" required>
                        <option value="">Escolher cuidadora</option>
                        <?php foreach ($carers as $carer): ?>
                            <option value="<?= $carer['id'] ?>"><?= e($carer['name']) ?> - <?= e($carer['phone']) ?></option>
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
