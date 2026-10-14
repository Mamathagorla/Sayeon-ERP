<?= $this->extend('layouts/main') ?>

<?= $this->section('pageActions') ?>
<a href="<?= site_url('hr/onboarding/role-groups') ?>" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>All role groups</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php $disabled = $editable ? '' : 'disabled'; ?>

<?php if ($group && ! $editable): ?>
<div class="alert alert-warning small d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span>This is a shared default and is read-only. Make a copy to change the questions for your company.</span>
    <?php if ($canClone): ?>
        <form action="<?= site_url('hr/onboarding/role-groups/' . $group['id'] . '/clone') ?>" method="post"><?= csrf_field() ?><button class="btn btn-sm btn-primary" type="submit">Customise for my company</button></form>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="sy-card mb-3" style="height:auto">
    <div class="sy-card-header"><strong><?= $group ? 'Role group' : 'New role group' ?></strong></div>
    <div class="sy-card-body">
        <form action="<?= $group ? site_url('hr/onboarding/role-groups/' . $group['id']) : site_url('hr/onboarding/role-groups') ?>" method="post">
            <?= csrf_field() ?>
            <fieldset <?= $disabled ?>>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" maxlength="100" required value="<?= esc(old('name', $group['name'] ?? '')) ?>" placeholder="e.g. Sales &amp; Business Development">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Priority <span class="text-muted small">(lower wins a tie)</span></label>
                        <input type="number" name="priority" class="form-control" min="1" max="999" value="<?= esc(old('priority', $group['priority'] ?? 100)) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label d-block">Status</label>
                        <div class="form-check form-switch mt-2"><input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="gActive" <?= old('is_active', $group['is_active'] ?? 1) ? 'checked' : '' ?>><label class="form-check-label" for="gActive">Active</label></div>
                    </div>
                    <?php if (! empty($companies)): ?>
                    <div class="col-md-6">
                        <label class="form-label">Company</label>
                        <select name="company_id" class="form-select"><option value="">Shared default (all companies)</option>
                            <?php foreach ($companies as $c): ?><option value="<?= (int) $c['id'] ?>"><?= esc($c['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="col-12">
                        <label class="form-label">Matching keywords</label>
                        <textarea name="keywords" class="form-control" rows="2" maxlength="2000" placeholder="sales, business development, account manager, inside sales"><?= esc(old('keywords', $group['keywords'] ?? '')) ?></textarea>
                        <div class="form-text">Comma separated. A candidate gets this group when one of these words or phrases appears in their job title (counts most), department or job description. Leave empty for a fallback group.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Role overview <span class="text-muted small">(shown to candidates who have no job description of their own)</span></label>
                        <textarea name="description" class="form-control" rows="3" maxlength="2000"><?= esc(old('description', $group['description'] ?? '')) ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label d-block">Extra sections for this role</label>
                        <?php $oldFeatures = old('features', null, false); $on = is_array($oldFeatures) ? $oldFeatures : array_filter(explode(',', (string) ($group['features'] ?? ''))); ?>
                        <?php foreach ($features as $key => $label): ?>
                            <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="features[]" value="<?= esc($key) ?>" id="feat_<?= esc($key) ?>" <?= in_array($key, $on, true) ? 'checked' : '' ?>><label class="form-check-label" for="feat_<?= esc($key) ?>"><?= esc($label) ?></label></div>
                        <?php endforeach; ?>
                        <div class="form-text">Freshers always get internships and projects; experienced candidates get employment history.</div>
                    </div>
                </div>
                <?php if ($editable): ?><div class="mt-3"><button type="submit" class="btn btn-primary"><?= $group ? 'Save changes' : 'Create role group' ?></button></div><?php endif; ?>
            </fieldset>
        </form>
    </div>
</div>

<?php if ($group): ?>
<!-- Questions -->
<div class="sy-card mb-3" style="height:auto">
    <div class="sy-card-header"><strong>Candidate questions</strong> <span class="text-muted small"><?= count($questions) ?></span></div>
    <div class="sy-card-body p-0">
        <?php if ($questions === []): ?><p class="text-muted small p-3 mb-0">No questions yet — candidates in this group only get the common sections.</p><?php else: ?>
        <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
            <thead><tr><th style="width:60px">Order</th><th>Question</th><th>Type</th><th>For</th><th>Required</th><th>Status</th><?php if ($editable): ?><th class="text-end">Actions</th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($questions as $q): ?>
                <tr>
                    <td class="text-muted"><?= (int) $q['sort_order'] ?></td>
                    <td><?= esc($q['label']) ?><?php if ($q['help_text']): ?><div class="text-muted small"><?= esc($q['help_text']) ?></div><?php endif; ?></td>
                    <td class="small"><?= esc($types[$q['field_type']] ?? $q['field_type']) ?></td>
                    <td class="small"><?= esc($appliesTo[$q['applies_to']] ?? $q['applies_to']) ?></td>
                    <td><?= $q['is_required'] ? '<span class="badge bg-warning text-dark">Required</span>' : '<span class="text-muted small">Optional</span>' ?></td>
                    <td><span class="badge bg-<?= $q['is_active'] ? 'success' : 'secondary' ?>"><?= $q['is_active'] ? 'Active' : 'Hidden' ?></span></td>
                    <?php if ($editable): ?>
                    <td class="text-end">
                        <div class="d-inline-flex gap-1">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-edit-question='<?= esc(json_encode($q), 'attr') ?>'><i class="fas fa-pen"></i></button>
                            <form action="<?= site_url('hr/onboarding/role-groups/' . $group['id'] . '/questions/' . $q['id'] . '/delete') ?>" method="post" onsubmit="return confirm('Remove this question? Answers already given are kept.');"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger" type="submit"><i class="fas fa-trash"></i></button></form>
                        </div>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </div>
    <?php if ($editable): ?>
    <div class="sy-card-body border-top">
        <form id="qForm" action="<?= site_url('hr/onboarding/role-groups/' . $group['id'] . '/questions') ?>" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="question_id" id="q_id" value="">
            <div class="fw-semibold small mb-2" id="qFormTitle">Add a question</div>
            <div class="row g-2">
                <div class="col-md-8"><label class="form-label small mb-1">Question</label><input type="text" name="label" id="q_label" class="form-control form-control-sm" maxlength="255" required></div>
                <div class="col-md-4"><label class="form-label small mb-1">Answer type</label><select name="field_type" id="q_type" class="form-select form-select-sm"><?php foreach ($types as $k => $l): ?><option value="<?= esc($k) ?>"><?= esc($l) ?></option><?php endforeach; ?></select></div>
                <div class="col-12" id="q_options_wrap" hidden><label class="form-label small mb-1">Options <span class="text-muted">(one per line)</span></label><textarea name="options" id="q_options" class="form-control form-control-sm" rows="3" maxlength="2000"></textarea></div>
                <div class="col-md-6"><label class="form-label small mb-1">Help text <span class="text-muted">(optional)</span></label><input type="text" name="help_text" id="q_help" class="form-control form-control-sm" maxlength="255"></div>
                <div class="col-md-3"><label class="form-label small mb-1">Asked of</label><select name="applies_to" id="q_applies" class="form-select form-select-sm"><?php foreach ($appliesTo as $k => $l): ?><option value="<?= esc($k) ?>"><?= esc($l) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3"><label class="form-label small mb-1">Order</label><input type="number" name="sort_order" id="q_sort" class="form-control form-control-sm" min="0" max="9999" value="<?= (count($questions) + 1) * 10 ?>"></div>
                <div class="col-12 d-flex gap-3 flex-wrap align-items-center">
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="is_required" value="1" id="q_req"><label class="form-check-label small" for="q_req">Required to submit</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" id="q_hide"><input type="hidden" name="is_active" id="q_active" value="1"><label class="form-check-label small" for="q_hide">Hide from candidates</label></div>
                    <button class="btn btn-primary btn-sm ms-auto" type="submit" id="qSubmit">Add question</button>
                    <button class="btn btn-light btn-sm" type="button" id="qCancel" hidden>Cancel edit</button>
                </div>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<!-- Evaluation criteria -->
<div class="sy-card mb-3" style="height:auto">
    <div class="sy-card-header"><strong>HR evaluation criteria</strong> <span class="text-muted small"><?= count($criteria) ?></span></div>
    <div class="sy-card-body p-0">
        <?php if ($criteria === []): ?><p class="text-muted small p-3 mb-0">No criteria — HR can still write free-text assessments.</p><?php else: ?>
        <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
            <thead><tr><th style="width:60px">Order</th><th>Criterion</th><th>Status</th><?php if ($editable): ?><th class="text-end">Actions</th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($criteria as $c): ?>
                <tr>
                    <td class="text-muted"><?= (int) $c['sort_order'] ?></td>
                    <td><?= esc($c['label']) ?><?php if ($c['description']): ?><div class="text-muted small"><?= esc($c['description']) ?></div><?php endif; ?></td>
                    <td><span class="badge bg-<?= $c['is_active'] ? 'success' : 'secondary' ?>"><?= $c['is_active'] ? 'Active' : 'Hidden' ?></span></td>
                    <?php if ($editable): ?>
                    <td class="text-end"><div class="d-inline-flex gap-1">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-edit-criterion='<?= esc(json_encode($c), 'attr') ?>'><i class="fas fa-pen"></i></button>
                        <form action="<?= site_url('hr/onboarding/role-groups/' . $group['id'] . '/criteria/' . $c['id'] . '/delete') ?>" method="post" onsubmit="return confirm('Remove this criterion?');"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger" type="submit"><i class="fas fa-trash"></i></button></form>
                    </div></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </div>
    <?php if ($editable): ?>
    <div class="sy-card-body border-top">
        <form id="cForm" action="<?= site_url('hr/onboarding/role-groups/' . $group['id'] . '/criteria') ?>" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="criterion_id" id="c_id" value="">
            <div class="fw-semibold small mb-2" id="cFormTitle">Add a criterion</div>
            <div class="row g-2">
                <div class="col-md-5"><label class="form-label small mb-1">Criterion</label><input type="text" name="label" id="c_label" class="form-control form-control-sm" maxlength="150" required></div>
                <div class="col-md-5"><label class="form-label small mb-1">What to look for <span class="text-muted">(optional)</span></label><input type="text" name="description" id="c_desc" class="form-control form-control-sm" maxlength="255"></div>
                <div class="col-md-2"><label class="form-label small mb-1">Order</label><input type="number" name="sort_order" id="c_sort" class="form-control form-control-sm" min="0" max="9999" value="<?= (count($criteria) + 1) * 10 ?>"></div>
                <div class="col-12 d-flex gap-3 align-items-center">
                    <div class="form-check"><input class="form-check-input" type="checkbox" id="c_hide"><input type="hidden" name="is_active" id="c_active" value="1"><label class="form-check-label small" for="c_hide">Hide</label></div>
                    <button class="btn btn-primary btn-sm ms-auto" type="submit" id="cSubmit">Add criterion</button>
                    <button class="btn btn-light btn-sm" type="button" id="cCancel" hidden>Cancel edit</button>
                </div>
            </div>
            <div class="form-text mt-2">Criteria are scored 1–5 by HR as guidance only. A score never approves or rejects a candidate on its own.</div>
        </form>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    function $(id) { return document.getElementById(id); }
    var qType = $('q_type');
    if (qType) {
        var sync = function () { $('q_options_wrap').hidden = qType.value !== 'select'; };
        qType.addEventListener('change', sync); sync();
        $('q_hide').addEventListener('change', function () { $('q_active').value = this.checked ? '0' : '1'; });
        var qDefaults = { sort: $('q_sort').value };
        function qReset() { $('qForm').reset(); $('q_id').value = ''; $('q_sort').value = qDefaults.sort; $('q_active').value = '1'; $('qFormTitle').textContent = 'Add a question'; $('qSubmit').textContent = 'Add question'; $('qCancel').hidden = true; sync(); }
        $('qCancel').addEventListener('click', qReset);
        [].forEach.call(document.querySelectorAll('[data-edit-question]'), function (b) {
            b.addEventListener('click', function () {
                var q = JSON.parse(b.getAttribute('data-edit-question'));
                $('q_id').value = q.id; $('q_label').value = q.label; qType.value = q.field_type; $('q_options').value = q.options || '';
                $('q_help').value = q.help_text || ''; $('q_applies').value = q.applies_to; $('q_sort').value = q.sort_order;
                $('q_req').checked = String(q.is_required) === '1'; $('q_hide').checked = String(q.is_active) !== '1'; $('q_active').value = $('q_hide').checked ? '0' : '1';
                $('qFormTitle').textContent = 'Edit question'; $('qSubmit').textContent = 'Save question'; $('qCancel').hidden = false; sync();
                $('qForm').scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        });
    }
    if ($('cForm')) {
        var cDefault = $('c_sort').value;
        $('c_hide').addEventListener('change', function () { $('c_active').value = this.checked ? '0' : '1'; });
        function cReset() { $('cForm').reset(); $('c_id').value = ''; $('c_sort').value = cDefault; $('c_active').value = '1'; $('cFormTitle').textContent = 'Add a criterion'; $('cSubmit').textContent = 'Add criterion'; $('cCancel').hidden = true; }
        $('cCancel').addEventListener('click', cReset);
        [].forEach.call(document.querySelectorAll('[data-edit-criterion]'), function (b) {
            b.addEventListener('click', function () {
                var c = JSON.parse(b.getAttribute('data-edit-criterion'));
                $('c_id').value = c.id; $('c_label').value = c.label; $('c_desc').value = c.description || ''; $('c_sort').value = c.sort_order;
                $('c_hide').checked = String(c.is_active) !== '1'; $('c_active').value = $('c_hide').checked ? '0' : '1';
                $('cFormTitle').textContent = 'Edit criterion'; $('cSubmit').textContent = 'Save criterion'; $('cCancel').hidden = false;
                $('cForm').scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        });
    }
})();
</script>
<?= $this->endSection() ?>
