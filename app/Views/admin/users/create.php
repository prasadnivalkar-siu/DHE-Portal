<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="page-heading">
    <div><p class="eyebrow">Administration</p><h2>Create portal user</h2><p>Set up an authorized account and grant the minimum role needed.</p></div>
    <a class="btn btn-outline-secondary" href="<?= url_to('admin.users') ?>">Back to users</a>
</div>

<div class="row g-4">
    <div class="col-xl-8">
        <section class="panel">
            <form method="post" action="<?= url_to('admin.users.store') ?>">
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label" for="username">Username</label><input class="form-control" id="username" name="username" value="<?= old('username') ?>" maxlength="30" pattern="[a-zA-Z0-9.]+" required><div class="form-text">Letters, numbers, and periods only.</div></div>
                    <div class="col-md-6"><label class="form-label" for="email">Email address</label><input class="form-control" id="email" name="email" type="email" value="<?= old('email') ?>" required></div>
                    <div class="col-12"><label class="form-label" for="role">Role</label><select class="form-select" id="role" name="role" required><?php foreach ($roles as $key => $details): ?><option value="<?= esc($key) ?>" <?= old('role', 'viewer') === $key ? 'selected' : '' ?>><?= esc($details['title']) ?> — <?= esc($details['description']) ?></option><?php endforeach ?></select></div>
                    <div class="col-md-6"><label class="form-label" for="password">Temporary password</label><input class="form-control" id="password" name="password" type="password" minlength="12" autocomplete="new-password" required><div class="form-text">Use at least 12 characters. Shield also checks password strength.</div></div>
                    <div class="col-md-6"><label class="form-label" for="password_confirm">Confirm password</label><input class="form-control" id="password_confirm" name="password_confirm" type="password" minlength="12" autocomplete="new-password" required></div>
                </div>
                <div class="form-actions"><a class="btn btn-outline-secondary" href="<?= url_to('admin.users') ?>">Cancel</a><button class="btn btn-primary" type="submit">Create user</button></div>
            </form>
        </section>
    </div>
    <div class="col-xl-4">
        <aside class="panel note-panel"><p class="eyebrow">Security note</p><h3>Least privilege</h3><p>Choose the most limited role that supports the user’s duties. Super Administrator accounts should be reserved for trusted system owners.</p></aside>
    </div>
</div>
<?= $this->endSection() ?>
