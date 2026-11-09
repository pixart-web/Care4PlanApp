<div class="modal-backdrop" id="copy-month-modal" hidden><section class="modal-card" role="dialog" aria-modal="true" aria-labelledby="copy-month-title">
    <div class="modal-head"><h2 id="copy-month-title">Duplicar mês</h2><button class="modal-close" type="button" data-close-modal aria-label="Fechar">×</button></div>
    <form method="post" class="modal-body" id="copy-month-form">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>"><input type="hidden" name="action" value="save_service"><input type="hidden" name="planning_mode" value="copy_month"><input type="hidden" name="idempotency_key" value="<?= bin2hex(random_bytes(24)) ?>"><input type="hidden" name="preview_token" value="">
        <label class="field"><span class="label">Mês de origem</span><input class="input" type="month" name="source_month" value="<?= e(substr($selectedDate,0,7)) ?>" required></label>
        <p class="muted">Copia todos os concelhos para o mês seguinte, mantendo a ordem dos dias da semana: primeira segunda-feira para primeira segunda-feira, e assim sucessivamente. Os novos serviços ficam agendados. Serviços cancelados não são copiados.</p>
        <label><input type="checkbox" name="allow_unmatched" value="1"> Se não existir a quinta ocorrência do dia da semana, copiar apenas as datas com correspondência.</label>
        <div data-month-preview role="status" aria-live="polite"></div>
        <div class="btn-row modal-actions"><button class="btn btn-secondary" type="button" data-month-preview-button>Pré-visualizar</button><button class="btn btn-primary" type="submit" disabled>Confirmar duplicação</button></div>
    </form>
</section></div>
