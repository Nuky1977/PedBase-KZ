<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherCourse extends Model
{
    protected $fillable = ['title', 'provider', 'hours', 'started_on', 'ended_on', 'certificate_number', 'certificate_issued_on', 'certificate_path'];

    protected $hidden = ['certificate_path'];
}
