<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PropertyUnitPublicImage extends Model
{
    protected $table = 'property_unit_public_images';

    protected $fillable = [
        'property_unit_id',
        'path',
        'sort_order',
    ];

    public function propertyUnit(): BelongsTo
    {
        return $this->belongsTo(PropertyUnit::class);
    }

    public function publicUrl(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
