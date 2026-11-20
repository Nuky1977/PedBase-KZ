<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherDiploma extends Model
{
    protected $fillable = ['education_level', 'institution', 'specialty', 'diploma_number', 'graduation_year'];
}
