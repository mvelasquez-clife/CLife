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
        define("KAMILL_LISTA_FAMILIAS", 5);
        define("KAMILL_LISTA_MARCAS", 6);
        define("KAMILL_LISTA_PRODUCTOS", 7);
        define("KAMILL_DATOS_PRODUCTO", 8);
        define("KAMILL_GENERA_TICKET", 9);
        define("KAMILL_NUMERO_TICKET", 10);
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
        $vendedor = $request->get("vendedor");
        $ccostos = $request->get("ccostos");
        $cliente = 151;
        $alias = $request->get("alias");
        //$puntosventa = DB::select("select co_punto_venta, de_nombre from vt_punt_vnta_m where co_empresa = ? and st_ocupado = 'S' and es_vigencia = 'Vigente'", [$empresa]);
        $puntosventa = DB::select("select
                pack.co_pto_venta,
                pack.listado_precios,
                pack.serie_listado,
                pack.nom_listado,
                vpvm.de_nombre
            from table(pack_punto_venta.f_carga_ptovta(?,?,?,?,?)) pack
                join vt_punt_vnta_m vpvm on vpvm.co_punto_venta = pack.co_pto_venta and vpvm.co_empresa = ?", [$empresa, $vendedor, $ccostos, $cliente, $alias, $empresa]);
        if(count($puntosventa) > 0) {
            $psventa = [];
            foreach($puntosventa as $pventa) {
                $psventa[] = [
                    "codigo" => (int) $pventa->co_pto_venta,
                    "nombre" => $pventa->de_nombre,
                    "listaprecios" => (int) $pventa->listado_precios,
                    "serielista" => (int) $pventa->serie_listado,
                    "nomlista" => $pventa->nom_listado
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
        $out = DB::select("select * from table(pack_punto_venta.f_consulta_cliente(?,?))", [$empresa, $rucdni]);
        if(count($out) == 0 || ((int) $out[0]->indic) == 10) {
            $existe = false;
            $ch = curl_init();
            if(strlen($rucdni) == 8) { //es dni
                /*
                // API YA NO FUNCIONA
                curl_setopt($ch, CURLOPT_URL, URL_CONSULTA_DNI . "?DNI=" . $rucdni);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                $output = curl_exec($ch);
                */
                $output = "?|?|?";
                list($apepat, $apemat, $nombres) = explode("|", $output);
                $ncomercial = ucwords(strtolower(implode(" ", [$nombres, $apepat, $apemat])));
                $rsocial = ucwords(strtolower($ncomercial));
                $codireccion = "";
            }
            else { //es ruc
                curl_setopt($ch, CURLOPT_URL, URL_CONSULTA_RUC . $rucdni);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                $output = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $JsonData = json_decode($output);
                $ncomercial = ucwords(strtolower($JsonData->nombre_comercial));
                $rsocial = ucwords(strtolower($JsonData->razon_social));
                $nombres = "-";
                $apepat = "-";
                $apemat = "-";
                $fechanac = "";
                $email = "";
                $telefono = "";
                $codireccion = "";
            }
            curl_close($ch);
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
            $codireccion = $out[0]->co_direccion;
        }
        $cliente = compact("rucdni", "ncomercial", "rsocial", "nombres", "apepat", "apemat", "fechanac", "email", "telefono", "codireccion");
        return response()->json([
            "data" => compact("existe", "cliente"),
            "rqid" => KAMILL_VALIDAR_CLIENTE
        ]);
    }

    public function registra_cliente(Request $request) {
        $usuario = $request->get("usuario");
        $empresa = $request->get("empresa");
        $rucdni = $request->get("rucdni");
        $tamanio = strlen($rucdni);
        $indicadorTipo = $tamanio == 8 ? "01" : "02";
        $rsocial = $request->get("rsocial");
        $email = $request->get("email");
        $fechanac = $request->get("fechanac");
        $telefono = $request->get("telefono");
        $nombres = $request->get("nombres");
        $apepat = $request->get("apepat");
        $apemat = $request->get("apemat");
        //valores por defecto
        $ubigeo = "177150131"; //san isidro
        $deubigeo = "San Isidro";
        $covia = 0;
        $devia = "-";
        $numvia = 0;
        $cozona = 0;
        $dezona = "-";
        $referencia = "-";
        $indicadorConsumidor = 0;
        $indicadorEstado = 10;
        $estado = "Vigente";
        $tpnegocio = 9;
        //query pa guardar
        $cadena= implode("@*@", [$usuario,$empresa,$rucdni,$rsocial,$ubigeo,$covia,$devia,$numvia,$cozona,$dezona,$referencia,$email,$tamanio,$indicadorConsumidor,$deubigeo,$fechanac,
            $indicadorEstado,$indicadorTipo,$telefono,$apepat,$apemat,$nombres,$estado,$tpnegocio]);
        $out = "";
        $procedure = "pack_punto_venta.sp_reg_cliente_nuevo_pack";
        $bindings = [
            "p1" => $cadena,
            "p2" => $out
        ];
        DB::executeProcedure($procedure, $bindings);
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
        $ticket = DB::select("select de_serie||'-'||nu_cantidad \"codigo\" from co_seri_nvo where co_serie = 830 and de_serie = 'OTK1'");
        $ticket = $ticket[0]->codigo;
        $info = compact("lisprecios", "serielista", "nomlista", "ticket");
        return response()->json([
            "data" => compact("info"),
            "rqid" => KAMILL_DATOS_CLIENTE
        ]);
    }

    public function lista_familias_producto(Request $request) {
        //modificacion: carga las marcas
        $empresa = $request->get("empresa");
        $lista = $request->get("colista");
        $serie = $request->get("serielista");
        $periodo = date("Ym");
        //
        $familias = DB::select("select distinct pack.co_marca \"value\",initcap(mmm.de_nombre) \"text\"
            from table(pack_punto_venta.f_list_prec_productos(?,?,?,?)) pack
                join ma_marc_m mmm on pack.co_marca = mmm.co_marca
            order by initcap(mmm.de_nombre) asc", [$empresa,$lista,$serie,$periodo]);
        return response()->json([
            "data" => compact("familias"),
            "rqid" => KAMILL_LISTA_FAMILIAS
        ]);
    }

    public function lista_marcas_producto(Request $request) {
        //modificacion: carga las submarcas
        $empresa = $request->get("empresa");
        $lista = $request->get("colista");
        $serie = $request->get("serielista");
        $periodo = date("Ym");
        $marca = $request->get("marca");
        //
        $marcas = DB::select("select distinct pack.co_submarca \"value\",initcap(msmm.de_nombre) \"text\"
            from table(pack_punto_venta.f_list_prec_productos(?,?,?,?)) pack
                join ma_sub_marc_m msmm on pack.co_marca = msmm.co_marca and pack.co_submarca = msmm.co_submarca
            where pack.co_marca = ?
            order by initcap(msmm.de_nombre) asc", [$empresa,$lista,$serie,$periodo,$marca]);
        return response()->json([
            "data" => compact("marcas"),
            "rqid" => KAMILL_LISTA_MARCAS
        ]);
    }

    public function lista_productos_familia(Request $request) {
        //carga los productos por marca y submarca
        $empresa = $request->get("empresa");
        $lista = $request->get("colista");
        $serie = $request->get("serielista");
        $periodo = date("Ym");
        $marca = $request->get("marca");
        $submarca = $request->get("submarca");
        $ls_productos = DB::select("select distinct
                co_catalogo_producto,
                initcap(de_nombre) de_nombre,
                cant,
                precio
            from table(pack_punto_venta.f_list_prec_productos(?,?,?,?))
            where co_marca = ? and co_submarca = ?
            order by initcap(de_nombre) asc", [$empresa,$lista,$serie,$periodo,$marca,$submarca]);
        $productos = [];
        foreach($ls_productos as $producto) {
            $productos[] = [
                "codigo" => $producto->co_catalogo_producto,
                "descripcion" => $producto->de_nombre,
                "stock" => (double) $producto->cant,
                "punit" => (double) $producto->precio
            ];
        }
        return response()->json([
            "data" => compact("productos"),
            "rqid" => KAMILL_LISTA_PRODUCTOS
        ]);
    }

    public function consulta_producto(Request $request) {
        $empresa = $request->get("empresa");
        $periodo = date("Ym");
        $serielista = $request->get("slista");
        $ptventa = $request->get("ptventa");
        $listaprecios = $request->get("listaprecios");
        $producto = $request->get("producto");
        $fnacimiento = $request->get("fnacimiento");
        $rucdni = $request->get("rucdni");
        $blogger = $request->get("blogger");
        $datos = DB::select("select co_catalogo_prod,icbper,nombre,unid,cant,precio,desc1,descu,nom_des from table(pack_punto_venta.f_consulta_prod(?,?,?,?,?,?,?,?,?))", 
        [$empresa,$periodo,$serielista,$ptventa,$listaprecios,$producto,$fnacimiento,$rucdni,$blogger]);
        if(count($datos) > 0) {
            $info = $datos[0];
            $info->cant = (int) $info->cant;
            $info->descu = (int) $info->descu;
            $info->desc1 = (int) $info->desc1;
            $info->precio = (double) $info->precio;
            //verifica los descuentos
            if ($info->descu > 0) {
                $info->precio = round($info->precio * (100 - $info->descu) / 100,1);
                $info->dscto = $info->descu;
            }
            else {
                if ($info->desc1 > 0) {
                    $info->precio = round($info->precio * (100 - $info->desc1) / 100,1);
                    $info->dscto = $info->desc1;
                    $info->nom_des = "Por lista de precios";
                }
                else {
                    $info->dscto = 0;
                }
            }
            return response()->json([
                "rqid" => KAMILL_DATOS_PRODUCTO,
                "data" => compact("info")
            ]);
        }
        return response()->json([
            "error" => "No se encontró el producto",
            "rqid" => KAMILL_DATOS_PRODUCTO
        ]);
    }

    public function genera_ticket(Request $request) {
        $alias = $request->get("alias");
        $oldticket = $request->get("ticket");
        $ticket = DB::select("select de_serie||'-'||nu_cantidad \"codigo\" from co_seri_nvo where co_serie = 830 and de_serie = 'OTK1'");
        $ticket = $ticket[0]->codigo;
        $cabecera = str_replace($oldticket, $ticket, $request->get("cabecera"));
        $productos = str_replace($oldticket, $ticket, $request->get("detalle"));
        $cantidad = $request->get("cantidad");
        //ejecutar con pdo
        $conexion = DB::connection()->getPdo();
        try {
            $pdo = DB::getPdo();
            $stmt = $pdo->prepare("call pack_venta.sm_activar_empresa(:p_alias)");
                $stmt->bindParam(":p_alias", $alias, \PDO::PARAM_STR);
            $stmt->execute();
            //siguiente query
            $pdo = DB::getPdo();
            $stmt = $pdo->prepare("call pack_punto_venta.sp_factura_pto_vta_com_bonus(:p_cabecera,:p_detalle,:p_cantidad)");
                $stmt->bindParam(":p_cabecera", $cabecera, \PDO::PARAM_STR);
                $stmt->bindParam(":p_detalle", $productos, \PDO::PARAM_STR);
                $stmt->bindParam(":p_cantidad", $cantidad, \PDO::PARAM_INT);
            $stmt->execute();
            //calcula el nuevo ticket
            $newticket = DB::select("select de_serie||'-'||nu_cantidad \"codigo\" from co_seri_nvo where co_serie = 830 and de_serie = 'OTK1'");
            $newticket = $newticket[0]->codigo;
            //escribe la salida
            return response()->json([
                "data" => compact("ticket", "newticket"),
                "rqid" => KAMILL_GENERA_TICKET
            ]);
        }
        catch(\PDOException $e) {
            return $e;
        }
    }

    public function numero_ticket(Request $request) {
        $ticket = DB::select("select de_serie||'-'||nu_cantidad \"codigo\" from co_seri_nvo where co_serie = 830 and de_serie = 'OTK1'");
        $ticket = $ticket[0]->codigo;
        return response()->json([
            "data" => compact("ticket"),
            "rqid" => KAMILL_NUMERO_TICKET
        ]);
    }
}