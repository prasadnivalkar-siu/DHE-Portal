<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Secure sign in for the DHE Portal">
    <title><?= esc($title ?? 'Sign in') ?> · DHE Portal</title>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= base_url('assets/css/portal.css') ?>">
</head>
<body class="auth-body">
    <main class="auth-shell">
        <section class="auth-aside">
            <div>
                <span class="auth-brand-mark">DHE</span>
                <p class="auth-kicker">Directorate of Higher Education</p>
                <h1>One secure portal for higher education data.</h1>
                <p>Manage category-wise submissions, validate admission records, and provide governed API access from a single administrative workspace.</p>
            </div>
            <small>Authorized personnel only · All administrative activity is auditable</small>
        </section>
        <section class="auth-panel">
            <?= $this->renderSection('main') ?>
        </section>
    </main>
</body>
</html>
