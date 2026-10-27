<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="page-heading">
    <div><p class="eyebrow">Data category</p><h2>Result records</h2><p>Search and filter successfully imported result data by academic year, examination, university, and organization.</p></div>
    <?php if (auth()->user()->can('results.import')): ?><a class="btn btn-primary" href="<?= url_to('results.import') ?>">Import Excel</a><?php endif ?>
</div>

<section class="panel mb-4">
    <form class="row g-3 align-items-end" method="post" action="<?= url_to('results.filter') ?>">
        <?= csrf_field() ?>
        <div class="col-lg-4"><label class="form-label" for="search">Search</label><input class="form-control" id="search" name="search" value="<?= esc($filters['search']) ?>" placeholder="Student, organization, course or AISHE code"></div>
        <div class="col-md-4 col-lg-2"><label class="form-label" for="academic_year">Academic year</label><select class="form-select" id="academic_year" name="academic_year"><option value="">All years</option><?php foreach ($years as $year): ?><option value="<?= esc($year['academic_year']) ?>" <?= $filters['academicYear'] === $year['academic_year'] ? 'selected' : '' ?>><?= esc($year['academic_year']) ?></option><?php endforeach ?></select></div>
        <div class="col-md-4 col-lg-2"><label class="form-label" for="exam_year">Exam year</label><select class="form-select" id="exam_year" name="exam_year"><option value="">All exam years</option><?php foreach ($examYears as $year): ?><option value="<?= esc($year['exam_year']) ?>" <?= $filters['examYear'] === $year['exam_year'] ? 'selected' : '' ?>><?= esc($year['exam_year']) ?></option><?php endforeach ?></select></div>
        <div class="col-md-4 col-lg-2"><label class="form-label" for="season">Season</label><select class="form-select" id="season" name="season"><option value="">All seasons</option><?php foreach ($seasons as $item): ?><option value="<?= esc($item['season']) ?>" <?= $filters['season'] === $item['season'] ? 'selected' : '' ?>><?= esc($item['season']) ?></option><?php endforeach ?></select></div>
        <div class="col-md-6 col-lg-3"><label class="form-label" for="univ_code">University</label><select class="form-select" id="univ_code" name="univ_code"><option value="">All universities</option><?php foreach ($universities as $item): ?><option value="<?= esc($item['university_aishe_code']) ?>" <?= $filters['university'] === $item['university_aishe_code'] ? 'selected' : '' ?>><?= esc($item['university_aishe_code'] . ' · ' . ($item['university_name'] ?? '')) ?></option><?php endforeach ?></select></div>
        <div class="col-md-6 col-lg-4"><label class="form-label" for="organization_code">Organization</label><select class="form-select" id="organization_code" name="organization_code"><option value="">All organizations</option><?php foreach ($organizations as $item): ?><option value="<?= esc($item['organization_code']) ?>" data-universities="<?= esc(implode('|', $item['universities'])) ?>" <?= $filters['organization'] === $item['organization_code'] ? 'selected' : '' ?>><?= esc($item['organization_code'] . ' · ' . ($item['organization_name'] ?? '')) ?></option><?php endforeach ?></select></div>
        <div class="col-md-6 col-lg-2"><label class="form-label" for="per_page">Records per page</label><select class="form-select" id="per_page" name="per_page" onchange="this.form.requestSubmit()"><?php foreach ($pageSizeOptions as $option): ?><option value="<?= $option ?>" <?= $filters['perPage'] === $option ? 'selected' : '' ?>><?= $option ?></option><?php endforeach ?></select></div>
        <div class="col-md-12 col-lg-3 d-flex gap-2"><button class="btn btn-primary flex-fill" type="submit">Apply filters</button><button class="btn btn-outline-secondary" type="submit" name="clear_filters" value="1" formnovalidate>Clear</button></div>
    </form>
</section>

<section class="panel p-0 overflow-hidden">
    <div class="panel-header px-4 pt-4"><div><h3 class="mb-1">Records</h3><p class="text-secondary mb-0">Showing <?= number_format($filters['perPage']) ?> records per page</p></div></div>
    <?php if ($results === []): ?>
        <div class="empty-state"><h4>No result records found</h4><p>Adjust the filters or import a result workbook.</p></div>
    <?php else: ?>
        <div class="table-responsive"><table class="table portal-table align-middle mb-0"><thead><tr><th class="academic-year-column">Academic year</th><th>Exam</th><th>University</th><th class="college-column">Organization</th><th>Student</th><th>Course</th><th>Semester</th><th>Result</th><th>Publishing</th><th>Updated</th><th></th></tr></thead><tbody>
        <?php foreach ($results as $record): ?><tr>
            <td class="academic-year-column"><span class="year-chip"><?= esc($record['academic_year']) ?></span></td>
            <td><strong><?= esc($record['exam_year']) ?></strong><small class="table-subtext"><?= esc($record['season']) ?></small></td>
            <td><strong><?= esc($record['university_aishe_code']) ?></strong><small class="table-subtext"><?= esc($record['university_name'] ?? 'NA') ?></small></td>
            <td class="college-column"><div class="college-summary"><span class="college-code"><?= esc($record['organization_aishe_code']) ?></span><span class="college-name"><?= esc($record['organization_name'] ?? 'NA') ?></span></div></td>
            <?php $studentId = ($record['prn'] ?? 'NA') !== 'NA' ? $record['prn'] : ((($record['registration_no'] ?? 'NA') !== 'NA') ? $record['registration_no'] : ($record['roll_no'] ?? 'NA')); ?>
            <td><strong><?= esc($studentId) ?></strong><small class="table-subtext"><?= esc(($record['registration_no'] ?? 'NA') !== $studentId ? ($record['registration_no'] ?? 'NA') : ($record['roll_no'] ?? 'NA')) ?></small></td>
            <td><span class="text-truncate-row"><?= esc($record['course_name'] ?? 'NA') ?></span><small class="table-subtext"><?= esc($record['course_code'] ?? 'NA') ?></small></td>
            <td class="metadata-column"><?= esc($record['semester'] ?? 'NA') ?></td>
            <td class="metadata-column"><?= esc($record['result_status'] ?? 'NA') ?></td>
            <td><span class="status status-<?= esc($record['publication_status'] ?? 'draft') ?>"><?= esc($record['publication_status'] ?? 'draft') ?></span></td>
            <td><?= esc(date('d M Y', strtotime($record['updated_at']))) ?><small class="table-subtext"><?= esc(date('h:i A', strtotime($record['updated_at']))) ?></small></td>
            <td><a class="btn btn-sm btn-outline-primary" href="<?= url_to('results.show', $record['id']) ?>">View</a></td>
        </tr><?php endforeach ?>
        </tbody></table></div>
        <div class="panel-footer"><?= $pager->links('results', 'bootstrap_full') ?></div>
    <?php endif ?>
</section>
<?= $this->endSection() ?>
