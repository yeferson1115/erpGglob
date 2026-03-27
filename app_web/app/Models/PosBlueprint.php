<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosBlueprint extends Model
{
    protected $fillable = [
        'company_id',
        'analysis_text',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}
