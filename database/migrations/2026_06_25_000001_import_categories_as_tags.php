<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Convoro 2 "Prism": seed the tag tree from existing categories so the new
// tag/sub-tag navigation has real content, preserving the category hierarchy
// (parent_id) and linking every topic to its category's tag. Non-destructive:
// categories are left intact so existing category code keeps working during the
// transition.
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('categories')) {
            return;
        }
        $cats = DB::table('categories')->orderBy('position')->get();
        if ($cats->isEmpty()) {
            return;
        }

        $map = [];
        foreach ($cats as $cat) {
            $data = [
                'name' => $cat->name,
                'color' => $cat->color ?: '#7c3aed',
                'icon' => $cat->icon,
                'description' => $cat->description,
                'position' => $cat->position ?? 0,
                'updated_at' => now(),
            ];
            $existing = DB::table('tags')->where('slug', $cat->slug)->value('id');
            if ($existing) {
                DB::table('tags')->where('id', $existing)->update($data);
                $map[$cat->id] = $existing;
            } else {
                $map[$cat->id] = DB::table('tags')->insertGetId($data + ['slug' => $cat->slug, 'created_at' => now()]);
            }
        }

        foreach ($cats as $cat) {
            if ($cat->parent_id && isset($map[$cat->parent_id], $map[$cat->id])) {
                DB::table('tags')->where('id', $map[$cat->id])->update(['parent_id' => $map[$cat->parent_id]]);
            }
        }

        foreach ($map as $catId => $tagId) {
            $rows = DB::table('topics')->where('category_id', $catId)->pluck('id')
                ->map(fn ($tid) => ['topic_id' => $tid, 'tag_id' => $tagId])->all();
            if ($rows) {
                DB::table('topic_tag')->insertOrIgnore($rows);
            }
        }
    }

    public function down(): void
    {
        // Non-destructive import — nothing safe to reverse automatically.
    }
};
