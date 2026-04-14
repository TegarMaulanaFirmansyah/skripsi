<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PreprocessingFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'file',
    ];

    /**
     * Get the preprocessing details for this file.
     */
    public function preprocessingDetails()
    {
        return $this->hasMany(PreprocessingDetail::class);
    }

    /**
     * Get the total count of preprocessing details for this file.
     */
    public function getDetailsCountAttribute()
    {
        return $this->preprocessingDetails()->count();
    }

    /**
     * Get the count of completed preprocessing (has stemming).
     */
    public function getCompletedCountAttribute()
    {
        return $this->preprocessingDetails()->whereNotNull('stemming')->where('stemming', '!=', '')->count();
    }

    /**
     * Get the preprocessing progress percentage.
     */
    public function getProgressPercentageAttribute()
    {
        $total = $this->details_count;
        if ($total === 0) {
            return 0;
        }
        
        $completed = $this->completed_count;
        return round(($completed / $total) * 100, 2);
    }

    /**
     * Check if preprocessing is completed for this file.
     */
    public function getIsCompletedAttribute()
    {
        return $this->details_count > 0 && $this->details_count === $this->completed_count;
    }

    /**
     * Get file size in human readable format.
     */
    public function getFileSizeAttribute()
    {
        if (!$this->file) {
            return 'N/A';
        }

        $filePath = storage_path('app/preprocessing/' . $this->file);
        if (!file_exists($filePath)) {
            return 'File not found';
        }

        $bytes = filesize($filePath);
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get file extension.
     */
    public function getFileExtensionAttribute()
    {
        if (!$this->file) {
            return 'N/A';
        }
        
        return pathinfo($this->file, PATHINFO_EXTENSION);
    }

    /**
     * Scope untuk mendapatkan file yang sudah selesai di-preprocessing
     */
    public function scopeCompleted($query)
    {
        return $query->whereHas('preprocessingDetails', function ($query) {
            $query->whereNotNull('stemming')->where('stemming', '!=', '');
        });
    }

    /**
     * Scope untuk mendapatkan file yang belum selesai di-preprocessing
     */
    public function scopeIncomplete($query)
    {
        return $query->whereDoesntHave('preprocessingDetails')
            ->orWhereHas('preprocessingDetails', function ($query) {
                $query->whereNull('stemming')->orWhere('stemming', '');
            });
    }

    /**
     * Scope untuk mencari file berdasarkan nama
     */
    public function scopeSearchByName($query, $search)
    {
        return $query->where('file', 'like', '%' . $search . '%');
    }

    /**
     * Mutator untuk memastikan nama file selalu trim
     */
    public function setFileAttribute($value)
    {
        $this->attributes['file'] = trim($value);
    }

    /**
     * Boot method untuk model events
     */
    protected static function boot()
    {
        parent::boot();

        // Auto delete related preprocessing details when file is deleted
        static::deleting(function ($preprocessingFile) {
            $preprocessingFile->preprocessingDetails()->delete();
        });
    }
}
