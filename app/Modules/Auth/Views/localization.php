<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<p class="loc-sub">Set your preferred region, currency and date/time formats for the ERP.</p>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="loc-card">
            <div class="loc-card-head loc-card-head--tint">
                <span class="loc-ico"><i class="fas fa-eye"></i></span>
                <div><div class="loc-card-title">Current Display</div><div class="loc-card-sub">This is how your data will appear based on your settings.</div></div>
            </div>
            <?php
                $rows = [
                    ['fa-calendar-day', 'Date', $preview['date']],
                    ['fa-clock', 'Time', $preview['time']],
                    ['fa-calendar-check', 'Date &amp; time', $preview['datetime']],
                    ['fa-indian-rupee-sign', 'Amount', $preview['money']],
                    ['fa-hashtag', 'Number', $preview['number']],
                    ['fa-globe', 'Your timezone', $preview['timezone']],
                ];
            ?>
            <ul class="loc-preview">
                <?php foreach ($rows as [$icon, $label, $value]): ?>
                    <li>
                        <i class="fas <?= $icon ?>"></i>
                        <span class="loc-preview-label"><?= $label ?></span>
                        <span class="loc-preview-value"><?= esc($value) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="loc-note"><i class="fas fa-circle-info"></i><span>Users can override the timezone and locale on their own profile. Blank means the organization default.</span></div>
        </div>
    </div>

    <div class="col-lg-8">
        <form method="post" action="<?= site_url('auth/localization') ?>" class="loc-card loc-form">
            <?= csrf_field() ?>
            <div class="loc-form-body">
                <section class="loc-section">
                    <div class="loc-section-head"><span class="loc-sico"><i class="fas fa-location-dot"></i></span>
                        <div><div class="loc-section-title">Region</div><div class="loc-section-sub">Set your country/region and language preferences.</div></div></div>
                    <div class="loc-panel row g-3">
                        <div class="col-md-6">
                            <label class="form-label erp-req">Country / Region</label>
                            <select name="country_iso2" class="form-select" required>
                                <?php foreach ($countries as $c): ?>
                                    <option value="<?= esc($c['iso2']) ?>" <?= $values['country_iso2'] === $c['iso2'] ? 'selected' : '' ?>><?= esc($c['name']) ?> (<?= esc($c['iso2']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label erp-req">Locale</label>
                            <select name="locale" class="form-select" required>
                                <?php foreach ($locales as $code => $label): ?>
                                    <option value="<?= esc($code) ?>" <?= $values['locale'] === $code ? 'selected' : '' ?>><?= esc($label) ?> — <?= esc($code) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Controls number grouping and the currency symbol style.</div>
                        </div>
                    </div>
                </section>

                <section class="loc-section">
                    <div class="loc-section-head"><span class="loc-sico"><i class="fas fa-dollar-sign"></i></span>
                        <div><div class="loc-section-title">Currency</div><div class="loc-section-sub">Set the currency to be used for amounts and financial data.</div></div></div>
                    <div class="loc-panel row g-3">
                        <div class="col-md-6">
                            <label class="form-label erp-req">Currency</label>
                            <select name="currency_code" class="form-select" required>
                                <?php foreach ($currencies as $code => $label): ?>
                                    <option value="<?= esc($code) ?>" <?= $values['currency_code'] === $code ? 'selected' : '' ?>><?= esc($code) ?> — <?= esc($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </section>

                <section class="loc-section">
                    <div class="loc-section-head"><span class="loc-sico"><i class="fas fa-calendar-days"></i></span>
                        <div><div class="loc-section-title">Date &amp; Time</div><div class="loc-section-sub">Set the display format for dates, time and timezone.</div></div></div>
                    <div class="loc-panel row g-3">
                        <div class="col-md-5">
                            <label class="form-label erp-req">Organization timezone</label>
                            <select name="timezone" class="form-select" required>
                                <?php foreach ($timezones as $tz): ?>
                                    <option value="<?= esc($tz) ?>" <?= $values['timezone'] === $tz ? 'selected' : '' ?>><?= esc(str_replace('_', ' ', $tz)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label erp-req">Date format</label>
                            <select name="date_format" class="form-select" required>
                                <?php foreach ($dateFormats as $fmt => $label): ?>
                                    <option value="<?= esc($fmt) ?>" <?= $values['date_format'] === $fmt ? 'selected' : '' ?>><?= esc($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label erp-req">Time format</label>
                            <select name="time_format" class="form-select" required>
                                <?php foreach ($timeFormats as $fmt => $label): ?>
                                    <option value="<?= esc($fmt) ?>" <?= $values['time_format'] === $fmt ? 'selected' : '' ?>><?= esc($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12"><div class="form-text mt-0">New meetings are scheduled in this timezone. Stored times are converted for each viewer.</div></div>
                    </div>
                </section>
            </div>
            <div class="loc-form-foot">
                <a href="<?= site_url('auth/localization') ?>" class="btn btn-outline-secondary loc-btn"><i class="fas fa-rotate-left me-2"></i>Discard Changes</a>
                <button type="submit" class="btn btn-primary loc-btn"><i class="far fa-floppy-disk me-2"></i>Save Changes</button>
            </div>
        </form>
    </div>
</div>

<style>
.loc-sub { color: var(--sy-muted); margin: -.25rem 0 1.25rem; }
.loc-card { background: var(--sy-surface); border: 1px solid var(--sy-border-soft); border-radius: 16px; box-shadow: var(--sy-shadow-sm, 0 1px 3px rgba(16,24,40,.06)); overflow: hidden; height: auto; }
.loc-card-head { display: flex; gap: .75rem; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid var(--sy-border-soft); }
.loc-card-head--tint { background: var(--sy-accent-soft); }
.loc-ico, .loc-sico { flex: 0 0 auto; width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center; color: var(--sy-accent-ink); }
.loc-card-title { font-weight: 700; font-size: 1.02rem; }
.loc-card-sub, .loc-section-sub { font-size: .8rem; color: var(--sy-muted); }
.loc-preview { list-style: none; margin: 0; padding: 0 1.25rem; }
.loc-preview li { display: grid; grid-template-columns: 28px 1fr 1.4fr; gap: .75rem; align-items: center; padding: .85rem 0; border-bottom: 1px solid var(--sy-border-soft); }
.loc-preview li:last-child { border-bottom: 0; }
.loc-preview i { color: var(--sy-ink); text-align: center; }
.loc-preview-label { font-size: .88rem; color: var(--sy-ink); }
.loc-preview-value { font-weight: 600; font-variant-numeric: tabular-nums; word-break: break-word; }
.loc-note { display: flex; gap: .6rem; margin: .5rem 1.25rem 1.25rem; padding: .75rem .9rem; border-radius: 12px; background: var(--sy-info-soft); color: var(--sy-ink); font-size: .78rem; }
.loc-note i { color: var(--sy-info); margin-top: .15rem; }
.loc-form-body { padding: 1.25rem 1.5rem 0; }
.loc-section + .loc-section { margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid var(--sy-border-soft); }
.loc-section-head { display: flex; gap: .75rem; align-items: flex-start; margin-bottom: .75rem; }
.loc-section-title { font-weight: 700; font-size: 1.02rem; }
.loc-panel { margin: 0; padding: 1rem; background: var(--sy-subtle-bg); border: 1px solid var(--sy-border-soft); border-radius: 12px; }
.loc-panel .form-select { background-color: var(--sy-surface); }
.loc-form-foot { display: flex; justify-content: space-between; flex-wrap: wrap; gap: .75rem; margin-top: 1.5rem; padding: 1rem 1.5rem; border-top: 1px solid var(--sy-border-soft); }
.loc-btn { border-radius: 10px; font-weight: 600; min-width: 150px; }
@media (max-width: 575.98px) { .loc-btn { flex: 1 1 100%; } .loc-preview li { grid-template-columns: 24px 1fr; } .loc-preview-value { grid-column: 2; } }
</style>
<?= $this->endSection() ?>
