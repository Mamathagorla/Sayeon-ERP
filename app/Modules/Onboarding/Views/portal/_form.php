<?php
/**
 * The candidate's multi-step information form (included by portal/show.php
 * while the record is editable). Everything the browser posts here is
 * re-validated by CandidateFormService::extract(); nothing in this file is
 * trusted by the server. Role-specific questions come from $questions
 * (configured per role group), the repeatable sections from
 * CandidateFormService::SECTIONS.
 *
 * @var array  $record
 * @var string $token
 * @var array  $values   current values (stored, or the last submitted attempt)
 * @var array  $errors   server errors keyed by field path
 * @var array  $group    resolved role group (name, feature_list, description)
 * @var array  $questions
 * @var array  $sections
 * @var array  $types
 * @var array  $documentTypes
 * @var array  $documents
 * @var int    $startStep
 * @var string $reference
 */

use App\Modules\Onboarding\Services\CandidateFormService;

$base      = site_url('onboarding/portal/' . $record['id'] . '/' . $token);
$features  = $group['feature_list'] ?? [];
$hasLinks  = in_array('links', $features, true);
$hasProj   = in_array('projects', $features, true);
$stepNames = ['Application', 'Personal & contact', 'Education', 'Skills & assessment', 'Experience & projects', 'Documents', 'Review & submit'];

$err = static fn (string $path): string => isset($errors[$path]) ? '<div class="cf-err" role="alert">' . esc($errors[$path]) . '</div>' : '';
$has = static fn (string $path): string => isset($errors[$path]) ? ' has-error' : '';
$v   = static fn (string $key): string => esc((string) ($values[$key] ?? ''));

$startAt = (int) $startStep;
if ($errors !== []) {
    // Open the earliest step that has something to fix.
    $startAt = count($stepNames) - 1;
    foreach (array_keys($errors) as $path) {
        $idx     = array_search(CandidateFormService::errorSection($path), $stepNames, true);
        $startAt = $idx === false ? $startAt : min($startAt, $idx);
    }
}
$startAt = max(0, min($startAt, count($stepNames) - 1));

/** One field of a repeatable row. */
$renderField = static function (string $section, $i, string $field, array $spec, array $data) use ($err, $has): string {
    $name  = "{$section}[{$i}][{$field}]";
    $path  = "{$section}.{$i}.{$field}";
    $val   = (string) ($data[$field] ?? '');
    $req   = ! empty($spec['required']);
    $label = esc($spec['label']) . ($req ? ' <span class="req">*</span>' : '');
    $id    = "f_{$section}_{$i}_{$field}";
    $wide  = in_array($spec['type'], ['textarea'], true) ? ' cf-wide' : '';

    if ($spec['type'] === 'check') {
        return '<div class="cf-field cf-check' . $wide . '"><label><input type="checkbox" name="' . $name . '" value="1" data-toggle-current' . ($val === '1' ? ' checked' : '') . '> ' . esc($spec['label']) . '</label></div>';
    }

    $attrs = ($req ? ' required' : '') . ' name="' . $name . '" id="' . $id . '"';
    switch ($spec['type']) {
        case 'textarea':
            $control = '<textarea' . $attrs . ' rows="3" maxlength="' . (int) ($spec['max'] ?? 1500) . '">' . esc($val) . '</textarea>';
            break;
        case 'year':
            $control = '<input type="number"' . $attrs . ' min="1950" max="' . ((int) date('Y') + 8) . '" value="' . esc($val) . '" inputmode="numeric" placeholder="YYYY">';
            break;
        case 'date':
            $control = '<input type="date"' . $attrs . ' max="' . date('Y-m-d') . '" value="' . esc($val) . '"' . ($field === 'to' ? ' data-end-date' : '') . '>';
            break;
        case 'select':
            $control = '<select' . $attrs . '><option value="">Select…</option>';
            foreach ($spec['options'] as $option) {
                $control .= '<option value="' . esc($option) . '"' . ($val === $option ? ' selected' : '') . '>' . esc($option) . '</option>';
            }
            $control .= '</select>';
            break;
        case 'url':
            $control = '<input type="text"' . $attrs . ' maxlength="255" value="' . esc($val) . '" inputmode="url" placeholder="https://">';
            break;
        default:
            $control = '<input type="text"' . $attrs . ' maxlength="' . (int) ($spec['max'] ?? 255) . '" value="' . esc($val) . '"' . (isset($spec['placeholder']) ? ' placeholder="' . esc($spec['placeholder']) . '"' : '') . '>';
    }

    return '<div class="cf-field' . $wide . $has($path) . '"><label for="' . $id . '">' . $label . '</label>' . $control . $err($path) . '</div>';
};

/** A whole row (also used, with $i = '__i__', for the "add another" template). */
$renderRow = static function (string $section, $i, array $data) use ($sections, $renderField): string {
    $out = '<div class="cf-row" data-row><div class="cf-row-head"><strong>' . esc($sections[$section]['item']) . ' <span data-row-number></span></strong>'
        . '<button type="button" class="cf-link" data-remove-row>Remove</button></div><div class="cf-grid">';
    foreach ($sections[$section]['fields'] as $field => $spec) {
        $out .= $renderField($section, $i, $field, $spec, $data);
    }

    return $out . '</div></div>';
};

/** A repeater block: existing rows + hidden template + add button. */
$repeater = static function (string $section, string $when, string $addLabel, string $emptyHint, bool $minOne = false) use ($sections, $values, $renderRow, $err): string {
    $rows = $values['entries'][$section] ?? [];
    if ($rows === [] && $minOne) {
        $rows = [[]];
    }
    $out = '<div class="cf-repeat" data-repeat="' . $section . '" data-max="' . (int) $sections[$section]['max'] . '" data-next="' . count($rows) . '"' . ($minOne ? ' data-min="1"' : '') . ($when !== '' ? ' data-when="' . $when . '"' : '') . '>';
    $out .= $err($section);
    $out .= '<div data-rows>';
    foreach (array_values($rows) as $i => $row) {
        $out .= $renderRow($section, $i, $row);
    }
    $out .= '</div><p class="cf-hint" data-empty' . ($rows === [] ? '' : ' hidden') . '>' . esc($emptyHint) . '</p>';
    $out .= '<template>' . $renderRow($section, '__i__', []) . '</template>';
    $out .= '<button type="button" class="btn btn-secondary" data-add-row>+ ' . esc($addLabel) . '</button></div>';

    return $out;
};
?>
<style>
    .cf-steps { list-style: none; display: flex; gap: 4px; margin: 0 0 8px; padding: 0; overflow-x: auto; }
    .cf-steps li { flex: 1 0 auto; }
    .cf-steps button { width: 100%; background: transparent; border: 0; border-bottom: 3px solid var(--border); padding: 8px 6px; font: inherit; font-size: .74rem; color: var(--muted); cursor: pointer; white-space: nowrap; }
    .cf-steps button .n { display: inline-block; width: 20px; height: 20px; line-height: 20px; border-radius: 50%; border: 1.5px solid var(--border); margin-right: 4px; font-weight: 700; font-size: .68rem; }
    .cf-steps li.active button { color: var(--ink); border-bottom-color: var(--accent); font-weight: 700; }
    .cf-steps li.active .n { border-color: var(--accent); color: var(--accent); }
    .cf-steps li.done button { border-bottom-color: var(--success); color: var(--ink); }
    .cf-steps li.done .n { background: var(--success); border-color: var(--success); color: #fff; }
    .cf-bar { height: 6px; background: var(--border); border-radius: 4px; overflow: hidden; margin-bottom: 4px; }
    .cf-bar > span { display: block; height: 100%; background: var(--success); width: 0; transition: width .25s; }
    .cf-barlabel { font-size: .76rem; color: var(--muted); margin-bottom: 14px; display: flex; justify-content: space-between; gap: 8px; flex-wrap: wrap; }
    .cf-step { display: none; }
    .cf-step.active { display: block; }
    .cf-step h3 { font-size: 1rem; margin: 0 0 2px; }
    .cf-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 14px; }
    .cf-field { min-width: 0; }
    .cf-wide, .cf-full { grid-column: 1 / -1; }
    .cf-field .req { color: var(--accent); }
    .cf-step input[type="text"], .cf-step input[type="number"], .cf-step input[type="date"], .cf-step input[type="url"], .cf-step select, .cf-step textarea { width: 100%; padding: 9px 11px; border: 1px solid var(--border); border-radius: 6px; font-size: .9rem; font-family: inherit; background: #fff; color: var(--ink); }
    .cf-step input:disabled { background: #f4f5f7; color: var(--muted); }
    .cf-field.has-error input, .cf-field.has-error select, .cf-field.has-error textarea { border-color: #dc2626; }
    .cf-err { color: #b91c1c; font-size: .78rem; margin-top: 3px; }
    .cf-hint { color: var(--muted); font-size: .8rem; margin: 4px 0 8px; }
    .cf-help { color: var(--muted); font-size: .76rem; margin-top: 2px; }
    .cf-row { border: 1px solid var(--border); border-radius: 8px; padding: 12px 14px 4px; margin-bottom: 12px; background: #fcfcfd; }
    .cf-row-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2px; font-size: .86rem; }
    .cf-link { background: none; border: 0; color: var(--accent); cursor: pointer; font: inherit; font-size: .8rem; padding: 2px 4px; }
    .cf-check label { display: flex; align-items: center; gap: 8px; font-weight: 500; margin-top: 26px; }
    .cf-check input { width: auto; }
    .cf-type { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 4px; }
    .cf-type label { flex: 1 1 200px; display: flex; gap: 8px; align-items: flex-start; border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px; cursor: pointer; margin: 0; font-weight: 600; }
    .cf-type input { margin-top: 3px; width: auto; }
    .cf-type small { display: block; font-weight: 400; color: var(--muted); }
    .cf-nav { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 18px; padding-top: 14px; border-top: 1px solid var(--border); }
    .cf-nav .grow { flex: 1; }
    .cf-summary-sec { border: 1px solid var(--border); border-radius: 8px; padding: 10px 14px; margin-bottom: 10px; }
    .cf-summary-sec h4 { margin: 0 0 6px; font-size: .86rem; display: flex; justify-content: space-between; }
    .cf-summary-sec dl { margin: 0; display: grid; grid-template-columns: 170px 1fr; gap: 4px 12px; font-size: .84rem; }
    .cf-summary-sec dt { color: var(--muted); }
    .cf-summary-sec dd { margin: 0; white-space: pre-wrap; word-break: break-word; }
    .cf-summary-sec .sub { margin: 8px 0 2px; font-weight: 700; font-size: .8rem; }
    .cf-missing { background: #fef3c7; border: 1px solid #fde68a; border-radius: 8px; padding: 10px 14px; font-size: .84rem; margin-bottom: 12px; }
    .cf-missing ul { margin: 6px 0 0 18px; padding: 0; }
    .cf-errbox { background: #fee2e2; color: #7f1d1d; border-radius: 8px; padding: 10px 14px; font-size: .84rem; margin-bottom: 14px; }
    .cf-errbox ul { margin: 6px 0 0 18px; padding: 0; }
    .cf-doc { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid var(--border); font-size: .86rem; }
    .cf-doc .meta { color: var(--muted); font-size: .76rem; }
    .cf-upload-status { font-size: .82rem; margin-top: 8px; min-height: 1.2em; }
    .cf-upload-status.ok { color: #166534; }
    .cf-upload-status.bad { color: #b91c1c; }
    [data-when][hidden] { display: none !important; }
    @media (max-width: 640px) { .cf-grid { grid-template-columns: 1fr; } .cf-summary-sec dl { grid-template-columns: 1fr; } .cf-steps button .t { display: none; } .cf-steps li.active button .t { display: inline; } }
</style>

<div class="card">
    <h2>Candidate Details</h2>
    <p class="sub">Application reference <strong><?= esc($reference) ?></strong> · complete each step, save your progress at any time, and submit when everything is ready.</p>

    <?php if ($record['verification_status'] === 'correction_needed' && $record['verification_notes']): ?>
        <div class="note-box"><strong>HR requested changes:</strong> <?= nl2br(esc($record['verification_notes'])) ?><br><span style="color:var(--muted)">Your earlier answers are saved below — update them and submit again.</span></div>
    <?php endif; ?>

    <?php if ($errors !== []): ?>
        <?php
        $bySection = [];
        foreach ($errors as $path => $message) {
            $bySection[CandidateFormService::errorSection($path)][] = $message;
        }
        ?>
        <div class="cf-errbox" role="alert" id="cf-errbox">
            <strong>Please review the highlighted items:</strong>
            <ul><?php foreach ($bySection as $sectionName => $messages): ?><li><?= esc($sectionName) ?> — <?= esc(count($messages) === 1 ? $messages[0] : count($messages) . ' items need attention') ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <ol class="cf-steps" id="cf-steps" aria-label="Form progress">
        <?php foreach ($stepNames as $i => $name): ?>
            <li data-step-tab="<?= $i ?>"><button type="button"><span class="n"><?= $i + 1 ?></span><span class="t"><?= esc($name) ?></span></button></li>
        <?php endforeach; ?>
    </ol>
    <div class="cf-bar" aria-hidden="true"><span id="cf-bar"></span></div>
    <div class="cf-barlabel"><span id="cf-bar-text">Step 1 of <?= count($stepNames) ?></span><span id="cf-saved-note"><?php if (! empty($stored['profile']['last_saved_at'])): ?>Last saved <?= esc(erp_datetime($stored['profile']['last_saved_at'])) ?><?php endif; ?></span></div>

    <form id="cf" method="post" action="<?= $base ?>/verify" autocomplete="on" novalidate data-upload-url="<?= $base ?>/documents" data-delete-url="<?= $base ?>/documents/__id__/delete" data-start="<?= $startAt ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="current_step" id="cf-current" value="<?= $startAt ?>">

        <!-- 1. Application -->
        <section class="cf-step" data-step="0">
            <h3>Application</h3>
            <p class="sub">These details come from HR and cannot be changed here. Tell us whether you are a fresher or have work experience.</p>
            <div class="meta-row"><span class="k">Position applied for</span><span><?= esc($record['designation'] ?: '—') ?></span></div>
            <div class="meta-row"><span class="k">Department</span><span><?= esc($record['department_name'] ?: '—') ?></span></div>
            <div class="meta-row"><span class="k">Company</span><span><?= esc($record['company_name']) ?></span></div>
            <div class="meta-row"><span class="k">Reference number</span><span><?= esc($reference) ?></span></div>
            <?php $overview = trim((string) ($record['job_description'] ?? '')) !== '' ? $record['job_description'] : ($group['description'] ?? ''); ?>
            <?php if ($overview): ?>
                <div class="note-box" style="background:#eef2ff;border-color:#c7d2fe;margin-top:12px"><strong>About this role</strong><br><?= nl2br(esc($overview)) ?></div>
            <?php endif; ?>
            <div class="cf-field<?= $has('candidate_type') ?>" style="margin-top:14px">
                <label>Are you a fresher or experienced? <span class="req">*</span></label>
                <div class="cf-type">
                    <label><input type="radio" name="candidate_type" value="fresher" required <?= $values['candidate_type'] === 'fresher' ? 'checked' : '' ?>><span>Fresher<small>Recently graduated or no full-time work experience</small></span></label>
                    <label><input type="radio" name="candidate_type" value="experienced" required <?= $values['candidate_type'] === 'experienced' ? 'checked' : '' ?>><span>Experienced<small>I have previous full-time employment</small></span></label>
                </div>
                <?= $err('candidate_type') ?>
            </div>
        </section>

        <!-- 2. Personal & contact -->
        <section class="cf-step" data-step="1">
            <h3>Personal &amp; contact</h3>
            <p class="sub">We only ask for what HR needs to evaluate and contact you.</p>
            <div class="cf-grid">
                <div class="cf-field"><label for="candidate_name">Full name</label><input id="candidate_name" type="text" value="<?= esc($record['candidate_name']) ?>" disabled></div>
                <div class="cf-field"><label for="candidate_email">Email</label><input id="candidate_email" type="text" value="<?= esc($record['candidate_email']) ?>" disabled></div>
                <div class="cf-field<?= $has('candidate_phone') ?>"><label for="candidate_phone">Contact number <span class="req">*</span></label><input id="candidate_phone" type="text" name="candidate_phone" maxlength="10" inputmode="numeric" pattern="[0-9]{10}" value="<?= $v('candidate_phone') ?>" required><div class="cf-help">10 digits, no spaces or country code.</div><?= $err('candidate_phone') ?></div>
                <div class="cf-field<?= $has('date_of_birth') ?>"><label for="date_of_birth">Date of birth</label><input id="date_of_birth" type="date" name="date_of_birth" max="<?= date('Y-m-d', strtotime('-16 years')) ?>" value="<?= $v('date_of_birth') ?>"><?= $err('date_of_birth') ?></div>
            </div>
            <h3 style="margin-top:12px">Current address</h3>
            <div class="cf-grid">
                <div class="cf-field cf-full<?= $has('address_line1') ?>"><label for="address_line1">Address line 1 <span class="req">*</span></label><input id="address_line1" type="text" name="address_line1" maxlength="190" value="<?= $v('address_line1') ?>" required><?= $err('address_line1') ?></div>
                <div class="cf-field cf-full<?= $has('address_line2') ?>"><label for="address_line2">Address line 2</label><input id="address_line2" type="text" name="address_line2" maxlength="190" value="<?= $v('address_line2') ?>"><?= $err('address_line2') ?></div>
                <div class="cf-field<?= $has('city') ?>"><label for="city">City <span class="req">*</span></label><input id="city" type="text" name="city" maxlength="100" value="<?= $v('city') ?>" required><?= $err('city') ?></div>
                <div class="cf-field<?= $has('state') ?>"><label for="state">State / province</label><input id="state" type="text" name="state" maxlength="100" value="<?= $v('state') ?>"><?= $err('state') ?></div>
                <div class="cf-field<?= $has('postal_code') ?>"><label for="postal_code">Postal code</label><input id="postal_code" type="text" name="postal_code" maxlength="20" value="<?= $v('postal_code') ?>"><?= $err('postal_code') ?></div>
                <div class="cf-field<?= $has('country') ?>"><label for="country">Country</label><input id="country" type="text" name="country" maxlength="100" value="<?= $v('country') ?>"><?= $err('country') ?></div>
            </div>
            <h3 style="margin-top:12px">Permanent address</h3>
            <div class="cf-field cf-check"><label style="margin-top:6px"><input type="checkbox" name="permanent_same_as_current" id="perm_same" value="1" <?= $values['permanent_same_as_current'] === '1' ? 'checked' : '' ?>> Same as current address</label></div>
            <div class="cf-field<?= $has('permanent_address') ?>" id="perm_wrap"><label for="permanent_address">Permanent address <span class="req">*</span></label><textarea id="permanent_address" name="permanent_address" rows="3" maxlength="500" data-req-unless="perm_same"><?= $v('permanent_address') ?></textarea><?= $err('permanent_address') ?></div>
        </section>

        <!-- 3. Education -->
        <section class="cf-step" data-step="2">
            <h3>Education</h3>
            <p class="sub">List your qualifications, <strong>highest first</strong>. Add one entry per degree or diploma.</p>
            <?= $repeater('education', '', 'Add another qualification', 'Add at least one qualification.', true) ?>
            <h3 style="margin-top:18px">Certifications</h3>
            <p class="sub">Relevant professional certifications or courses (optional). Upload the certificates themselves in the Documents step.</p>
            <?= $repeater('certification', '', 'Add a certification', 'No certifications added.') ?>
        </section>

        <!-- 4. Skills & role assessment -->
        <section class="cf-step" data-step="3">
            <h3>Skills &amp; assessment</h3>
            <p class="sub">Questions for the <strong><?= esc($record['designation'] ?: 'this') ?></strong> role (<?= esc($group['name']) ?>).</p>
            <?php foreach ($questions as $q): ?>
                <?php
                $key      = $q['question_key'];
                $path     = 'answers.' . $key;
                $answer   = (string) ($values['answers'][$key] ?? '');
                $qid      = 'ans_' . preg_replace('/[^a-z0-9_]/i', '_', $key);
                $required = (int) $q['is_required'] === 1;
                $when     = $q['applies_to'] === 'all' ? '' : ' data-when="' . esc($q['applies_to']) . '"';
                $attr     = ' name="answers[' . esc($key) . ']" id="' . $qid . '"' . ($required ? ' required' : '');
                ?>
                <div class="cf-field<?= $has($path) ?>"<?= $when ?> style="margin-bottom:12px">
                    <label for="<?= $qid ?>"><?= esc($q['label']) ?><?= $required ? ' <span class="req">*</span>' : '' ?></label>
                    <?php if ($q['field_type'] === 'textarea'): ?>
                        <textarea<?= $attr ?> rows="3" maxlength="2000"><?= esc($answer) ?></textarea>
                    <?php elseif ($q['field_type'] === 'select'): ?>
                        <select<?= $attr ?>><option value="">Select…</option>
                            <?php foreach (array_filter(array_map('trim', preg_split('/\R/', (string) $q['options']) ?: [])) as $option): ?><option value="<?= esc($option) ?>" <?= $answer === $option ? 'selected' : '' ?>><?= esc($option) ?></option><?php endforeach; ?>
                        </select>
                    <?php elseif ($q['field_type'] === 'yes_no'): ?>
                        <select<?= $attr ?>><option value="">Select…</option><option value="Yes" <?= $answer === 'Yes' ? 'selected' : '' ?>>Yes</option><option value="No" <?= $answer === 'No' ? 'selected' : '' ?>>No</option></select>
                    <?php elseif ($q['field_type'] === 'number'): ?>
                        <input type="number"<?= $attr ?> min="0" step="any" value="<?= esc($answer) ?>">
                    <?php else: ?>
                        <input type="text"<?= $attr ?> maxlength="<?= $q['field_type'] === 'tags' ? 600 : 255 ?>" value="<?= esc($answer) ?>"<?= $q['field_type'] === 'url' ? ' inputmode="url" placeholder="https://"' : '' ?>>
                    <?php endif; ?>
                    <?php if ($q['help_text'] || $q['field_type'] === 'tags'): ?><div class="cf-help"><?= esc($q['help_text'] ?: '') ?><?= $q['field_type'] === 'tags' ? ($q['help_text'] ? ' ' : '') . 'Separate items with commas.' : '' ?></div><?php endif; ?>
                    <?= $err($path) ?>
                </div>
            <?php endforeach; ?>
            <div class="cf-field<?= $has('profile_summary') ?>"><label for="profile_summary">Anything else that supports your application? <span class="cf-help" style="display:inline">(optional)</span></label><textarea id="profile_summary" name="profile_summary" rows="3" maxlength="1500"><?= $v('profile_summary') ?></textarea><?= $err('profile_summary') ?></div>
            <div class="cf-grid" style="margin-top:8px">
                <?php if ($hasLinks): ?>
                    <div class="cf-field<?= $has('github_url') ?>"><label for="github_url">GitHub profile</label><input id="github_url" type="text" name="github_url" maxlength="255" inputmode="url" placeholder="https://github.com/…" value="<?= $v('github_url') ?>"><?= $err('github_url') ?></div>
                    <div class="cf-field<?= $has('portfolio_url') ?>"><label for="portfolio_url">Portfolio / website</label><input id="portfolio_url" type="text" name="portfolio_url" maxlength="255" inputmode="url" placeholder="https://" value="<?= $v('portfolio_url') ?>"><?= $err('portfolio_url') ?></div>
                <?php endif; ?>
                <div class="cf-field<?= $has('linkedin_url') ?>"><label for="linkedin_url">LinkedIn profile</label><input id="linkedin_url" type="text" name="linkedin_url" maxlength="255" inputmode="url" placeholder="https://linkedin.com/in/…" value="<?= $v('linkedin_url') ?>"><?= $err('linkedin_url') ?></div>
            </div>
        </section>

        <!-- 5. Experience & projects -->
        <section class="cf-step" data-step="4">
            <h3>Experience &amp; projects</h3>
            <p class="cf-hint" data-when-none>Choose Fresher or Experienced in the first step to see the relevant questions.</p>

            <div data-when="experienced">
                <p class="sub">Add each previous employer, most recent first.</p>
                <?= $repeater('experience', '', 'Add another employer', 'Add at least one previous employer.', true) ?>
                <div class="cf-field<?= $has('notice_period') ?>" style="margin-top:10px"><label for="notice_period">Notice period</label><input id="notice_period" type="text" name="notice_period" maxlength="60" placeholder="e.g. 30 days, immediate" value="<?= $v('notice_period') ?>"><?= $err('notice_period') ?></div>
            </div>

            <div data-when="fresher">
                <div class="note-box" style="background:#eef2ff;border-color:#c7d2fe">Employment history is <strong>not applicable</strong> for freshers. Tell us about internships, training and projects instead.</div>
                <h3>Internships &amp; practical experience</h3>
                <?= $repeater('internship', '', 'Add an internship', 'No internships added (optional).') ?>
            </div>

            <div data-when="<?= $hasProj ? 'fresher,experienced' : 'fresher' ?>">
                <h3 style="margin-top:14px">Projects</h3>
                <p class="sub">Include your title, what you built, the technologies and tools used, and <strong>your own contribution</strong>.</p>
                <?= $repeater('project', '', 'Add a project', 'No projects added (optional).') ?>
            </div>
        </section>

        <!-- 6. Documents -->
        <section class="cf-step" data-step="5">
            <h3>Resume &amp; documents</h3>
            <p class="sub">Your <strong>resume is required</strong>. Add any certificates or proofs that support your application. Files are stored securely and visible only to HR.</p>
            <?= $err('documents') ?>
            <div class="cf-grid">
                <div class="cf-field"><label for="up_type">Document type</label>
                    <select id="up_type">
                        <?php foreach ($documentTypes as $type => $def): ?><option value="<?= esc($type) ?>" data-formats="<?= esc(implode(',', $def['formats'])) ?>"><?= esc($def['label']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="cf-field"><label for="up_file">File</label><input id="up_file" type="file"><div class="cf-help" id="up_help">PDF or DOCX, up to 10 MB.</div></div>
            </div>
            <button type="button" class="btn btn-secondary" id="up_btn">Upload</button>
            <div class="cf-upload-status" id="up_status" role="status" aria-live="polite"></div>
            <div id="doc_list" style="margin-top:10px" data-documents='<?= esc(json_encode(array_map(static fn (array $d) => ['id' => (int) $d['id'], 'type' => (string) $d['document_type'], 'name' => (string) $d['original_name'], 'size' => (int) $d['file_size']], $documents)), 'attr') ?>'></div>
        </section>

        <!-- 7. Review & submit -->
        <section class="cf-step" data-step="6">
            <h3>Review &amp; submit</h3>
            <p class="sub">Check your answers. You can go back to any step to change them.</p>
            <div id="cf-missing" class="cf-missing" hidden></div>
            <div id="cf-summary"></div>
            <div class="cf-field cf-check<?= $has('confirm_accuracy') ?>"><label style="margin-top:10px"><input type="checkbox" name="confirm_accuracy" id="cf-confirm" value="1"> I confirm that the information I have provided is accurate and complete.</label><?= $err('confirm_accuracy') ?></div>
            <div class="cf-field" style="margin-top:8px"><label for="notes">Note for HR (optional)</label><textarea id="notes" name="notes" rows="2" maxlength="2000"><?= $v('notes') ?></textarea></div>
        </section>

        <div class="cf-nav">
            <button type="button" class="btn btn-secondary" id="cf-back">← Back</button>
            <button type="submit" class="btn btn-secondary" formaction="<?= $base ?>/save" id="cf-save" title="Keep what you have entered and continue later from this link">Save progress</button>
            <span class="grow"></span>
            <button type="button" class="btn btn-primary" id="cf-next">Next →</button>
            <button type="submit" class="btn btn-primary" id="cf-submit" hidden>Submit for HR review</button>
        </div>
    </form>
</div>

<script>
(function () {
    var form = document.getElementById('cf');
    if (!form) { return; }
    var steps = [].slice.call(form.querySelectorAll('.cf-step'));
    var tabs = [].slice.call(document.querySelectorAll('#cf-steps li'));
    var names = <?= json_encode($stepNames) ?>;
    var current = parseInt(form.getAttribute('data-start'), 10) || 0;
    var dirty = false, submitting = false;

    function type() { var c = form.querySelector('input[name="candidate_type"]:checked'); return c ? c.value : ''; }

    // ---- conditional blocks ([data-when]) ------------------------------------
    function applyWhen() {
        var t = type();
        [].forEach.call(form.querySelectorAll('[data-when]'), function (el) {
            var ok = t !== '' && el.getAttribute('data-when').split(',').indexOf(t) !== -1;
            if (el.tagName === 'DIV' || el.tagName === 'SECTION') { el.hidden = !ok; }
            [].forEach.call(el.querySelectorAll('input,select,textarea'), function (c) { c.disabled = !ok && !c.hasAttribute('data-keep'); });
        });
        var none = form.querySelector('[data-when-none]'); if (none) { none.hidden = t !== ''; }
        var permWrap = document.getElementById('perm_wrap'), same = document.getElementById('perm_same');
        if (permWrap && same) { permWrap.hidden = same.checked; document.getElementById('permanent_address').disabled = same.checked; }
        [].forEach.call(form.querySelectorAll('[data-toggle-current]'), function (cb) {
            var row = cb.closest('[data-row]'), to = row && row.querySelector('[data-end-date]');
            if (to) { to.disabled = cb.checked || cb.disabled; if (cb.checked) { to.value = ''; } }
        });
    }

    // ---- repeaters --------------------------------------------------------------------
    function renumber(rep) {
        [].forEach.call(rep.querySelectorAll('[data-row]'), function (row, i) { var n = row.querySelector('[data-row-number]'); if (n) { n.textContent = i + 1; } });
        var empty = rep.querySelector('[data-empty]'); if (empty) { empty.hidden = rep.querySelectorAll('[data-row]').length > 0; }
        var add = rep.querySelector('[data-add-row]'); if (add) { add.hidden = rep.querySelectorAll('[data-row]').length >= parseInt(rep.getAttribute('data-max'), 10); }
    }
    [].forEach.call(form.querySelectorAll('[data-repeat]'), function (rep) {
        renumber(rep);
        rep.addEventListener('click', function (e) {
            if (e.target.matches('[data-add-row]')) {
                var i = parseInt(rep.getAttribute('data-next'), 10); rep.setAttribute('data-next', i + 1);
                var html = rep.querySelector('template').innerHTML.replace(/__i__/g, i);
                rep.querySelector('[data-rows]').insertAdjacentHTML('beforeend', html);
                renumber(rep); applyWhen(); dirty = true;
                var first = rep.querySelector('[data-rows] [data-row]:last-child input,[data-rows] [data-row]:last-child textarea'); if (first) { first.focus(); }
            } else if (e.target.matches('[data-remove-row]')) {
                e.target.closest('[data-row]').remove(); renumber(rep); dirty = true; refresh();
            }
        });
    });

    // ---- completeness -----------------------------------------------------------------
    function active(el) { return !el.disabled && !el.closest('[hidden]') && !el.closest('template'); }
    function stepIssues(step) {
        var issues = [], seen = {};
        [].forEach.call(steps[step].querySelectorAll('input,select,textarea'), function (c) {
            if (!active(c) || c.type === 'file') { return; }
            var need = c.required;
            if (c.hasAttribute('data-req-unless')) { var u = document.getElementById(c.getAttribute('data-req-unless')); need = !(u && u.checked); }
            if (!need) { return; }
            if (blankOptionalRow(c)) { return; }
            if (c.type === 'radio') {
                if (seen[c.name]) { return; } seen[c.name] = true;
                if (!form.querySelector('input[name="' + c.name + '"]:checked')) { issues.push(label(c)); }
            } else if (c.value.trim() === '') { issues.push(label(c)); }
            else if (c.pattern && !new RegExp('^(?:' + c.pattern + ')$').test(c.value.trim())) { issues.push(label(c) + ' (invalid format)'); }
        });
        if (step === 5 && !hasResume()) { issues.push('Resume upload'); }
        return issues;
    }
    // An untouched extra row (the server drops it too) must not block submission; the only row of a
    // "at least one" list (education, employment) still counts.
    function blankOptionalRow(c) {
        var row = c.closest('[data-row]'); if (!row) { return false; }
        var rep = row.closest('[data-repeat]'), minOne = rep && rep.getAttribute('data-min') === '1';
        if (minOne && rep.querySelectorAll('[data-row]').length === 1) { return false; }
        return [].every.call(row.querySelectorAll('input,select,textarea'), function (i) { return i.type === 'checkbox' ? !i.checked : i.value.trim() === ''; });
    }
    function label(c) { var f = c.closest('.cf-field'), l = f && f.querySelector('label'); return l ? l.textContent.replace('*', '').trim() : (c.name || 'Field'); }
    function hasResume() { return docs.some(function (d) { return d.type === 'Resume'; }); }

    function refresh() {
        applyWhen();
        var done = 0;
        steps.forEach(function (s, i) {
            var ok = i === 0 ? type() !== '' : (i === 6 ? false : stepIssues(i).length === 0);
            if (i === 6) { ok = document.getElementById('cf-confirm').checked && allIssues().length === 0; }
            tabs[i].classList.toggle('done', ok && i !== current);
            tabs[i].classList.toggle('active', i === current);
            if (ok) { done++; }
        });
        var pct = Math.round(done / steps.length * 100);
        document.getElementById('cf-bar').style.width = pct + '%';
        document.getElementById('cf-bar-text').textContent = 'Step ' + (current + 1) + ' of ' + steps.length + ' · ' + names[current] + ' · ' + pct + '% complete';
    }
    function allIssues() {
        var out = [];
        for (var i = 0; i < 6; i++) {
            stepIssues(i).forEach(function (t) { out.push({ step: i, text: t }); });
        }
        return out;
    }

    // ---- navigation -------------------------------------------------------------------
    function show(n, quiet) {
        current = Math.max(0, Math.min(n, steps.length - 1));
        steps.forEach(function (s, i) { s.classList.toggle('active', i === current); });
        document.getElementById('cf-current').value = current;
        document.getElementById('cf-back').style.visibility = current === 0 ? 'hidden' : 'visible';
        document.getElementById('cf-next').hidden = current === steps.length - 1;
        document.getElementById('cf-submit').hidden = current !== steps.length - 1;
        if (current === steps.length - 1) { buildSummary(); }
        refresh();
        var top = document.getElementById('cf-steps'); if (!quiet && top && top.scrollIntoView) { top.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    }
    tabs.forEach(function (t, i) { t.querySelector('button').addEventListener('click', function () { show(i); }); });
    document.getElementById('cf-next').addEventListener('click', function () { show(current + 1); });
    document.getElementById('cf-back').addEventListener('click', function () { show(current - 1); });
    form.addEventListener('input', function () { dirty = true; refresh(); });
    form.addEventListener('change', function () { dirty = true; refresh(); });
    window.addEventListener('beforeunload', function (e) { if (dirty && !submitting) { e.preventDefault(); e.returnValue = ''; } });

    // ---- review summary (built with textContent only) -------------------------------------------
    function buildSummary() {
        var box = document.getElementById('cf-summary'), miss = document.getElementById('cf-missing');
        box.textContent = '';
        for (var i = 0; i < 6; i++) {
            var sec = document.createElement('div'); sec.className = 'cf-summary-sec';
            var h = document.createElement('h4'), t = document.createElement('span'), a = document.createElement('button');
            t.textContent = names[i]; a.type = 'button'; a.className = 'cf-link'; a.textContent = 'Edit'; (function (n) { a.addEventListener('click', function () { show(n); }); })(i);
            h.appendChild(t); h.appendChild(a); sec.appendChild(h);
            var dl = document.createElement('dl'), count = 0;
            if (i === 5) {
                docs.forEach(function (d) { add(dl, d.type, d.name); count++; });
            } else {
                [].forEach.call(steps[i].querySelectorAll('.cf-field,.cf-row-head'), function (f) {
                    if (f.classList.contains('cf-row-head')) {
                        if (!active(f)) { return; }
                        var s = document.createElement('div'); s.className = 'sub'; s.style.gridColumn = '1 / -1'; s.textContent = f.querySelector('strong').textContent.trim(); dl.appendChild(s); return;
                    }
                    if (f.closest('[hidden]')) { return; }
                    var c = f.querySelector('input,select,textarea'); if (!c || (c.disabled && c.id !== 'candidate_name' && c.id !== 'candidate_email')) { return; }
                    var val = '';
                    if (c.type === 'radio') { var r = f.querySelector('input:checked'); val = r ? r.value.charAt(0).toUpperCase() + r.value.slice(1) : ''; }
                    else if (c.type === 'checkbox') { val = c.checked ? 'Yes' : ''; }
                    else if (c.tagName === 'SELECT') { val = c.value ? c.options[c.selectedIndex].text : ''; }
                    else { val = c.value.trim(); }
                    var l = f.querySelector('label'); var name = l ? l.textContent.replace('*', '').trim() : '';
                    if (val !== '' && name !== '') { add(dl, name.split('\n')[0], val); count++; }
                });
            }
            if (count === 0) { add(dl, '', 'Nothing entered'); }
            sec.appendChild(dl); box.appendChild(sec);
        }
        var issues = allIssues();
        miss.hidden = issues.length === 0; miss.textContent = '';
        if (issues.length) {
            var p = document.createElement('strong'); p.textContent = 'Please complete these before submitting:'; miss.appendChild(p);
            var ul = document.createElement('ul');
            issues.forEach(function (it) { var li = document.createElement('li'), b = document.createElement('button'); b.type = 'button'; b.className = 'cf-link'; b.textContent = names[it.step] + ' — ' + it.text; b.addEventListener('click', function () { show(it.step); }); li.appendChild(b); ul.appendChild(li); });
            miss.appendChild(ul);
        }
    }
    function add(dl, k, v) { var dt = document.createElement('dt'), dd = document.createElement('dd'); dt.textContent = k; dd.textContent = v; dl.appendChild(dt); dl.appendChild(dd); }

    // ---- uploads ------------------------------------------------------------------------------------
    var docs = JSON.parse(document.getElementById('doc_list').getAttribute('data-documents') || '[]');
    var typeSel = document.getElementById('up_type'), fileIn = document.getElementById('up_file'), status = document.getElementById('up_status');
    function formats() { return typeSel.options[typeSel.selectedIndex].getAttribute('data-formats').split(','); }
    function syncAccept() {
        var f = formats();
        fileIn.setAttribute('accept', f.map(function (x) { return '.' + x; }).join(','));
        document.getElementById('up_help').textContent = f.filter(function (x) { return x !== 'jpeg'; }).join(', ').toUpperCase().replace('JPG', 'JPG') + ', up to 10 MB.';
    }
    typeSel.addEventListener('change', syncAccept); syncAccept();
    function renderDocs() {
        var list = document.getElementById('doc_list'); list.textContent = '';
        if (!docs.length) { var p = document.createElement('p'); p.className = 'cf-hint'; p.textContent = 'No documents uploaded yet.'; list.appendChild(p); }
        docs.forEach(function (d) {
            var row = document.createElement('div'); row.className = 'cf-doc';
            var left = document.createElement('div'), n = document.createElement('div'), m = document.createElement('div');
            n.textContent = d.name; m.className = 'meta'; m.textContent = d.type + ' · ' + (d.size / 1024 < 1024 ? Math.max(1, Math.round(d.size / 1024)) + ' KB' : (d.size / 1048576).toFixed(1) + ' MB') + ' · uploaded';
            left.appendChild(n); left.appendChild(m);
            var rm = document.createElement('button'); rm.type = 'button'; rm.className = 'cf-link'; rm.textContent = 'Remove';
            rm.addEventListener('click', function () { removeDoc(d); });
            row.appendChild(left); row.appendChild(rm); list.appendChild(row);
        });
        refresh();
    }
    function say(msg, ok) { status.textContent = msg; status.className = 'cf-upload-status ' + (ok ? 'ok' : 'bad'); }
    function csrf(fd) { var f = form.querySelector('input[type=hidden][name^="csrf"]'); if (f) { fd.append(f.name, f.value); } return f; }
    function afterResponse(r, f) { if (r.csrf && f) { [].forEach.call(document.querySelectorAll('input[type=hidden][name="' + f.name + '"]'), function (i) { i.value = r.csrf; }); } if (r.documents) { docs = r.documents; renderDocs(); } say(r.message || '', r.ok); }
    function send(url, fd, f) {
        return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
            .then(function (res) { return res.json().catch(function () { return { ok: false, message: 'The server could not process that request. Please try again.' }; }); })
            .then(function (r) { afterResponse(r, f); }).catch(function () { say('Network problem - please check your connection and try again.', false); });
    }
    document.getElementById('up_btn').addEventListener('click', function () {
        if (!fileIn.files.length) { say('Choose a file to upload.', false); return; }
        var file = fileIn.files[0], ext = file.name.split('.').pop().toLowerCase();
        if (formats().indexOf(ext) === -1) { say('This document type accepts ' + formats().join(', ').toUpperCase() + ' files only.', false); return; }
        if (file.size > 10 * 1024 * 1024) { say('The file is too large (maximum 10 MB).', false); return; }
        var fd = new FormData(); fd.append('document_type', typeSel.value); fd.append('candidate_document', file); var f = csrf(fd);
        say('Uploading…', true); this.disabled = true; var btn = this;
        send(form.getAttribute('data-upload-url'), fd, f).then(function () { btn.disabled = false; fileIn.value = ''; });
    });
    function removeDoc(d) {
        if (!confirm('Remove "' + d.name + '"?')) { return; }
        var fd = new FormData(); var f = csrf(fd);
        send(form.getAttribute('data-delete-url').replace('__id__', d.id), fd, f);
    }
    renderDocs();

    // ---- submit guards -------------------------------------------------------------------------------------
    form.addEventListener('submit', function (e) {
        var isSubmit = e.submitter && e.submitter.id === 'cf-submit';
        if (isSubmit) {
            var issues = allIssues();
            if (issues.length) { e.preventDefault(); show(steps.length - 1); status.textContent = ''; return; }
            if (!document.getElementById('cf-confirm').checked) { e.preventDefault(); document.getElementById('cf-confirm').focus(); alert('Please confirm that the information is accurate.'); return; }
        }
        // Disabled controls (inactive sections) are simply not posted. Block a second submit.
        submitting = true;
        [].forEach.call(form.querySelectorAll('#cf-save,#cf-submit'), function (b) { setTimeout(function () { b.disabled = true; }, 0); });
    });

    show(current, true);
})();
</script>
