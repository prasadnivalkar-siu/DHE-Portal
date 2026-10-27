<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('main') ?>
<div class="login-card">
    <div class="d-lg-none mb-4"><span class="auth-brand-mark small-mark">DHE</span></div>
    <p class="eyebrow">Administration Console</p>
    <h2>Welcome back</h2>
    <p class="text-secondary mb-4">Sign in with your authorized DHE Portal account.</p>

    <?php if (session('error') !== null): ?>
        <div class="alert alert-danger" role="alert"><?= esc(session('error')) ?></div>
    <?php endif ?>
    <?php if (session('message') !== null): ?>
        <div class="alert alert-success" role="alert"><?= esc(session('message')) ?></div>
    <?php endif ?>
    <?php if (session('errors') !== null): ?>
        <div class="alert alert-danger" role="alert"><ul class="mb-0"><?php foreach (session('errors') as $error): ?><li><?= esc($error) ?></li><?php endforeach ?></ul></div>
    <?php endif ?>

    <form action="<?= url_to('login') ?>" method="post" novalidate>
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label" for="email">Email address</label>
            <input class="form-control form-control-lg" id="email" type="email" name="email" inputmode="email" autocomplete="email" value="<?= old('email') ?>" required autofocus>
        </div>
        <div class="mb-3">
            <label class="form-label" for="password">Password</label>
            <input class="form-control form-control-lg" id="password" type="password" name="password" autocomplete="current-password" required>
        </div>
        <?php if (setting('Auth.sessionConfig')['allowRemembering']): ?>
            <div class="form-check mb-4"><input class="form-check-input" type="checkbox" name="remember" id="remember" <?= old('remember') ? 'checked' : '' ?>><label class="form-check-label" for="remember">Keep me signed in on this device</label></div>
        <?php endif ?>
        <button class="btn btn-primary btn-lg w-100" type="submit">Sign in securely</button>
    </form>
    <p class="login-help">Need access? Contact a DHE Portal administrator.</p>
</div>
<?= $this->endSection() ?>
