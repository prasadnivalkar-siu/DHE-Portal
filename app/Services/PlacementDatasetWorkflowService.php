<?php

namespace App\Services;

use App\Models\PlacementImportJobModel;
use RuntimeException;

final class PlacementDatasetWorkflowService
{
    public function transition(int $jobId, string $action, int $actorId): string
    {
        $job = (new PlacementImportJobModel())->find($jobId);
        if ($job === null) {
            throw new RuntimeException('Import dataset not found.');
        }
        if (! in_array($job['status'], ['completed', 'completed_with_errors'], true)) {
            throw new RuntimeException('The database import must complete before workflow review.');
        }

        $rules = [
            'validate' => ['from' => 'draft', 'to' => 'validated', 'actor' => 'reviewed_by', 'time' => 'reviewed_at'],
            'approve' => ['from' => 'validated', 'to' => 'approved', 'actor' => 'approved_by', 'time' => 'approved_at'],
            'publish' => ['from' => 'approved', 'to' => 'published', 'actor' => 'published_by', 'time' => 'published_at'],
        ];
        if (! isset($rules[$action])) {
            throw new RuntimeException('Unsupported workflow action.');
        }
        $rule = $rules[$action];
        if (($job['workflow_status'] ?? 'draft') !== $rule['from']) {
            throw new RuntimeException('This dataset must be ' . ucfirst($rule['from']) . ' before it can be ' . $rule['to'] . '.');
        }

        $db = db_connect();
        $now = date('Y-m-d H:i:s');
        $db->transStart();
        $db->table('placement_import_jobs')->where('id', $jobId)->where('workflow_status', $rule['from'])->update([
            'workflow_status' => $rule['to'],
            $rule['actor'] => $actorId,
            $rule['time'] => $now,
            'updated_at' => $now,
        ]);
        if ($db->affectedRows() !== 1) {
            $db->transRollback();
            throw new RuntimeException('The workflow changed while this action was being processed. Refresh and try again.');
        }
        if ($action === 'publish') {
            $db->table('placements')->where('import_job_id', $jobId)->update([
                'publication_status' => 'published',
                'published_at' => $now,
            ]);
        }
        $db->transComplete();
        if (! $db->transStatus()) {
            throw new RuntimeException('The dataset workflow could not be updated.');
        }
        return $rule['to'];
    }
}


