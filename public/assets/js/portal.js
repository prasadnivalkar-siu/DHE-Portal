(function ($) {
    'use strict';

    const submenuDuration = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 220;
    const categorySelector = '.nav-category, .mobile-category';

    function closeCategory($category) {
        if (!$category.prop('open')) return;

        const $submenu = $category.children('.nav-submenu, .mobile-submenu');
        $submenu.stop(true, true).slideUp(submenuDuration, function () {
            $category.prop('open', false);
            $submenu.removeAttr('style');
        });
    }

    function openCategory($category) {
        if ($category.prop('open')) return;

        const $submenu = $category.children('.nav-submenu, .mobile-submenu');
        $category.prop('open', true);
        $submenu.stop(true, true).hide().slideDown(submenuDuration, function () {
            $submenu.removeAttr('style');
        });
    }

    $(categorySelector).children('summary').on('click', function (event) {
        event.preventDefault();

        const $category = $(this).parent(categorySelector);
        const $navigation = $category.closest('.portal-nav, .mobile-links');
        const shouldOpen = !$category.prop('open');

        $navigation.find(categorySelector).not($category).each(function () {
            closeCategory($(this));
        });

        if (shouldOpen) openCategory($category);
        else closeCategory($category);
    });

    const $input = $('.upload-zone input[type="file"]').first();
    const $zone = $('.upload-zone');
    const $selected = $('#selectedFile');
    const $name = $('#selectedFileName');

    function showFile(file) {
        if (!file) {
            $selected.addClass('d-none');
            $name.text('');
            return;
        }
        $name.text(file.name);
        $selected.removeClass('d-none');
    }

    $input.on('change', function () { showFile(this.files[0]); });
    $zone.on('dragover dragenter', function (event) {
        event.preventDefault();
        $zone.addClass('is-dragging');
    }).on('dragleave drop', function () { $zone.removeClass('is-dragging'); });
    $('#clearFile').on('click', function () { $input.val(''); showFile(null); });

    const $importForm = $('#importForm');
    if ($importForm.length) {
        const importLabel = String($importForm.data('import-label') || ($input.attr('id') === 'examination_file' ? 'examination' : 'admission'));
        const $button = $('#importButton');
        const $cancel = $('#importCancel');
        const $clear = $('#clearFile');
        const $progress = $('#importProgress');
        const $bar = $('#importProgressBar');
        const $fill = $('#importProgressFill');
        const $percent = $('#importProgressPercent');
        const $title = $('#importProgressTitle');
        const $message = $('#importProgressMessage');
        const $phase = $('#importProgressPhase');
        const $elapsed = $('#importElapsed');
        const $error = $('#importAjaxError');
        const $icon = $('#importProgressIcon');
        const $recordProgress = $('#importRecordProgress');
        const $processedRows = $('#importProcessedRows');
        const $totalRows = $('#importTotalRows');
        const $recordBreakdown = $('#importRecordBreakdown');
        const $progressToken = $('#importProgressToken');
        let importing = false;
        let processingTimer = null;
        let elapsedTimer = null;
        let pollTimer = null;
        let polling = false;
        let serverProgressSeen = false;
        let activeProgressToken = '';
        let completionRedirect = '';
        let startedAt = 0;
        let progressValue = 0;

        function setStep(step) {
            const order = ['upload', 'validate', 'save', 'complete'];
            const current = order.indexOf(step);
            $('[data-import-step]').each(function () {
                const index = order.indexOf(String($(this).data('import-step')));
                $(this).removeClass('active done');
                if (index === current) $(this).addClass('active');
                if (index < current) $(this).addClass('done');
            });
        }

        function setProgress(value, title, message, phase, step) {
            progressValue = Math.max(progressValue, Math.min(100, Math.round(value)));
            $fill.css('width', progressValue + '%');
            $bar.attr('aria-valuenow', progressValue);
            $percent.text(progressValue + '%');
            if (title) $title.text(title);
            if (message) $message.text(message);
            if (phase) $phase.text(phase);
            if (step) setStep(step);
        }

        function formatElapsed(seconds) {
            const minutes = Math.floor(seconds / 60).toString().padStart(2, '0');
            const remainder = Math.floor(seconds % 60).toString().padStart(2, '0');
            return minutes + ':' + remainder;
        }

        function beginProcessingAnimation() {
            setProgress(35, 'Validating workbook', 'Checking the template and ' + importLabel + ' rows…', 'Validation', 'validate');
            pollImportProgress();
            pollTimer = window.setInterval(pollImportProgress, 400);
            processingTimer = window.setInterval(function () {
                const seconds = (Date.now() - startedAt) / 1000;
                if (!serverProgressSeen) {
                    const target = Math.min(43, 35 + (seconds * .45));
                    setProgress(target, 'Validating workbook', 'Checking headers, formats, and duplicate identities…', 'Validation', 'validate');
                }
            }, 450);
        }

        function renderRecordProgress(result) {
            const total = Number(result.totalRows || 0);
            const inserted = Number(result.insertedRows || 0);
            const updated = Number(result.updatedRows || 0);
            const skipped = Number(result.skippedRows || 0);
            const errors = Number(result.errorRows || 0);
            const processed = Number(result.processedRows ?? (inserted + updated + skipped + errors));
            if (total < 1) return;

            serverProgressSeen = true;
            $recordProgress.removeClass('d-none');
            $processedRows.text(processed.toLocaleString());
            $totalRows.text(total.toLocaleString());
            $recordBreakdown.text('Inserted ' + inserted.toLocaleString()
                + ' · Updated ' + updated.toLocaleString()
                + ' · Skipped ' + skipped.toLocaleString()
                + ' · Errors ' + errors.toLocaleString());

            const ratio = Math.min(1, processed / total);
            const phaseText = 'Database import · ' + processed.toLocaleString() + ' / ' + total.toLocaleString() + ' records';
            setProgress(35 + (ratio * 59), 'Importing ' + importLabel + ' records', 'Saving validated records in secure batches…', phaseText, 'save');
        }

        function renderValidationProgress(result) {
            const total = Number(result.totalRows || 0);
            const valid = Number(result.validRows || 0);
            const warning = Number(result.warningRows || 0);
            const duplicate = Number(result.duplicateRows || 0);
            const rejected = Number(result.rejectedRows || 0);
            const processed = Number(result.processedRows || (valid + warning + duplicate + rejected));
            if (total < 1) return;
            serverProgressSeen = true;
            $recordProgress.removeClass('d-none');
            $processedRows.text(processed.toLocaleString());
            $totalRows.text(total.toLocaleString());
            $recordBreakdown.text('Valid ' + valid.toLocaleString()
                + ' · Warnings ' + warning.toLocaleString()
                + ' · Duplicates ' + duplicate.toLocaleString()
                + ' · Rejected ' + rejected.toLocaleString());
            setProgress(35 + (Math.min(1, processed / total) * 59), 'Validating ' + importLabel + ' records', 'Classifying workbook rows and building the preview…', 'Validation · ' + processed.toLocaleString() + ' / ' + total.toLocaleString(), 'save');
        }

        function pollImportProgress() {
            if (!importing || polling) return;
            const token = activeProgressToken;
            const baseUrl = String($importForm.data('progress-url') || '').replace(/\/$/, '');
            if (!token || !baseUrl) return;

            polling = true;
            window.fetch(baseUrl + '/' + encodeURIComponent(token), {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (response) {
                return response.ok ? response.json() : null;
            }).then(function (response) {
                if (!response || !response.success || !response.found) return;

                if (response.status === 'queued_preview') {
                    setProgress(35, 'Starting validation worker', 'The workbook is queued for validation…', 'Queued', 'validate');
                    return;
                }

                if (response.status === 'previewing') {
                    if (Number(response.totalRows || 0) > 0) renderValidationProgress(response);
                    else setProgress(38, 'Validating workbook', 'Reading worksheet structure and validating headers…', 'Validation', 'validate');
                    return;
                }

                if (response.status === 'preview_ready') {
                    completeValidation(response);
                    return;
                }

                if (response.status === 'queued') {
                    setProgress(35, 'Starting import worker', 'The confirmed dataset is queued for database import…', 'Queued', 'validate');
                    return;
                }

                if (response.status === 'processing') {
                    if (Number(response.totalRows || 0) > 0) {
                        renderRecordProgress(response);
                    } else {
                        setProgress(38, 'Validating workbook', 'Reading worksheet structure and validating headers…', 'Validation', 'validate');
                    }
                    return;
                }

                if (response.status === 'completed' || response.status === 'completed_with_errors') {
                    completeImport(response);
                    return;
                }

                if (response.status === 'failed') failBackgroundImport(response);
            }).catch(function () {
                // The main upload request remains authoritative. A later poll
                // will recover from a temporary progress-read failure.
            }).finally(function () {
                polling = false;
            });
        }

        function completeValidation(result) {
            if (!importing) return;
            stopTimers();
            renderValidationProgress(result);
            const summary = Number(result.totalRows || 0).toLocaleString() + ' rows checked · '
                + Number(result.validRows || 0).toLocaleString() + ' valid · '
                + Number(result.warningRows || 0).toLocaleString() + ' warnings · '
                + Number(result.duplicateRows || 0).toLocaleString() + ' duplicates · '
                + Number(result.rejectedRows || 0).toLocaleString() + ' rejected';
            $progress.addClass('import-progress-complete');
            $icon.addClass('complete');
            $fill.removeClass('progress-bar-animated');
            setProgress(100, 'Preview ready for confirmation', summary, 'Review preview', 'complete');
            $button.text('Validation completed');
            importing = false;
            window.setTimeout(function () { if (completionRedirect) window.location.assign(completionRedirect); }, 900);
        }

        function completeImport(result) {
            if (!importing) return;
            stopTimers();
            renderRecordProgress(result);
            const hasRowErrors = Number(result.errorRows || 0) > 0;
            const summary = Number(result.totalRows || 0).toLocaleString() + ' rows processed · '
                + Number(result.insertedRows || 0).toLocaleString() + ' inserted · '
                + Number(result.updatedRows || 0).toLocaleString() + ' updated · '
                + Number(result.skippedRows || 0).toLocaleString() + ' skipped · '
                + Number(result.errorRows || 0).toLocaleString() + ' errors';
            $progress.addClass('import-progress-complete');
            $icon.addClass('complete');
            $fill.removeClass('progress-bar-animated');
            setProgress(100, hasRowErrors ? 'Import completed with row errors' : 'Import completed', summary, 'Complete', 'complete');
            $elapsed.text(formatElapsed((Date.now() - startedAt) / 1000));
            $button.text('Import completed');
            importing = false;
            window.setTimeout(function () {
                if (completionRedirect) window.location.assign(completionRedirect);
            }, 1200);
        }

        function failBackgroundImport(result) {
            if (!importing) return;
            stopTimers();
            $progress.addClass('import-progress-error');
            $icon.addClass('error');
            $fill.removeClass('progress-bar-animated');
            $title.text('Import stopped');
            $message.text('The worker could not complete this workbook.');
            $phase.text('Needs attention');
            $error.removeClass('d-none').text(result.message || 'The background import failed. Open Import Logs for details.');
            updateCsrf(null);
            restoreForm();
        }

        function freshProgressToken() {
            if (window.crypto && window.crypto.getRandomValues) {
                const bytes = new Uint8Array(16);
                window.crypto.getRandomValues(bytes);
                return Array.from(bytes, function (byte) { return byte.toString(16).padStart(2, '0'); }).join('');
            }
            return String(Date.now()) + Math.random().toString(16).slice(2).padEnd(19, '0').slice(0, 19);
        }

        function updateCsrf(response) {
            if (response && response.csrf) {
                const $token = $('#importCsrf');
                $token.attr('name', response.csrf.name).val(response.csrf.hash);
            }
            $progressToken.val(response && response.nextProgressToken ? response.nextProgressToken : freshProgressToken());
        }

        function stopTimers() {
            window.clearInterval(processingTimer);
            window.clearInterval(elapsedTimer);
            window.clearInterval(pollTimer);
            processingTimer = null;
            elapsedTimer = null;
            pollTimer = null;
        }

        function restoreForm() {
            importing = false;
            $button.prop('disabled', false).text('Upload and validate');
            $input.prop('disabled', false);
            $clear.prop('disabled', false);
            $cancel.removeClass('disabled').removeAttr('aria-disabled');
        }

        $importForm.on('submit', function (event) {
            event.preventDefault();
            if (importing) return;

            const file = $input[0] && $input[0].files ? $input[0].files[0] : null;
            if (!file) {
                $error.removeClass('d-none').text('Choose an Excel workbook before starting the import.');
                return;
            }
            const maximumUploadBytes = Number($importForm.data('max-upload-bytes') || 104857600);
            if (file.size > maximumUploadBytes) {
                const maximumUploadMegabytes = Math.round(maximumUploadBytes / (1024 * 1024));
                $error.removeClass('d-none').text(`The selected workbook is larger than the ${maximumUploadMegabytes} MB application limit.`);
                return;
            }

            importing = true;
            progressValue = 0;
            serverProgressSeen = false;
            activeProgressToken = String($progressToken.val() || '');
            completionRedirect = '';
            startedAt = Date.now();
            const formData = new FormData($importForm[0]);
            $error.addClass('d-none').empty();
            $progress.removeClass('d-none import-progress-error import-progress-complete');
            $recordProgress.addClass('d-none');
            $icon.removeClass('complete error');
            $fill.addClass('progress-bar-animated');
            $button.prop('disabled', true).text('Import in progress…');
            $input.prop('disabled', true);
            $clear.prop('disabled', true);
            $cancel.addClass('disabled').attr('aria-disabled', 'true');
            setProgress(2, 'Uploading workbook', 'Sending ' + file.name + ' securely…', 'Upload', 'upload');

            elapsedTimer = window.setInterval(function () {
                $elapsed.text(formatElapsed((Date.now() - startedAt) / 1000));
            }, 250);

            const request = new XMLHttpRequest();
            request.open('POST', $importForm.attr('action'), true);
            request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            request.setRequestHeader('Accept', 'application/json');
            request.upload.addEventListener('progress', function (upload) {
                if (upload.lengthComputable) {
                    setProgress(2 + ((upload.loaded / upload.total) * 31), 'Uploading workbook', 'Sending ' + file.name + ' securely…', 'Upload', 'upload');
                }
            });
            request.upload.addEventListener('load', beginProcessingAnimation);
            request.addEventListener('load', function () {
                let response = request.response;
                if (typeof response === 'string') {
                    try { response = JSON.parse(response); } catch (ignore) { response = null; }
                }
                updateCsrf(response);

                if (request.status >= 200 && request.status < 300 && response && response.success && response.queued) {
                    completionRedirect = response.redirect || '';
                    setProgress(35, 'Starting import worker', 'The workbook was uploaded and queued successfully…', 'Queued', 'validate');
                    pollImportProgress();
                    return;
                }

                if (request.status >= 200 && request.status < 300 && response && response.success) {
                    stopTimers();
                    const result = response.result || {};
                    completionRedirect = response.redirect || '';
                    completeImport(result);
                    return;
                }

                stopTimers();
                const errors = response && Array.isArray(response.errors) ? response.errors : [];
                const message = response && response.message ? response.message : 'The import could not be completed. Please try again.';
                $progress.addClass('import-progress-error');
                $icon.addClass('error');
                $fill.removeClass('progress-bar-animated');
                $title.text('Import stopped');
                $message.text('Review the message below and try again.');
                $phase.text('Needs attention');
                $error.removeClass('d-none').html($('<div>').text(message).html() + errors.map(function (item) { return '<div>' + $('<div>').text(item).html() + '</div>'; }).join(''));
                restoreForm();
            });
            request.addEventListener('error', function () {
                stopTimers();
                updateCsrf(null);
                $progress.addClass('import-progress-error');
                $icon.addClass('error');
                $title.text('Connection interrupted');
                $message.text('The server may still be processing the workbook. Check Import Logs before trying again.');
                $error.removeClass('d-none').text('The connection was interrupted. Open Import Logs to confirm whether the import completed.');
                restoreForm();
            });
            request.send(formData);
        });

        window.addEventListener('beforeunload', function (event) {
            if (!importing) return;
            event.preventDefault();
            event.returnValue = '';
        });
    }

    const $university = $('#univ_code');
    const $college = $('#college_code');

    function filterCollegeOptions() {
        if (!$university.length || !$college.length) {
            return;
        }

        const universityCode = String($university.val() || '');
        $college.find('option[data-universities]').each(function () {
            const universities = String($(this).data('universities') || '').split('|');
            const hidden = universityCode !== '' && !universities.includes(universityCode);
            $(this).prop('hidden', hidden).prop('disabled', hidden);
        });

        if ($college.find('option:selected').prop('disabled')) {
            $college.val('');
        }
    }

    $university.on('change', filterCollegeOptions);
    filterCollegeOptions();
})(jQuery);

(function () {
    'use strict';
    const panel = document.getElementById('jobLiveProgress');
    if (!panel) return;
    const url = panel.dataset.progressUrl;
    const returnUrl = panel.dataset.returnUrl || window.location.href;
    let previous = '';
    const terminal = ['preview_ready', 'completed', 'completed_with_errors', 'failed', 'cancelled'];
    const poll = function () {
        window.fetch(url, { credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) { return response.ok ? response.json() : null; })
            .then(function (result) {
                if (!result || !result.found) return;
                if (terminal.includes(result.status) || (previous && previous !== result.status)) {
                    window.location.assign(returnUrl);
                    return;
                }
                previous = result.status;
            }).catch(function () {}).finally(function () {
                window.setTimeout(poll, 1200);
            });
    };
    poll();
})();
