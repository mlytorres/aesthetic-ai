<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClinicAccessRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'clinic_name',
        'email',
        'website_url',
        'status',
    ];
}
