<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$sections = [
    'University and institution' => [
        'Academic year' => 'academic_year', 'University AISHE code' => 'university_aishe_code',
        'University name' => 'university_name', 'University / college AISHE code' => 'institution_aishe_code',
        'University / college name' => 'institution_name', 'University / college type' => 'institution_type_name',
    ],
    'Program and employer' => [
        'Program name' => 'program_name', 'Course name' => 'course_name', 'Company name' => 'company_name',
    ],
    'Package range counts' => [
        '1 to 3 lakh' => 'package_1_to_3_lakh', '3 to 5 lakh' => 'package_3_to_5_lakh',
        '5 to 10 lakh' => 'package_5_to_10_lakh', '10 to 15 lakh' => 'package_10_to_15_lakh',
        'More than 15 lakh' => 'package_above_15_lakh',
    ],
];
?>
<div class="page-heading">
    <div><p class="eyebrow">Placement record</p><h2><?= esc($record['institution_aishe_code']) ?> · <?= esc($record['program_name'] ?? 'NA') ?></h2><p><?= esc($record['institution_name'] ?? 'Institution not provided') ?> · <span class="status status-<?= esc($record['publication_status'] ?? 'draft') ?>"><?= esc($record['publication_status'] ?? 'draft') ?></span></p></div>
    <a class="btn btn-outline-secondary" href="<?= url_to('placements.index') ?>">Back to records</a>
</div>

<div class="row g-4">
    <?php foreach ($sections as $heading => $fields): ?>
        <div class="col-xl-6"><section class="panel h-100 detail-panel"><h3><?= esc($heading) ?></h3><dl class="detail-grid">
            <?php foreach ($fields as $label => $key): ?><div><dt><?= esc($label) ?></dt><dd><?= ($record[$key] ?? null) !== null && $record[$key] !== '' ? esc((string) $record[$key]) : '—' ?></dd></div><?php endforeach ?>
        </dl></section></div>
    <?php endforeach ?>
</div>
<?= $this->endSection() ?>


