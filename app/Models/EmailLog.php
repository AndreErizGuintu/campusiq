<?php

namespace App\Models;

use App\Core\Model;

class EmailLog extends Model
{
    protected static string $table = 'email_logs';

    public const STATUSES = ['sent', 'failed', 'demo'];
}
