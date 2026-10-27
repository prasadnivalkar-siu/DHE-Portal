<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="page-heading">
    <div><p class="eyebrow">Data category</p><h2>Placement records</h2><p>Search and filter all successfully imported placement data.</p></div>
    <?php if (auth()->user()->can('placements.import')): ?><a class="btn btn-primary" href="<?= url_to('placements.import') ?>">Import Excel</a><?php endif ?>
</div>

<section class="panel mb-4">
    <form class="row g-3 align-items-end" method="post" action="<?= url_to('placements.filter') ?>">
        <?= csrf_field() ?>
        <div class="col-lg-4"><label class="form-label" for="search">Search</label><input class="form-control" id="search" name="search" value="<?= esc($filters['search']) ?>" placeholder="Institution, program, course, type or company"></div>
        <div class="col-md-4 col-lg-2"><label class="form-label" for="academic_year">Academic year</label><select class="form-select" id="academic_year" name="academic_year"><option value="">All years</option><?php foreach ($years as $year): ?><option value="<?= esc($year['academic_year']) ?>" <?= $filters['academicYear'] === $year['academic_year'] ? 'selected' : '' ?>><?= esc($year['academic_year']) ?></option><?php endforeach ?></select></div>
        <div class="col-md-4 col-lg-2"><label class="form-label" for="univ_code">University</label><select class="form-select" id="univ_code" name="univ_code"><option value="">All universities</option><?php foreach ($universities as $item): ?><option value="<?= esc($item['university_aishe_code']) ?>" <?= $filters['university'] === $item['university_aishe_code'] ? 'selected' : '' ?>><?= esc($item['university_aishe_code'] . ' · ' . ($item['university_name'] ?? '')) ?></option><?php endforeach ?></select></div>
        <div class="col-md-4 col-lg-2"><label class="form-label" for="college_code">Institution</label><select class="form-select" id="college_code" name="college_code"><option value="">All institutions</option><?php foreach ($colleges as $item): ?><option value="<?= esc($item['college_code']) ?>" data-universities="<?= esc(implode('|', $item['universities'])) ?>" <?= $filters['college'] === $item['college_code'] ? 'selected' : '' ?>><?= esc($item['college_code'] . ' · ' . ($item['college_name'] ?? '')) ?></option><?php endforeach ?></select></div>
        <div class="col-md-4 col-lg-2"><label class="form-label" for="per_page">Records per page</label><select class="form-select" id="per_page" name="per_page" onchange="this.form.requestSubmit()"><?php foreach ($pageSizeOptions as $option): ?><option value="<?= $option ?>" <?= $filters['perPage'] === $option ? 'selected' : '' ?>><?= $option ?></option><?php endforeach ?></select></div>
        <div class="col-md-12 col-lg-2 d-flex gap-2"><button class="btn btn-primary flex-fill" type="submit">Apply</button><button class="btn btn-outline-secondary" type="submit" name="clear_filters" value="1" formnovalidate>Clear</button></div>
    </form>
</section>

<section class="panel p-0 overflow-hidden">
    <div class="panel-header px-4 pt-4"><div><h3 class="mb-1">Records</h3><p class="text-secondary mb-0">Showing <?= number_format($filters['perPage']) ?> records per page</p></div></div>
    <?php if ($placements === []): ?>
        <div class="empty-state"><h4>No placement records found</h4><p>Adjust the filters or import a placement workbook.</p></div>
    <?php else: ?>
        <div class="table-responsive"><table class="table portal-table align-middle mb-0"><thead><tr><th class="academic-year-column">Academic year</th><th>University</th><th class="college-column">Institution</th><th>Type</th><th>Publishing</th><th>Program / course</th><th>Company</th><th>1–3 lakh</th><th>3–5 lakh</th><th>5–10 lakh</th><th>10–15 lakh</th><th>&gt;15 lakh</th><th>Updated</th><th></th></tr></thead><tbody>
        <?php foreach ($placements as $record): ?><tr>
            <td class="academic-year-column"><span class="year-chip"><?= esc($record['academic_year']) ?></span></td>
            <td><strong><?= esc($record['university_aishe_code']) ?></strong><small class="table-subtext"><?= esc($record['university_name'] ?? '—') ?></small></td>
            <td class="college-column"><div class="college-summary"><span class="college-code"><?= esc($record['institution_aishe_code']) ?></span><span class="college-name"><?= esc($record['institution_name'] ?? 'NA') ?></span></div></td>
            <td class="metadata-column"><?= esc($record['institution_type_name'] ?? 'NA') ?></td>
            <td><span class="status status-<?= esc($record['publication_status'] ?? 'draft') ?>"><?= esc($record['publication_status'] ?? 'draft') ?></span></td>
            <td><span class="text-truncate-row"><?= esc($record['program_name'] ?? 'NA') ?></span><small class="table-subtext"><?= esc($record['course_name'] ?? 'NA') ?></small></td>
            <td><span class="text-truncate-row"><?= esc($record['company_name'] ?? 'NA') ?></span></td>
            <td class="metadata-column"><?= esc($record['package_1_to_3_lakh'] ?? 'NA') ?></td>
            <td class="metadata-column"><?= esc($record['package_3_to_5_lakh'] ?? 'NA') ?></td>
            <td class="metadata-column"><?= esc($record['package_5_to_10_lakh'] ?? 'NA') ?></td>
            <td class="metadata-column"><?= esc($record['package_10_to_15_lakh'] ?? 'NA') ?></td>
            <td class="metadata-column"><?= esc($record['package_above_15_lakh'] ?? 'NA') ?></td>
            <td><?= esc(date('d M Y', strtotime($record['updated_at']))) ?><small class="table-subtext"><?= esc(date('h:i A', strtotime($record['updated_at']))) ?></small></td>
            <td><a class="btn btn-sm btn-outline-primary" href="<?= url_to('placements.show', $record['id']) ?>">View</a></td>
        </tr><?php endforeach ?>
        </tbody></table></div>
        <div class="panel-footer"><?= $pager->links('placements', 'bootstrap_full') ?></div>
    <?php endif ?>
</section>
<?= $this->endSection() ?>


