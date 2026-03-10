<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Estudiante extends Model
{
    use HasFactory;

    // Standard ID is used

    protected $fillable = ['nfc_id', 'nombre', 'alias', 'activo'];

    public function logs()
    {
        return $this->hasMany(LogPrestamo::class, 'estudiante_id');
    }
}
