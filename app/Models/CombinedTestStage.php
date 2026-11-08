<?php

namespace App\Models;

use App\Support\Juz;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** One step of the combined tests ladder, e.g. 30 → 28. */
class CombinedTestStage extends Model
{
    protected $fillable = ['name', 'from_juz', 'to_juz', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'from_juz' => 'integer', 'to_juz' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (CombinedTestStage $stage) {
            [$stage->from_juz, $stage->to_juz] = [max($stage->from_juz, $stage->to_juz), min($stage->from_juz, $stage->to_juz)];
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    /** @return int[] */
    public function ajza(): array
    {
        return range($this->from_juz, $this->to_juz);
    }

    public function label(): string
    {
        return 'من '.$this->from_juz.' إلى '.$this->to_juz.($this->name ? ' ('.$this->name.')' : '');
    }
}
