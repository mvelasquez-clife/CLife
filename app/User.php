<?php

namespace App;

use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable {
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    
    protected $table = "cl_usuarios";
    protected $primaryKey = "co_cliente";
    //protected $fillable = ["co_usuario","de_alias","co_centro_costo","co_empresa_usuario","es_vigencia","de_nombre","st_acceso_sistema","st_admin","de_correo","de_clave_wap",];
    //public $timestamps = false;

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = ["password", "remember_token"];
}
