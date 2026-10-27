<?php

namespace App\Services;

use App\Models\AdminAuditLogModel;

final class AdminAuditService
{
    public function record(string $action, ?int $targetUserId = null, array $metadata = []): void
    {
        $actor = auth()->loggedIn() ? auth()->user() : null;
        $request = service('request');

        (new AdminAuditLogModel())->insert([
            'actor_user_id' => $actor?->id,
            'target_user_id' => $targetUserId,
            'action' => $action,
            'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'request_ip' => method_exists($request, 'getIPAddress') ? $request->getIPAddress() : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
