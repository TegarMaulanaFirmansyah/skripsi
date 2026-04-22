<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PreprocessingDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'preprocessing_file_id',
        'raw_text',
        'case_folding',
        'cleansing',
        'normalisasi',
        'tokenizing',
        'filtering',
        'stemming',
    ];

    public function preprocessingFile()
    {
        return $this->belongsTo(PreprocessingFile::class);
    }
}
