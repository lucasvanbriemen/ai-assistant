<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    public function memories()
    {
        return $this->belongsToMany(Memory::class, 'memory_tag_links', 'tag_id', 'memory_id')
            ->withTimestamps();
    }

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
}
