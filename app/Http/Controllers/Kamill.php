<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use DB;

class Kamill extends Controller {

    private $user;
    
    public function __construct() {
        define("URL_CONSULTA_DNI", "https://aplicaciones007.jne.gob.pe/srop_publico/Consulta/Afiliado/GetNombresCiudadano");
        define("URL_CONSULTA_RUC", "https://api.sunat.cloud/ruc/");
        define("KAMILL_LOGIN", 1);
        define("KAMILL_PUNTOS_VENTA", 2);
        define("KAMILL_VALIDAR_CLIENTE", 3);
        define("KAMILL_DATOS_CLIENTE", 4);
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

    public function validar_cliente(Request $request) {
        $empresa = $request->get("empresa");
        $rucdni = $request->get("rucdni");
        $vendedor = $request->get("vendedor");
        $ccostos = $request->get("ccostos");
        $alias = $request->get("alias");
        $out = DB::select("select * from table(pack_punto_venta.f_consulta_cliente(?,?))", [$empresa, $rucdni]);
        if(count($out == 0) || ((int) $out[0]->indic) == 10) {
            $existe = false;
            $ch = curl_init();
            if(strlen($rucdni) == 8) { //es dni
                curl_setopt($ch, CURLOPT_URL, URL_CONSULTA_DNI . "?DNI=" . $rucdni);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                $result = ucwords(ucwords(strtolower(curl_exec($ch)), "|"));
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                list($apepat, $apemat, $nombres) = explode("|", $result);
                $ncomercial = implode(" ", [$nombres, $apepat, $apemat]);
                $rsocial = $ncomercial;
            }
            else { //es ruc
                curl_setopt($ch, CURLOPT_URL, URL_CONSULTA_RUC . $rucdni);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                $result = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                $JsonData = json_decode($result);
                $ncomercial = $JsonData->nombre_comercial;
                $rsocial = $JsonData->razon_social;
                $nombres = "-";
                $apepat = "-";
                $apemat = "-";
                $fechanac = "";
                $email = "";
                $telefono = "";
            }
        }
        else {
            $existe = true;
            $ncomercial = $out[0]->de_nom_comer;
            $rsocial = $out[0]->de_razsoc;
            $nombres = $out[0]->nombres;
            $apepat = $out[0]->ape_pat;
            $apemat = $out[0]->ape_mat;
            $fechanac = $out[0]->fe_nac;
            $email = $out[0]->de_mail;
            $telefono = $out[0]->telf;
        }
        $cliente = compact("rucdni", "ncomercial", "rsocial", "nombres", "apepat", "apemat", "fechanac", "email", "telefono");
        return response()->json([
            "data" => compact("existe", "cliente"),
            "rqid" => KAMILL_VALIDAR_CLIENTE
        ]);
    }

    public function registra_cliente(Request $request) {
        $usuario = $request->get("usuario");
        $empresa = $request->get("empresa");
        $rucdni = $request->get("rucdni");
        $rsocial = $request->get("rsocial");
        $email = $request->get("email");
        $fechanac = $request->get("fechanac");
        $telefono = $request->get("telefono");
        $nombres = $request->get("nombres");
        $apepat = $request->get("apepat");
        $apemat = $request->get("apemat");
        //query pa guardar
    }

    public function datos_cliente(Request $request) {
        $empresa = $request->get("empresa");
        $vendedor = $request->get("vendedor");
        $ccostos = $request->get("ccostos");
        $rucdni = $request->get("rucdni");
        $alias = $request->get("alias");
        $datos = DB::select("select * from table(pack_punto_venta.f_carga_ptovta(?,?,?,?,?))", [$empresa, $vendedor, $ccostos, $rucdni, $alias]);
            $lisprecios = (int) $datos[0]->listado_precios;
            $serielista = (int) $datos[0]->serie_listado;
            $nomlista = $datos[0]->nom_listado;
        $info = compact("lisprecios", "serielista", "nomlista");
        return response()->json([
            "data" => compact("info"),
            "rqid" => KAMILL_DATOS_CLIENTE
        ]);
    }
}