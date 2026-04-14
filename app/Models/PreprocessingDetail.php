<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PreprocessingDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'preprocessing_file_id',
        'tweet_asli',
        'case_folding',
        'cleansing',
        'normalisasi',
        'tokenizing',
        'filtering',
        'stemming',
    ];

    /**
     * Get the preprocessing file that owns this detail.
     */
    public function preprocessingFile()
    {
        return $this->belongsTo(PreprocessingFile::class);
    }

    /**
     * Scope untuk mendapatkan data yang memiliki stemming tidak kosong
     */
    public function scopeHasStemming($query)
    {
        return $query->whereNotNull('stemming')->where('stemming', '!=', '');
    }

    /**
     * Scope untuk mendapatkan data berdasarkan preprocessing file
     */
    public function scopeByFile($query, $fileId)
    {
        return $query->where('preprocessing_file_id', $fileId);
    }

    /**
     * Accessor untuk mendapatkan panjang tweet asli
     */
    public function getTweetLengthAttribute()
    {
        return strlen($this->tweet_asli);
    }

    /**
     * Accessor untuk mendapatkan jumlah kata setelah stemming
     */
    public function getWordCountAttribute()
    {
        return str_word_count($this->stemming);
    }

    /**
     * Mutator untuk memastikan case folding selalu lowercase
     */
    public function setCaseFoldingAttribute($value)
    {
        $this->attributes['case_folding'] = strtolower($value);
    }

    /**
     * Mutator untuk memastikan cleansing selalu trim
     */
    public function setCleansingAttribute($value)
    {
        $this->attributes['cleansing'] = trim($value);
    }

    /**
     * Mutator untuk memastikan normalisasi selalu trim
     */
    public function setNormalisasiAttribute($value)
    {
        $this->attributes['normalisasi'] = trim($value);
    }

    /**
     * Mutator untuk memastikan tokenizing selalu trim
     */
    public function setTokenizingAttribute($value)
    {
        $this->attributes['tokenizing'] = trim($value);
    }

    /**
     * Mutator untuk memastikan filtering selalu trim
     */
    public function setFilteringAttribute($value)
    {
        $this->attributes['filtering'] = trim($value);
    }

    /**
     * Mutator untuk memastikan stemming selalu trim
     */
    public function setStemmingAttribute($value)
    {
        $this->attributes['stemming'] = trim($value);
    }
}
