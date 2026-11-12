<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php
$editing = $vendor !== null;
$selectedCategories = old('allowed_categories', $editing ? json_decode($vendor['allowed_categories'], true) : array_keys($categories));
$selectedCategories = is_array($selectedCategories) ? $selectedCategories : [];
$submitCategories = old('ingestion_categories', $editing ? json_decode($vendor['ingestion_categories'] ?? '[]', true) : []);
$submitCategories = is_array($submitCategories) ? $submitCategories : [];
?>
<div class="page-heading">
    <div><p class="eyebrow">Administration</p><h2><?= $editing ? 'Manage vendor' : 'Create API vendor' ?></h2><p><?= $editing ? esc($vendor['name']) : 'Generate a separate API key and configure access for a vendor.' ?></p></div>
    <div class="d-flex flex-wrap gap-2"><?php if ($editing && auth()->user()->can('vendors.activity')): ?><form method="post" action="<?= url_to('admin.vendors.activity.filter') ?>"><?= csrf_field() ?><input type="hidden" name="vendor_id" value="<?= (int) $vendor['id'] ?>"><input type="hidden" name="page" value="1"><button class="btn btn-outline-primary" type="submit">View activity</button></form><?php endif ?><a class="btn btn-outline-secondary" href="<?= url_to('admin.vendors') ?>">Back to vendors</a></div>
</div>
<?php if ($issuedKey): ?>
    <section class="panel border border-success mb-4" role="status">
        <h3>Your API key</h3><p>Copy this key for the vendor. Authorized administrators can display it again using Show API key.</p>
        <label class="form-label" for="issued-api-key">X-API-Key</label>
        <div class="input-group"><input class="form-control font-monospace" id="issued-api-key" value="<?= esc($issuedKey, 'attr') ?>" readonly autocomplete="off" spellcheck="false"><button class="btn btn-outline-primary" type="button" id="copy-api-key">Copy key</button></div>
        <small class="d-block mt-2" id="key-copy-status" aria-live="polite"></small>
    </section>
<?php endif ?>
<div class="row g-4">
    <div class="col-xl-8">
        <section class="panel">
            <form method="post" action="<?= $editing ? url_to('admin.vendors.update', $vendor['id']) : url_to('admin.vendors.store') ?>">
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label" for="vendor-code">Vendor code</label><input class="form-control" id="vendor-code" name="vendor_code" value="<?= $editing ? esc($vendor['vendor_code'], 'attr') : old('vendor_code') ?>" maxlength="100" pattern="[a-zA-Z0-9._-]+" <?= $editing ? 'readonly' : 'required' ?>><div class="form-text">Unique code using letters, numbers, periods, underscores, or hyphens. Cannot be changed after creation; delivery history uses this code.</div></div>
                    <div class="col-md-6"><label class="form-label" for="vendor-name">Vendor name</label><input class="form-control" id="vendor-name" name="name" value="<?= old('name', $vendor['name'] ?? '') ?>" maxlength="150" required></div>
                    <div class="col-md-6"><label class="form-label" for="contact-email">Contact email</label><input class="form-control" type="email" id="contact-email" name="contact_email" value="<?= old('contact_email', $vendor['contact_email'] ?? '') ?>" maxlength="254"></div>
                    <div class="col-md-3"><label class="form-label" for="vendor-status">Status</label><select class="form-select" id="vendor-status" name="is_active"><option value="1" <?= (string) old('is_active', $vendor['is_active'] ?? '1') === '1' ? 'selected' : '' ?>>Active</option><option value="0" <?= (string) old('is_active', $vendor['is_active'] ?? '1') === '0' ? 'selected' : '' ?>>Inactive</option></select></div>
                    <div class="col-md-3"><label class="form-label" for="rate-limit">Requests per minute</label><input class="form-control" type="number" id="rate-limit" name="rate_limit_per_minute" min="1" max="10000" value="<?= old('rate_limit_per_minute', $vendor['rate_limit_per_minute'] ?? '60') ?>" required><div class="form-text">Per data category.</div></div>
                    <?php if (! $editing): ?><div class="col-md-6"><label class="form-label" for="initial-key-expiry">API key expires at</label><input class="form-control" id="initial-key-expiry" type="datetime-local" step="1" name="api_key_expires_at" value="<?= esc(old('api_key_expires_at', date('Y-m-d\TH:i', strtotime('+' . \App\Libraries\VendorKeyPolicy::DEFAULT_LIFETIME_DAYS . ' days'))), 'attr') ?>"><div class="form-text"><?= esc(date_default_timezone_get()) ?>. Defaults to 90 days. Blank means no expiry.</div></div><?php endif ?>
                    <div class="col-12"><fieldset><legend class="form-label fs-6">Consume published data</legend><div class="d-flex flex-wrap gap-3"><?php foreach ($categories as $code => $label): ?><div class="form-check"><input class="form-check-input" type="checkbox" id="category-<?= esc($code) ?>" name="allowed_categories[]" value="<?= esc($code) ?>" <?= in_array($code, $selectedCategories, true) ? 'checked' : '' ?>><label class="form-check-label" for="category-<?= esc($code) ?>"><?= esc($label) ?></label></div><?php endforeach ?></div><div class="form-text">Allows retrieval and delivery acknowledgment for these categories.</div></fieldset></div>
                    <div class="col-12"><fieldset><legend class="form-label fs-6">Submit data for verification</legend><div class="d-flex flex-wrap gap-3"><?php foreach ($categories as $code => $label): ?><div class="form-check"><input class="form-check-input" type="checkbox" id="submit-<?= esc($code) ?>" name="ingestion_categories[]" value="<?= esc($code) ?>" <?= in_array($code, $submitCategories, true) ? 'checked' : '' ?>><label class="form-check-label" for="submit-<?= esc($code) ?>"><?= esc($label) ?></label></div><?php endforeach ?></div><div class="form-text">API submissions arrive as Draft. Select at least one permission across consume and submit.</div></fieldset></div>
                    <?= view('admin/vendors/institution_scope', ['vendor' => $vendor]) ?>
                    <div class="col-12"><label class="form-label" for="vendor-notes">Notes</label><textarea class="form-control" name="notes" id="vendor-notes" rows="3" maxlength="2000"><?= old('notes', $vendor['notes'] ?? '') ?></textarea></div>
                </div>
                <div class="form-actions"><a class="btn btn-outline-secondary" href="<?= url_to('admin.vendors') ?>">Cancel</a><button class="btn btn-primary" type="submit"><?= $editing ? 'Save vendor' : 'Create vendor & generate key' ?></button></div>
            </form>
        </section>
    </div>
    <div class="col-xl-4">
        <section class="panel mb-4"><h3>API integration</h3><p>Send the vendor's key in <code>X-API-Key</code> and use <code>Content-Type: application/json</code>.</p><p>The key identifies the vendor automatically. <code>Vendor_Code</code> and <code>X-Vendor-Code</code> are optional, but must match this vendor when supplied.</p><p class="mb-0">Delivery history, incremental checkpoints, acknowledgments, and request limits remain separate for each vendor.</p></section>
        <?php if ($editing): ?>
            <section class="panel"><h3>API key</h3><dl><dt>Current key prefix</dt><dd><code><?= esc($vendor['api_key_prefix']) ?>…</code><?= view('admin/vendors/key_status', ['vendor' => $vendor]) ?></dd><dt>Generated</dt><dd><?= esc($vendor['key_generated_at']) ?></dd><dt>Current key last used</dt><dd><?= esc($vendor['current_key_last_used_at'] ?? 'Not recorded') ?></dd><dt>Vendor last API use</dt><dd><?= esc($vendor['last_used_at'] ?? 'Never') ?></dd></dl>
                <div class="mb-4"><?= view('admin/vendors/key_controls', ['vendor' => $vendor]) ?></div>
                <form class="mb-4" method="post" action="<?= url_to('admin.vendors.key_expiry', $vendor['id']) ?>">
                    <?= csrf_field() ?><input type="hidden" name="expected_key_prefix" value="<?= esc($vendor['api_key_prefix'], 'attr') ?>">
                    <label class="form-label" for="current-key-expiry">Current key expires at</label><input class="form-control" id="current-key-expiry" type="datetime-local" step="1" name="api_key_expires_at" value="<?= esc(isset($vendor['api_key_expires_at']) ? str_replace(' ', 'T', $vendor['api_key_expires_at']) : '', 'attr') ?>">
                    <p class="form-text"><?= esc(date_default_timezone_get()) ?>. Blank means no expiry. Shortening this date also shortens an active overlap; extending it does not extend the overlap.</p>
                    <button class="btn btn-outline-primary" type="submit">Save expiry</button>
                </form>
                <?php if (! empty($vendor['previous_api_key_prefix'])): ?>
                    <div class="border rounded p-3 mb-4"><h4 class="h6">Previous key</h4><p class="mb-1"><code><?= esc($vendor['previous_api_key_prefix']) ?>…</code></p><p class="small mb-1">Accepted until <?= esc($vendor['previous_key_valid_until']) ?> (<?= esc(date_default_timezone_get()) ?>)</p><p class="small text-secondary">Last used: <?= esc($vendor['previous_key_last_used_at'] ?? 'Not recorded') ?></p>
                        <?php if (\App\Libraries\VendorKeyPolicy::usable($vendor['previous_key_valid_until'])): ?><form method="post" action="<?= url_to('admin.vendors.revoke_overlap', $vendor['id']) ?>"><?= csrf_field() ?><input type="hidden" name="expected_previous_key_prefix" value="<?= esc($vendor['previous_api_key_prefix'], 'attr') ?>"><button class="btn btn-sm btn-outline-danger" type="submit">End overlap now</button></form><?php endif ?>
                    </div>
                <?php endif ?>
                <form method="post" action="<?= url_to('admin.vendors.regenerate_key', $vendor['id']) ?>">
                    <?= csrf_field() ?><input type="hidden" name="expected_key_prefix" value="<?= esc($vendor['api_key_prefix'], 'attr') ?>">
                    <h4 class="h6">Rotate API key</h4>
                    <label class="form-label" for="replacement-key-expiry">New key expires at</label><input class="form-control mb-2" id="replacement-key-expiry" type="datetime-local" step="1" name="new_key_expires_at" value="<?= date('Y-m-d\TH:i', strtotime('+' . \App\Libraries\VendorKeyPolicy::DEFAULT_LIFETIME_DAYS . ' days')) ?>"><p class="form-text"><?= esc(date_default_timezone_get()) ?>. Blank means no expiry.</p>
                    <label class="form-label" for="key-overlap">Keep the previous key working for</label><select class="form-select mb-2" id="key-overlap" name="overlap_minutes"><?php foreach ([0 => 'No overlap — stop it immediately', 15 => '15 minutes', 60 => '1 hour', 360 => '6 hours', 1440 => '24 hours'] as $minutes => $label): ?><option value="<?= $minutes ?>" <?= $minutes === \App\Libraries\VendorKeyPolicy::DEFAULT_OVERLAP_MINUTES ? 'selected' : '' ?>><?= esc($label) ?></option><?php endforeach ?></select>
                    <p class="form-text">The overlap ends sooner if either key expires. An expired key stays expired. Rotating again ends any older overlap immediately.</p>
                    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="confirm_regenerate" id="confirm-regenerate" value="1" required><label class="form-check-label" for="confirm-regenerate">Generate a replacement key using the expiry and overlap above.</label></div>
                    <button class="btn btn-outline-danger" type="submit">Rotate API key</button>
                </form>
                <p class="small text-secondary mt-3 mb-0">Both keys use the vendor's current permissions, shared rate limits, delivery history, and checkpoints. Show API key displays the current key.</p>
            </section>
        <?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js/vendor-scopes.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/vendor-scopes.js') ?>"></script>
<script src="<?= base_url('assets/js/vendor-keys.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/vendor-keys.js') ?>"></script>
<script>
document.getElementById('copy-api-key')?.addEventListener('click', async function () {
    const input = document.getElementById('issued-api-key');
    const status = document.getElementById('key-copy-status');
    try {
        await navigator.clipboard.writeText(input.value);
        status.textContent = 'API key copied.';
    } catch (error) {
        input.focus(); input.select();
        status.textContent = 'Key selected. Press Ctrl+C to copy.';
    }
});
</script>
<?= $this->endSection() ?>
