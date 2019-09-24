<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use DB;

class Kamill extends Controller {

    private $user;
    
    public function __construct() {
        define("KAMILL_LOGIN", 1);
        define("KAMILL_PUNTOS_VENTA", 2);
    }
    
    public function login(Request $request) {
        $alias = $request->get("alias");
        $password = $request->get("password");
        $usuarios = DB::table("sg_usua_m")
            ->where("de_alias", $alias)
            ->where("de_clave_sistema", $password)
            ->where("es_vigencia", "Vigente")
            ->count();
        if($usuarios > 0) {
            DB::statement("call pack_venta.sm_activar_empresa(?)", [$alias]);
            $usuario = DB::table("temp_ma_empresa")
                ->select("co_empresa as empresa", "de_alias as alias", DB::raw("initcap(de_usuario) as nvendedor"), "co_centro_costo as ccosto", "co_codigo as cvendedor")
                ->first();
            if($usuario) {
                $usuario->empresa = (int) $usuario->empresa;
                $usuario->ccosto = (int) $usuario->ccosto;
                return response()->json([
                    "data" => compact("usuario"),
                    "rqid" => KAMILL_LOGIN
                ]);
            }
            return response()->json([
                "error" => "No se pudo cargar los datos del vendedor",
                "rqid" => KAMILL_LOGIN
            ]);
        }
        return response()->json([
            "error" => "El usuario ingresado no existe",
            "rqid" => KAMILL_LOGIN
        ]);
    }

    public function puntos_venta(Request $request) {
        $empresa = $request->get("empresa");
        $puntosventa = DB::select("select co_punto_venta, de_nombre from vt_punt_vnta_m where co_empresa = ? and st_ocupado = 'S' and es_vigencia = 'Vigente'", [$empresa]);
        if(count($puntosventa) > 0) {
            $psventa = [];
            foreach($puntosventa as $pventa) {
                $psventa[] = [
                    "value" => (int) $pventa->co_punto_venta,
                    "text" => $pventa->de_nombre
                ];
            }
            return response()->json([
                "data" => compact("psventa"),
                "rqid" => KAMILL_PUNTOS_VENTA
            ]);
        }
        return response()->json([
            "error" => "No se asignaron puntos de venta",
            "rqid" => KAMILL_PUNTOS_VENTA
        ]);
    }
}