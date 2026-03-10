<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Camara extends Model
{
    use HasFactory;

    // Standard ID is used

    protected $fillable = ['nfc_id', 'modelo', 'alias', 'estado'];

    public function logs()
    {
        return $this->hasMany(LogPrestamo::class, 'camara_id');
    }
}
