<?php

namespace App\Models;

use App\Core\Model;

class Record extends Model
{
    protected static string $table = 'records';

    public const TYPES = ['grade', 'attendance', 'library'];
}
