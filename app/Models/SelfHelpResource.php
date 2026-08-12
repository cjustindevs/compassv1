<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SelfHelpResource extends Model
{
    protected $table = 'self_help_resources';

    protected $fillable = [
        'title',
        'description',
        'category',
        'content',
        'icon',
        'duration',
        'difficulty',
        'tags',
        'is_featured',
        'is_published',
        'views_count',
        'saved_count',
    ];

    protected $casts = [
        'tags' => 'array',
        'is_featured' => 'boolean',
        'is_published' => 'boolean',
        'views_count' => 'integer',
        'saved_count' => 'integer',
    ];

    public function savedBy(): HasMany
    {
        return $this->hasMany(UserSavedResource::class, 'resource_id', 'id');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(UserResourceProgress::class, 'resource_id', 'id');
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeInCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('title', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")
                ->orWhere('tags', 'like', '%' . $term . '%')
                ->orWhere('category', 'like', "%{$term}%");
        });
    }

    public function getTagsListAttribute(): array
    {
        return is_array($this->tags) ? $this->tags : [];
    }

    public function getDifficultyLabelAttribute(): string
    {
        return ucfirst($this->difficulty ?? 'beginner');
    }
}