<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogPrestamo extends Model
{
    use HasFactory;

    protected $table = 'logs_prestamos';

    protected $fillable = ['estudiante_id', 'camara_id', 'accion', 'observacion'];

    public function estudiante()
    {
        return $this->belongsTo(Estudiante::class, 'estudiante_id');
    }

    public function camara()
    {
        return $this->belongsTo(Camara::class, 'camara_id');
    }
}
