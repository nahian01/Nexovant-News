<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class SavedArticle extends Pivot
{
    protected $table = 'saved_articles';

    protected $fillable = [
        'user_id',
        'article_id',
        'notes',
    ];
}
