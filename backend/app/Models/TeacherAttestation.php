<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherAttestation extends Model
{
    protected $fillable = ['category', 'awarded_on', 'valid_until'];
}
