<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MenuItem extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'category_id',
        'name',
        'description',
        'ingredients',
        'price',
        'image',
        'is_available',
        'display_order',
    ];

    protected $casts = [
        'name' => 'array',
        'image' => 'array',
        'price' => 'decimal:2',
        'is_available' => 'boolean',
        'display_order' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function availableDates(): HasMany
    {
        return $this->hasMany(MenuAvailableDate::class);
    }
}
