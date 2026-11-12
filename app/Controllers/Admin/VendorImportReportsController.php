<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\VendorImportReportService;

final class VendorImportReportsController extends BaseController
{
    public function download(string $category, int $id)
    {
        return (new VendorImportReportService())->download($category, $id, $this->response);
    }
}
