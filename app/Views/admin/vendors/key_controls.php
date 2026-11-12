<?php if (auth()->user()->can('vendors.keys.reveal')): ?>
<div data-vendor-key-control class="mt-2">
    <form method="post" action="<?= url_to('admin.vendors.reveal_key', $vendor['id']) ?>" data-reveal-form>
        <?= csrf_field() ?>
        <button class="btn btn-sm btn-outline-primary" type="submit" data-show-key>Show API key</button>
    </form>
    <div class="mt-2" data-key-panel hidden>
        <label class="form-label small mb-1" for="vendor-key-<?= (int) $vendor['id'] ?>">X-API-Key</label>
        <input class="form-control form-control-sm font-monospace" id="vendor-key-<?= (int) $vendor['id'] ?>" data-key-value readonly autocomplete="off" spellcheck="false" aria-label="Vendor API key">
        <button class="btn btn-sm btn-outline-secondary mt-2" type="button" data-copy-key>Copy key</button>
    </div>
    <small class="d-block mt-1" data-key-status aria-live="polite"></small>
</div>
<?php endif ?>
