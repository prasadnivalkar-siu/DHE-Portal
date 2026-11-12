<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="page-heading">
    <div><p class="eyebrow">Administration</p><h2>API vendors</h2><p>Manage vendor access, individual API keys, and permitted data categories.</p></div>
    <div class="d-flex flex-wrap gap-2"><?php if (auth()->user()->can('vendors.activity')): ?><a class="btn btn-outline-primary" href="<?= url_to('admin.vendors.activity') ?>">Activity dashboard</a><?php endif ?><?php if (auth()->user()->can('vendors.manage')): ?><a class="btn btn-primary" href="<?= url_to('admin.vendors.create') ?>">Create vendor</a><?php endif ?></div>
</div>
<section class="panel p-0 overflow-hidden">
    <div class="panel-header px-4 pt-4"><div><h3>Registered vendors</h3><p class="text-secondary mb-0">Each vendor has a current key and may have a previous key during rotation. Deactivate a vendor to suspend both keys. Times: <?= esc(date_default_timezone_get()) ?>.</p></div><span class="count-pill"><?= number_format($pager->getTotal('vendors')) ?> vendors</span></div>
    <?php if ($vendors === []): ?>
        <div class="empty-state"><h4>No vendors yet</h4><p>Create a vendor to generate their first API key.</p></div>
    <?php else: ?>
        <div class="table-responsive"><table class="table portal-table align-middle mb-0">
            <thead><tr><th>Vendor</th><th>Status</th><th>Categories</th><th>Key / generated</th><th>Requests / minute / category</th><th>Last API use</th><th class="text-end">Action</th></tr></thead>
            <tbody><?php foreach ($vendors as $vendor): ?>
                <tr>
                    <td><strong><?= esc($vendor['name']) ?></strong><small class="table-subtext"><?= esc($vendor['vendor_code']) ?></small><small class="table-subtext"><?= esc($vendor['contact_email'] ?? '') ?></small></td>
                    <td><span class="status <?= (int) $vendor['is_active'] === 1 ? 'status-completed' : 'status-failed' ?>"><?= (int) $vendor['is_active'] === 1 ? 'Active' : 'Inactive' ?></span></td>
                    <td><small class="d-block text-secondary">Consume</small><?php foreach (json_decode($vendor['allowed_categories'], true) ?? [] as $category): ?><span class="badge text-bg-light border me-1 mb-1"><?= esc($categories[$category] ?? $category) ?></span><?php endforeach ?><small class="d-block text-secondary">Submit</small><?php foreach (json_decode($vendor['ingestion_categories'] ?? '[]', true) ?? [] as $category): ?><span class="badge text-bg-primary me-1 mb-1"><?= esc($categories[$category] ?? $category) ?></span><?php endforeach ?><small class="d-block text-secondary mt-1">Institutions</small><?php if (($vendor['institution_access_mode'] ?? 'all') === 'all'): ?><span>All universities / institutions</span><?php else: ?><?php $scopeRules = json_decode($vendor['institution_access_rules'] ?? '[]', true) ?: []; ?><?php foreach (array_slice($scopeRules, 0, 3) as $scopeRule): ?><small class="d-block"><?= esc($scopeRule['university_code']) ?> / <?= esc($scopeRule['institution_code'] ?? 'All institutions') ?></small><?php endforeach ?><?php if (count($scopeRules) > 3): ?><small>+<?= count($scopeRules) - 3 ?> more rules</small><?php endif ?><?php if ($scopeRules === []): ?><small>No institutions permitted</small><?php endif ?><?php endif ?></td>
                    <td><code><?= esc($vendor['api_key_prefix']) ?>…</code><small class="table-subtext"><?= esc($vendor['key_generated_at']) ?></small><?= view('admin/vendors/key_status', ['vendor' => $vendor]) ?><?= view('admin/vendors/key_controls', ['vendor' => $vendor]) ?></td>
                    <td><?= number_format((int) $vendor['rate_limit_per_minute']) ?></td>
                    <td><?= esc($vendor['last_used_at'] ?? 'Never') ?></td>
                    <td class="text-end"><?php if (auth()->user()->can('vendors.manage')): ?><a class="btn btn-sm btn-outline-primary" href="<?= url_to('admin.vendors.edit', $vendor['id']) ?>">Manage</a><?php endif ?></td>
                </tr>
            <?php endforeach ?></tbody>
        </table></div>
        <div class="panel-footer"><?= $pager->links('vendors', 'bootstrap_full') ?></div>
    <?php endif ?>
</section>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js/vendor-keys.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/vendor-keys.js') ?>"></script>
<?= $this->endSection() ?>
