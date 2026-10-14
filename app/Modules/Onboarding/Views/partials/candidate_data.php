<?php
/**
 * Read-only rendering of everything a candidate submitted. Shared by the
 * HR "Candidate Details" page (mode 'hr': documents get preview/download
 * links) and the candidate portal's post-submission summary (mode
 * 'candidate': documents are listed by name only). Self-contained markup
 * and CSS so it sits equally well in the AdminLTE layout and the
 * standalone portal page. Every value is escaped; links are only emitted
 * for http(s) URLs.
 *
 * @var array       $record
 * @var array       $stored    CandidateFormService::load()
 * @var array|null  $group
 * @var array       $documents
 * @var string      $mode      'hr' | 'candidate'
 * @var string|null $heading
 */

use App\Modules\Onboarding\Services\CandidateFormService;

$profile  = $stored['profile'] ?? null;
$entries  = $stored['entries'] ?? [];
$answers  = $stored['answers'] ?? [];
$isHr     = ($mode ?? 'candidate') === 'hr';
$e        = static fn (mixed $v): string => esc((string) $v);
$dash     = static fn (mixed $v): string => trim((string) $v) === '' ? '<span class="cd-none">—</span>' : esc((string) $v);
$link     = static function (?string $url): string {
    $url = trim((string) $url);
    if ($url === '' || ! preg_match('#^https?://#i', $url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
        return $url === '' ? '<span class="cd-none">—</span>' : esc($url);
    }

    return '<a href="' . esc($url, 'attr') . '" target="_blank" rel="noopener noreferrer nofollow">' . esc($url) . '</a>';
};
$dateOr   = static fn (?string $d): string => ($d === null || $d === '') ? '' : erp_date($d);
$row      = static fn (string $k, string $html): string => '<div class="cd-row"><div class="cd-k">' . esc($k) . '</div><div class="cd-v">' . $html . '</div></div>';
$long     = static fn (?string $t): string => trim((string) $t) === '' ? '<span class="cd-none">—</span>' : nl2br(esc((string) $t));
$type     = $profile['candidate_type'] ?? null;
$address  = implode(', ', array_filter([$record['address_line1'] ?? '', $record['address_line2'] ?? '', $record['city'] ?? '', $record['state'] ?? '', $record['postal_code'] ?? '', $record['country'] ?? '']));
$permanent = $profile === null ? '' : (! empty($profile['permanent_same_as_current']) ? 'Same as current address' : (string) ($profile['permanent_address'] ?? ''));
$submittedAt = $profile['submitted_at'] ?? $record['verification_submitted_at'] ?? null;
$sizeLabel = static fn (int $b): string => $b >= 1048576 ? number_format($b / 1048576, 1) . ' MB' : max(1, (int) round($b / 1024)) . ' KB';
?>
<style>
    .cd-sec { background: var(--sy-surface, #fff); border: 1px solid var(--sy-border, var(--border, #e5e9ef)); border-radius: 10px; padding: 16px 18px; margin-bottom: 14px; }
    .cd-sec h3 { font-size: .95rem; font-weight: 700; margin: 0 0 10px; padding-bottom: 8px; border-bottom: 1px solid var(--sy-border-soft, var(--border, #e5e9ef)); display: flex; justify-content: space-between; align-items: center; gap: 8px; }
    .cd-sec h3 small { font-weight: 500; color: var(--sy-muted, var(--muted, #6b7a90)); font-size: .75rem; }
    .cd-row { display: grid; grid-template-columns: 190px 1fr; gap: 12px; padding: 5px 0; font-size: .86rem; }
    .cd-k { color: var(--sy-muted, var(--muted, #6b7a90)); }
    .cd-v { min-width: 0; word-break: break-word; }
    .cd-none { color: var(--sy-muted, var(--muted, #94a3b8)); }
    .cd-entry { border: 1px solid var(--sy-border-soft, var(--border, #e5e9ef)); border-radius: 8px; padding: 10px 14px; margin-bottom: 10px; }
    .cd-entry .t { font-weight: 700; font-size: .88rem; }
    .cd-entry .s { color: var(--sy-muted, var(--muted, #6b7a90)); font-size: .8rem; margin-bottom: 4px; }
    .cd-doc { display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; padding: 7px 0; border-bottom: 1px solid var(--sy-border-soft, var(--border, #e5e9ef)); font-size: .86rem; }
    .cd-doc:last-child { border-bottom: 0; }
    .cd-doc .m { color: var(--sy-muted, var(--muted, #6b7a90)); font-size: .76rem; }
    .cd-tag { display: inline-block; background: rgba(37,99,235,.08); border-radius: 12px; padding: 2px 9px; margin: 2px 4px 2px 0; font-size: .78rem; }
    @media (max-width: 640px) { .cd-row { grid-template-columns: 1fr; gap: 0; } }
</style>

<?php if (! empty($heading)): ?><h2 style="font-size:1rem;margin:6px 0 10px"><?= esc($heading) ?></h2><?php endif; ?>

<!-- Application -->
<div class="cd-sec">
    <h3>Application</h3>
    <?= $row('Reference', $e(CandidateFormService::reference($record))) ?>
    <?= $row('Position', $dash($record['designation'] ?? '')) ?>
    <?= $row('Department', $dash($record['department_name'] ?? '')) ?>
    <?= $row('Company', $dash($record['company_name'] ?? '')) ?>
    <?= $row('Candidate type', $type ? $e(CandidateFormService::TYPES[$type] ?? $type) : '<span class="cd-none">Not specified</span>') ?>
    <?php if ($isHr): ?><?= $row('Question set used', $dash($profile['role_group_name'] ?? ($group['name'] ?? ''))) ?><?php endif; ?>
    <?php if (trim((string) ($record['job_description'] ?? '')) !== ''): ?><?= $row('Job description', $long($record['job_description'])) ?><?php endif; ?>
    <?= $row('Submitted', $submittedAt ? $e(erp_datetime($submittedAt)) . ($profile && (int) $profile['revision'] > 1 ? ' <span class="cd-none">(submission #' . (int) $profile['revision'] . ')</span>' : '') : '<span class="cd-none">Not submitted yet</span>') ?>
</div>

<!-- Personal & contact -->
<div class="cd-sec">
    <h3>Personal &amp; contact</h3>
    <?= $row('Full name', $e($record['candidate_name'])) ?>
    <?= $row('Email', $e($record['candidate_email'])) ?>
    <?= $row('Contact number', $dash($record['candidate_phone'] ?? '')) ?>
    <?= $row('Date of birth', $record['date_of_birth'] ? $e($dateOr($record['date_of_birth'])) : '<span class="cd-none">—</span>') ?>
    <?= $row('Current address', $dash($address)) ?>
    <?php if ($profile !== null): ?><?= $row('Permanent address', $dash($permanent)) ?><?php endif; ?>
    <?php if ($profile !== null && ! empty($profile['linkedin_url'])): ?><?= $row('LinkedIn', $link($profile['linkedin_url'])) ?><?php endif; ?>
    <?php if ($profile !== null && ! empty($profile['github_url'])): ?><?= $row('GitHub', $link($profile['github_url'])) ?><?php endif; ?>
    <?php if ($profile !== null && ! empty($profile['portfolio_url'])): ?><?= $row('Portfolio / website', $link($profile['portfolio_url'])) ?><?php endif; ?>
    <?php if ($type === 'experienced' && ! empty($profile['notice_period'])): ?><?= $row('Notice period', $e($profile['notice_period'])) ?><?php endif; ?>
</div>

<!-- Education -->
<div class="cd-sec">
    <h3>Education <small><?= count($entries['education'] ?? []) ?> record(s)</small></h3>
    <?php if (! empty($entries['education'])): ?>
        <?php foreach ($entries['education'] as $edu): ?>
            <div class="cd-entry">
                <div class="t"><?= $e(trim(($edu['degree'] ?? '') . (($edu['specialization'] ?? '') !== '' ? ' — ' . $edu['specialization'] : ''))) ?></div>
                <div class="s"><?= $e(implode(' · ', array_filter([$edu['institution'] ?? '', $edu['year'] ?? '', ($edu['grade'] ?? '') !== '' ? ((($edu['grade_type'] ?? '') ?: 'Grade') . ': ' . $edu['grade']) : '']))) ?></div>
            </div>
        <?php endforeach; ?>
    <?php elseif (! empty($record['highest_qualification'])): ?>
        <div class="cd-entry">
            <div class="t"><?= $e($record['highest_qualification']) ?></div>
            <div class="s"><?= $e(implode(' · ', array_filter([$record['institution'] ?? '', $record['graduation_year'] ?? '']))) ?></div>
        </div>
    <?php else: ?><span class="cd-none">No education details provided.</span><?php endif; ?>

    <?php if (! empty($entries['certification'])): ?>
        <div style="font-weight:700;font-size:.84rem;margin:12px 0 6px">Certifications</div>
        <?php foreach ($entries['certification'] as $cert): ?>
            <div class="cd-entry">
                <div class="t"><?= $e($cert['name'] ?? '') ?></div>
                <div class="s"><?= $e(implode(' · ', array_filter([$cert['issuer'] ?? '', $cert['year'] ?? '']))) ?></div>
                <?php if (! empty($cert['url'])): ?><div style="font-size:.82rem"><?= $link($cert['url']) ?></div><?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Role-specific skills -->
<?php if ($profile !== null): ?>
<div class="cd-sec">
    <h3>Skills &amp; role assessment <small><?= $e($profile['role_group_name'] ?? '') ?></small></h3>
    <?php if ($answers === []): ?>
        <span class="cd-none">No answers were provided.</span>
    <?php else: ?>
        <?php foreach ($answers as $a): ?>
            <?php if ($a['field_type'] === 'tags'): ?>
                <?php $tags = array_filter(array_map('trim', explode(',', (string) $a['answer']))); ?>
                <?= $row($a['question_label'], implode('', array_map(static fn (string $t) => '<span class="cd-tag">' . esc($t) . '</span>', $tags))) ?>
            <?php elseif ($a['field_type'] === 'url'): ?>
                <?= $row($a['question_label'], $link($a['answer'])) ?>
            <?php else: ?>
                <?= $row($a['question_label'], $long($a['answer'])) ?>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php if (! empty($profile['profile_summary'])): ?><?= $row('Additional information', $long($profile['profile_summary'])) ?><?php endif; ?>
</div>
<?php endif; ?>

<!-- Projects & internships -->
<?php if (! empty($entries['project']) || ! empty($entries['internship'])): ?>
<div class="cd-sec">
    <h3>Projects &amp; practical experience</h3>
    <?php foreach ($entries['project'] ?? [] as $p): ?>
        <div class="cd-entry">
            <div class="t"><?= $e($p['title'] ?? '') ?> <span class="cd-none" style="font-weight:400">· Project</span></div>
            <div class="s"><?= $e($p['technologies'] ?? '') ?></div>
            <div style="font-size:.84rem"><?= $long($p['description'] ?? '') ?></div>
            <?php if (! empty($p['contribution'])): ?><div style="font-size:.84rem;margin-top:4px"><strong>Contribution:</strong> <?= $long($p['contribution']) ?></div><?php endif; ?>
            <?php if (! empty($p['url'])): ?><div style="font-size:.82rem;margin-top:4px"><?= $link($p['url']) ?></div><?php endif; ?>
        </div>
    <?php endforeach; ?>
    <?php foreach ($entries['internship'] ?? [] as $i): ?>
        <div class="cd-entry">
            <div class="t"><?= $e(trim(($i['role'] ?? '') . ' — ' . ($i['organization'] ?? ''), ' —')) ?> <span class="cd-none" style="font-weight:400">· Internship</span></div>
            <div class="s"><?= $e(implode(' – ', array_filter([$dateOr($i['from'] ?? ''), $dateOr($i['to'] ?? '')]))) ?></div>
            <div style="font-size:.84rem"><?= $long($i['description'] ?? '') ?></div>
            <?php if (! empty($i['skills'])): ?><div style="font-size:.82rem;margin-top:4px"><strong>Skills / tools:</strong> <?= $e($i['skills']) ?></div><?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Employment -->
<?php if ($profile !== null): ?>
<div class="cd-sec">
    <h3>Employment history</h3>
    <?php if ($type === 'fresher'): ?>
        <span class="cd-none">Not applicable — candidate is a fresher.</span>
    <?php elseif (empty($entries['experience'])): ?>
        <span class="cd-none">No employment records.</span>
    <?php else: ?>
        <?php foreach ($entries['experience'] as $x): ?>
            <div class="cd-entry">
                <div class="t"><?= $e(trim(($x['designation'] ?? '') . ' — ' . ($x['employer'] ?? ''), ' —')) ?></div>
                <div class="s"><?= $e(($dateOr($x['from'] ?? '') ?: '?') . ' – ' . (($x['current'] ?? '') === '1' ? 'Present' : ($dateOr($x['to'] ?? '') ?: '?'))) ?></div>
                <div style="font-size:.84rem"><strong>Responsibilities:</strong> <?= $long($x['responsibilities'] ?? '') ?></div>
                <?php if (! empty($x['achievements'])): ?><div style="font-size:.84rem;margin-top:4px"><strong>Achievements:</strong> <?= $long($x['achievements']) ?></div><?php endif; ?>
                <?php if (! empty($x['skills'])): ?><div style="font-size:.82rem;margin-top:4px"><strong>Skills / tools:</strong> <?= $e($x['skills']) ?></div><?php endif; ?>
                <?php if (! empty($x['reason_for_leaving'])): ?><div style="font-size:.82rem;margin-top:4px"><strong>Reason for leaving:</strong> <?= $e($x['reason_for_leaving']) ?></div><?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Documents -->
<div class="cd-sec">
    <h3>Resume &amp; documents <small><?= count($documents) ?> file(s)</small></h3>
    <?php if ($documents === []): ?>
        <span class="cd-none">No documents uploaded.</span>
    <?php else: ?>
        <?php foreach ($documents as $d): ?>
            <?php
            $ext     = strtolower(pathinfo((string) $d['file_path'], PATHINFO_EXTENSION));
            $dl      = site_url('files/download?path=' . urlencode($d['file_path']));
            $canView = in_array($ext, ['pdf', 'png', 'jpg', 'jpeg'], true);
            ?>
            <div class="cd-doc">
                <div>
                    <div><strong><?= $e($d['document_type'] ?: 'Document') ?></strong> — <?= $e($d['original_name'] ?: $d['title']) ?></div>
                    <div class="m"><?= $sizeLabel((int) $d['file_size']) ?> · uploaded <?= $e(erp_datetime($d['created_at'])) ?></div>
                </div>
                <?php if ($isHr): ?>
                    <div>
                        <?php if ($canView): ?><a class="btn btn-sm btn-outline-secondary" href="<?= esc($dl . '&inline=1', 'attr') ?>" target="_blank" rel="noopener"><i class="fas fa-eye me-1"></i>Preview</a><?php endif; ?>
                        <a class="btn btn-sm btn-outline-primary" href="<?= esc($dl, 'attr') ?>"><i class="fas fa-download me-1"></i>Download</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
