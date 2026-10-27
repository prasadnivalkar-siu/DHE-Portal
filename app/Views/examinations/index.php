<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="page-heading">
    <div><p class="eyebrow">Data category</p><h2>Examination records</h2><p>Search and filter all successfully imported examination data.</p></div>
    <?php if (auth()->user()->can('examinations.import')): ?><a class="btn btn-primary" href="<?= url_to('examinations.import') ?>">Import Excel</a><?php endif ?>
</div>

<section class="panel mb-4">
    <form class="row g-3 align-items-end" method="post" action="<?= url_to('examinations.filter') ?>">
        <?= csrf_field() ?>
        <div class="col-lg-4"><label class="form-label" for="search">Search</label><input class="form-control" id="search" name="search" value="<?= esc($filters['search']) ?>" placeholder="PRN, seat, exam form, college, course or paper"></div>
        <div class="col-md-4 col-lg-2"><label class="form-label" for="academic_year">Academic year</label><select class="form-select" id="academic_year" name="academic_year"><option value="">All years</option><?php foreach ($years as $year): ?><option value="<?= esc($year['academic_year']) ?>" <?= $filters['academicYear'] === $year['academic_year'] ? 'selected' : '' ?>><?= esc($year['academic_year']) ?></option><?php endforeach ?></select></div>
        <div class="col-md-4 col-lg-2"><label class="form-label" for="univ_code">University</label><select class="form-select" id="univ_code" name="univ_code"><option value="">All universities</option><?php foreach ($universities as $item): ?><option value="<?= esc($item['univ_aishe_code']) ?>" <?= $filters['university'] === $item['univ_aishe_code'] ? 'selected' : '' ?>><?= esc($item['univ_aishe_code'] . ' · ' . ($item['univ_name'] ?? '')) ?></option><?php endforeach ?></select></div>
        <div class="col-md-4 col-lg-2"><label class="form-label" for="college_code">College</label><select class="form-select" id="college_code" name="college_code"><option value="">All colleges</option><?php foreach ($colleges as $item): ?><option value="<?= esc($item['college_code']) ?>" data-universities="<?= esc(implode('|', $item['universities'])) ?>" <?= $filters['college'] === $item['college_code'] ? 'selected' : '' ?>><?= esc($item['college_code'] . ' · ' . ($item['college_name'] ?? '')) ?></option><?php endforeach ?></select></div>
        <div class="col-md-4 col-lg-2"><label class="form-label" for="per_page">Records per page</label><select class="form-select" id="per_page" name="per_page" onchange="this.form.requestSubmit()"><?php foreach ($pageSizeOptions as $option): ?><option value="<?= $option ?>" <?= $filters['perPage'] === $option ? 'selected' : '' ?>><?= $option ?></option><?php endforeach ?></select></div>
        <div class="col-md-12 col-lg-2 d-flex gap-2"><button class="btn btn-primary flex-fill" type="submit">Apply</button><button class="btn btn-outline-secondary" type="submit" name="clear_filters" value="1" formnovalidate>Clear</button></div>
    </form>
</section>

<section class="panel p-0 overflow-hidden">
    <div class="panel-header px-4 pt-4"><div><h3 class="mb-1">Records</h3><p class="text-secondary mb-0">Showing <?= number_format($filters['perPage']) ?> records per page</p></div></div>
    <?php if ($examinations === []): ?>
        <div class="empty-state"><h4>No examination records found</h4><p>Adjust the filters or import an examination workbook.</p></div>
    <?php else: ?>
        <div class="table-responsive"><table class="table portal-table align-middle mb-0"><thead><tr><th class="academic-year-column">Academic year</th><th>University</th><th class="college-column">College / study centre</th><th>Publishing</th><th>PRN / seat</th><th>Exam form</th><th>Course</th><th>Paper</th><th>Exam</th><th>Assessment</th><th>Centre / venue</th><th>Eligibility</th><th>Updated</th><th></th></tr></thead><tbody>
        <?php foreach ($examinations as $record): ?><tr>
            <td class="academic-year-column"><span class="year-chip"><?= esc($record['academic_year']) ?></span></td>
            <td><strong><?= esc($record['univ_aishe_code']) ?></strong><small class="table-subtext"><?= esc($record['univ_name'] ?? '—') ?></small></td>
            <td class="college-column"><div class="college-summary"><span class="college-code"><?= esc($record['college_study_center_code']) ?></span><span class="college-name"><?= esc($record['college_study_center_name'] ?? 'NA') ?></span></div></td>
            <td><span class="status status-<?= esc($record['publication_status'] ?? 'draft') ?>"><?= esc($record['publication_status'] ?? 'draft') ?></span></td>
            <td><strong><?= esc($record['prn'] ?? 'NA') ?></strong><small class="table-subtext">Seat: <?= esc($record['seat_number'] ?? 'NA') ?></small></td>
            <td class="metadata-column"><?= esc($record['exam_form_number'] ?? 'NA') ?><small class="table-subtext"><?= esc($record['exam_appearance_type'] ?? 'NA') ?></small></td>
            <td><span class="text-truncate-row"><?= esc($record['course_name'] ?? 'NA') ?></span><small class="table-subtext"><?= esc($record['course_code'] ?? 'NA') ?> · <?= esc($record['branch'] ?? 'NA') ?></small></td>
            <td><span class="text-truncate-row"><?= esc($record['paper_name'] ?? 'NA') ?></span><small class="table-subtext"><?= esc($record['paper_code'] ?? 'NA') ?></small></td>
            <td class="metadata-column"><?= esc($record['exam_year'] ?? 'NA') ?><small class="table-subtext"><?= esc($record['exam_session'] ?? 'NA') ?> · <?= esc($record['course_term'] ?? 'NA') ?></small></td>
            <td class="metadata-column"><?= esc($record['assessment_type'] ?? 'NA') ?><small class="table-subtext"><?= esc($record['assessment_method'] ?? 'NA') ?></small></td>
            <td><span class="text-truncate-row"><?= esc($record['center_name'] ?? 'NA') ?></span><small class="table-subtext"><?= esc($record['venue_name'] ?? 'NA') ?></small></td>
            <td class="metadata-column"><?= esc($record['eligibility_status'] ?? 'NA') ?></td>
            <td><?= esc(date('d M Y', strtotime($record['updated_at']))) ?><small class="table-subtext"><?= esc(date('h:i A', strtotime($record['updated_at']))) ?></small></td>
            <td><a class="btn btn-sm btn-outline-primary" href="<?= url_to('examinations.show', $record['id']) ?>">View</a></td>
        </tr><?php endforeach ?>
        </tbody></table></div>
        <div class="panel-footer"><?= $pager->links('examinations', 'bootstrap_full') ?></div>
    <?php endif ?>
</section>
<?= $this->endSection() ?>

