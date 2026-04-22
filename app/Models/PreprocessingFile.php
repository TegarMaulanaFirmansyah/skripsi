<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PreprocessingFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'filename',
        'path',
        'size',
        'total_rows',
        'status',
    ];

    public function preprocessingDetails()
    {
        return $this->hasMany(PreprocessingDetail::class);
    }
}
