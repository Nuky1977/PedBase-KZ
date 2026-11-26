<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherAchievement extends Model
{
    protected $fillable = ['title', 'issuer', 'awarded_on', 'level', 'description', 'document_path'];

    protected $hidden = ['document_path'];
}
