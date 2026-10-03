<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class AiQuery extends Model
{
    protected static string $table = 'ai_queries';

    /** The user's latest questions, oldest first, for the chat history. */
    public static function recentFor(int $userId, int $limit = 6): array
    {
        return array_reverse(Database::all(
            'SELECT * FROM ai_queries WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT ' . max(1, $limit),
            [$userId]
        ));
    }
}
