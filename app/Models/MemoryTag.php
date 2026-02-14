<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Cache;

class MemoryTag extends Model
{
    protected $fillable = [
        'name',
        'category',
        'usage_count',
    ];

    protected $casts = [
        'usage_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        // Clear cache on changes (if tagging is supported)
        static::saved(function ($tag) {
            try {
                Cache::tags(['memory', 'tags'])->flush();
            } catch (\BadMethodCallException $e) {
                Cache::flush();
            }
        });

        static::deleted(function ($tag) {
            try {
                Cache::tags(['memory', 'tags'])->flush();
            } catch (\BadMethodCallException $e) {
                Cache::flush();
            }
        });
    }

    public function memories(): BelongsToMany
    {
        return $this->belongsToMany(Memory::class, 'memory_tag_links', 'tag_id', 'memory_id')
            ->withTimestamps();
    }

    /**
     * Find or create a tag by name
     */
    public static function findOrCreateTag(string $name, ?string $category = null): self
    {
        $tag = static::where('name', $name)->first();

        if ($tag) {
            $tag->increment('usage_count');
            return $tag;
        }

        return static::create([
            'name' => $name,
            'category' => $category,
            'usage_count' => 1,
        ]);
    }

    /**
     * Get popular tags
     */
    public static function getPopular(int $limit = 20)
    {
        $cacheCallback = function () use ($limit) {
            return static::orderByDesc('usage_count')
                ->limit($limit)
                ->get();
        };

        try {
            return Cache::tags(['tags'])->remember('tags:popular', 3600, $cacheCallback);
        } catch (\BadMethodCallException $e) {
            return Cache::remember('tags:popular', 3600, $cacheCallback);
        }
    }

    /**
     * Get tags by category
     */
    public static function getByCategory(string $category)
    {
        $cacheCallback = function () use ($category) {
            return static::where('category', $category)
                ->orderByDesc('usage_count')
                ->get();
        };

        try {
            return Cache::tags(['tags'])->remember("tags:category:{$category}", 3600, $cacheCallback);
        } catch (\BadMethodCallException $e) {
            return Cache::remember("tags:category:{$category}", 3600, $cacheCallback);
        }
    }
}
