<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Surah extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $fillable = ['id', 'name', 'ayah_count', 'juz_start'];
}
