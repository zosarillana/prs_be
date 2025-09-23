<?php

use App\Models\AuditLog;

function auditLog($action, $model = null, $old = null, $new = null)
{
    AuditLog::create([
        'user_id'    => auth()->id(),
        'action'     => $action,
        'model_type' => $model ? get_class($model) : null,
        'model_id'   => $model?->id,
        'old_values' => $old,
        'new_values' => $new,
        'ip_address' => request()->ip(),
        'user_agent' => request()->userAgent(),
    ]);
}
