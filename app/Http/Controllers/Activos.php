<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use JWTAuth;
use App\User as User;
use JWTAuthException;
use DB;

class Activos extends Controller {

    private $user;
    
    public function __construct(User $user) {
        $this->user = $user;
        $this->middleware("jwt.auth")->except(["login", "registro", "upload_img", "pruebas"]);
        //
        define("STRING_SEPARATOR", "|");
        //
        define("REQ_ACT_LOGIN", 1);
        define("REQ_ACT_REGISTRO", 2);
        define("REQ_ACT_LISTA_INVENTARIOS", 3);
        define("REQ_ACT_LISTA_SEDES", 4);
        define("REQ_ACT_LISTA_DEPARTAMENTOS", 5);
        define("REQ_ACT_LISTA_OFICINAS", 6);
        define("REQ_ACT_NVO_INV_INICIA", 7);
        define("REQ_ACT_SUBCLASES", 8);
        define("REQ_ACT_RESPONSABLES", 9);
        define("REQ_ACT_SECCION", 10);
        define("REQ_ACT_NVO_INV_GUARDA", 11);
        define("REQ_ACT_GENERA_ETIQUETA", 12);
        define("REQ_ACT_INFO_ACTIVO", 13);
        define("REQ_ACT_ACT_INV_TOMA", 14);
        define("REQ_ACT_FALTANTES", 15);
        define("REQ_ACT_CIERRA_OFICINA", 16);
        //
        define("REQ_ACT_USER", 999);
    }
    
    public function login(Request $request) {
        $alias = $request->get("alias");
        $password = $request->get("password");
        $sg_usuario = DB::table("sg_usua_m")->where("de_alias", $alias)->select("co_usuario as codigo", "co_empresa_usuario as empresa")->first();
        if($sg_usuario) {
            $co_cliente = $sg_usuario->codigo;
            $co_empresa = $sg_usuario->empresa;
            $credentials = compact("co_cliente", "co_empresa", "password");
            $token = null;
            try {
                if (!$token = JWTAuth::attempt($credentials)) {
                    //return response()->json(['invalid_email_or_password'], 422);
                    return response()->json([
                        "status" => false,
                        "message" => "El usuario no está habilitado para acceder al sistema",
                        "rqid" => REQ_ACT_LOGIN
                    ], 422);
                }
            }
            catch (JWTAuthException $e) {
                return response()->json(['failed_to_create_token'], 500);
            }
            $usuario = User::find($sg_usuario->codigo);
            return response()->json([
                "status" => true,
                "data" => compact("usuario", "token"),
                "rqid" => REQ_ACT_LOGIN
            ]);
        }
        return response()->json([
            "status" => false,
            "message" => "El usuario ingresado no existe",
            "rqid" => REQ_ACT_LOGIN
        ]);
    }
   
    public function registro(Request $request) {
        $alias = $request->get("alias");
        $personal = DB::table("sg_usua_m")
            ->where("de_alias", $alias)
            ->select(
                "co_usuario as codigo",
                "co_empresa_usuario as empresa",
                "de_alias as alias",
                "de_nombre as nombre",
                "de_correo as email",
                DB::raw("nvl(de_telefono,'-') as telefono"),
                "de_clave_sistema as clave"
            )
            ->first();
        if($personal) {
            $encontrados = DB::table("cl_usuarios")
                ->where("co_cliente", $personal->codigo)
                ->where("co_empresa", $personal->empresa)
                ->count();
            if($encontrados == 0) {
                DB::table("cl_usuarios")->insert([
                    "co_cliente" => $personal->codigo,
                    "co_empresa" => $personal->empresa,
                    "de_nombre_comercial" => $personal->alias,
                    "de_razon_social" => $personal->nombre,
                    "co_rucdni" => $personal->codigo,
                    "de_email" => $personal->email,
                    "de_telefono" => $personal->telefono,
                    "password" => bcrypt($personal->clave),
                    "st_cuenta_activada" => "S",
                ]);
            }
            $usuario = User::find($personal->codigo);
            return response()->json([
                "status" => true,
                "rqid" => REQ_ACT_REGISTRO,
                "data" => compact("usuario")
            ]);
        }
        return response()->json([
            "status" => false,
            "rqid" => REQ_ACT_REGISTRO,
            "message" => "Usuario no existe"
        ]);
    }

    public function ls_inventarios(Request $request) {
        $user = JWTAuth::toUser($request->get("token"));
        $alias = $user->de_nombre_comercial;
        DB::statement("call pack_venta.sm_activar_empresa(?)", [$alias]);
        $inventarios = DB::select("select
                af.nu_inventario value,
                'Inventario No ' || af.nu_inventario || '-' || af.co_periodo text
            from lo_acti_fijo_inven_c af
                join temp_ma_empresa ma on af.co_empresa = ma.co_empresa and af.co_periodo = substr(ma.co_periodo,0,4)
            where af.co_estado = ?", ["ABIERTO"]);
        $inventarios = DB::select("select af.nu_inventario value,'Inventario No ' || af.nu_inventario || '-' || af.co_periodo text from lo_acti_fijo_inven_c af join temp_ma_empresa ma on af.co_empresa = ma.co_empresa");
        return response()->json([
            "status" => true,
            "rqid" => REQ_ACT_LISTA_INVENTARIOS,
            "data" => compact("inventarios")
        ]);
    }

    public function ls_sedes(Request $request) {
        $user = JWTAuth::toUser($request->get("token"));
        $sedes = DB::table("ma_empresas_sedes_m")
            ->where("co_empresa", $user->co_empresa)
            ->where("es_vigencia", "VIGENTE")
            ->where("co_sede", ">", 0)
            ->select("co_sede as value", "de_sede as text")
            ->orderBy("de_sede", "asc")
            ->get();
        $departamentos = DB::table("ma_empresas_dptos_m")
            ->where("co_empresa", $user->co_empresa)
            ->where("es_vigencia", "VIGENTE")
            ->where("co_dpto", ">", 0)
            ->select("co_dpto as value","de_dpto as text")
            ->orderBy("de_dpto", "asc")
            ->get();
        return response()->json([
            "status" => true,
            "rqid" => REQ_ACT_LISTA_SEDES,
            "data" => compact("sedes", "departamentos")
        ]);
    }

    public function ls_departamentos(Request $request) {
        $user = JWTAuth::toUser($request->get("token"));
        $sede = $request->get("sede");
        $departamentos = DB::select("select
                medm.co_dpto value,
                medm.de_dpto text
            from ma_empresas_dptos_m medm
                join ma_empresas_ofic_m meom on meom.co_empresa = medm.co_empresa and meom.co_dpto = medm.co_dpto
            where medm.co_empresa = ?
                and medm.es_vigencia = 'VIGENTE'
                and medm.co_dpto > 0
                and meom.co_sede = ?
            group by medm.co_dpto, medm.de_dpto
            having count(meom.co_oficina) > 0
            order by medm.de_dpto asc", [$user->co_empresa, $sede]);
        return response()->json([
            "status" => true,
            "rqid" => REQ_ACT_LISTA_DEPARTAMENTOS,
            "data" => compact("departamentos")
        ]);
    }

    public function ls_oficinas(Request $request) {
        $user = JWTAuth::toUser($request->get("token"));
        $sede = $request->get("sede");
        $departamento = $request->get("departamento");
        $oficinas = DB::table("ma_empresas_ofic_m")
            ->where("co_empresa", $user->co_empresa)
            ->where("es_vigencia", "VIGENTE")
            ->where("co_sede", $sede)
            ->where("co_dpto", $departamento)
            ->where("co_dpto", ">", 0)
            ->select("co_oficina as value","de_oficina as text")
            ->orderBy("de_oficina", "asc")
            ->get();
        return response()->json([
            "status" => true,
            "rqid" => REQ_ACT_LISTA_OFICINAS,
            "data" => compact("oficinas")
        ]);
    }

    public function ls_nvo_inventario_inicia(Request $request) {
        $clases = DB::table("ma_clasifica_activo")
            ->select("co_clasifica as value", "de_clasifica as text")
            ->orderBy("de_clasifica", "asc")
            ->get();
        $subclases = count($clases) > 0 ? DB::table("ma_subclasifica_activo")
            ->select("co_subclasifica as value", "de_subclasifica as text")
            ->where("co_clasifica", $clases[0]->value)
            ->orderBy("de_subclasifica", "asc")
            ->get() : [];
        $arrConservacion = ["BUENO", "REGULAR", "MALO"];
        $conservacion = [];
        foreach($arrConservacion as $estado) {
            $iEstado = new \stdClass();
                $iEstado->value = $estado;
                $iEstado->text = $estado;
            $conservacion[] = $iEstado;
        }
        $utilidad = DB::table("lo_estado_util_acti_fijo_m")
            ->where("es_vigencia", "Vigente")
            ->select("co_estado_util as value", "de_estado_util as text")
            ->orderBy("de_estado_util", "asc")
            ->get();
        return response()->json([
            "status" => true,
            "rqid" => REQ_ACT_NVO_INV_INICIA,
            "data" => compact("clases","subclases","conservacion","utilidad")
        ]);
    }

    public function ls_subclases(Request $request) {
        $clase = $request->get("clase");
        $subclases = DB::table("ma_subclasifica_activo")
            ->select("co_subclasifica as value", "de_subclasifica as text")
            ->where("co_clasifica", $clase)
            ->orderBy("de_subclasifica", "asc")
            ->get();
        return response()->json([
            "status" => true,
            "rqid" => REQ_ACT_SUBCLASES,
            "data" => compact("subclases")
        ]);
    }

    public function ls_busca_usuarios(Request $request) {
        $user = JWTAuth::toUser($request->get("token"));
        $texto = strtoupper("%" . $request->get("texto") . "%");
        $usuarios = DB::table("sg_usua_m")
            ->select("co_usuario as value", "de_nombre as text")
            ->where("es_vigencia", "Vigente")
            ->where(function($sql) use($texto) {
                $sql->where(DB::raw("upper(de_nombre)"), "like", $texto)
                    ->orWhere("co_usuario", "like", $texto);
            })
            ->orderBy("de_nombre", "asc")
            ->get();
        return response()->json([
            "status" => true,
            "rqid" => REQ_ACT_RESPONSABLES,
            "data" => compact("usuarios")
        ]);
    }

    public function ls_responsables_activo(Request $request) {
        $user = JWTAuth::toUser($request->get("token"));
        $seccion = $request->get("seccion");
        $responsables = DB::table("ma_empresas_ofic_m as ofic")
            ->join("ma_cata_enti_m as enti", "enti.co_catalogo_entidad", "=", "ofic.co_responsable")
            ->where("ofic.co_empresa", $user->co_empresa)
            ->where("ofic.co_oficina", $seccion)
            ->select("ofic.co_responsable as value", "enti.de_razon_social as text")
            ->orderBy("enti.de_razon_social", "asc")
            ->get();
        return response()->json([
            "status" => true,
            "rqid" => REQ_ACT_SECCION,
            "data" => compact("responsables")
        ]);

    }

    public function sv_inventario_inicial(Request $request) {
        ini_set("max_execution_time", 300);
        $user = JWTAuth::toUser($request->get("token"));
        $inventario = $request->get("inventario");
        DB::statement("call pack_venta.sm_activar_empresa(?)", [$user->de_nombre_comercial]);
        $nu_catalogo = DB::table("ma_cata_acti_fijo_m")
            ->where("co_empresa", $user->co_empresa)
            ->select(DB::raw("nvl(max(nu_catalogo_activo),0) as catalogo"))
            ->first();
        $nu_catalogo = $nu_catalogo->catalogo + 1;
        if(strcmp($request->get("sresponsable"),"S") == 0) {
            DB::table("ma_cata_acti_fijo_m")->insert([
                "co_empresa" => $user->co_empresa,
                "nu_catalogo_activo" => $nu_catalogo,
                "co_clasifica" => $request->get("clase"),
                "co_subclasifica" => $request->get("subclase"),
                "de_nombre" => $request->get("nombre"),
                "co_serie" => $request->get("serie"),
                "de_otras_caract" => $request->get("caracteristicas"),
                "co_usu_registra" => $user->co_cliente,
                "fe_registra" => date("Y-m-d H:i:s"),
                "co_oficina_empresa" => $request->get("sede"),
                "co_area_ubicacion" => $request->get("departamento"),
                "co_seccion_empresa" => $request->get("oficina"),
                "nu_periodos_depreciados" => 0,
                "de_marca" => $request->get("marca"),
                "de_modelo" => $request->get("modelo"),
                "co_estado_conserva_ini" => $request->get("esconserva"),
                "co_estado_util_ini" => $request->get("esutilidad"),
                "co_responsable_uso" => $request->get("responsable"),
                "de_nomfile_qr" => $nu_catalogo . ".jpg",
                "st_tipo_ingreso" => "MOVIL"
            ]);
        }
        else {
            DB::table("ma_cata_acti_fijo_m")->insert([
                "co_empresa" => $user->co_empresa,
                "nu_catalogo_activo" => $nu_catalogo,
                "co_clasifica" => $request->get("clase"),
                "co_subclasifica" => $request->get("subclase"),
                "de_nombre" => $request->get("nombre"),
                "co_serie" => $request->get("serie"),
                "de_otras_caract" => $request->get("caracteristicas"),
                "co_usu_registra" => $user->co_cliente,
                "fe_registra" => date("Y-m-d H:i:s"),
                "co_oficina_empresa" => $request->get("sede"),
                "co_area_ubicacion" => $request->get("departamento"),
                "co_seccion_empresa" => $request->get("oficina"),
                "nu_periodos_depreciados" => 0,
                "de_marca" => $request->get("marca"),
                "de_modelo" => $request->get("modelo"),
                "co_estado_conserva_ini" => $request->get("esconserva"),
                "co_estado_util_ini" => $request->get("esutilidad"),
                "de_nomfile_qr" => $nu_catalogo . ".jpg",
                "st_tipo_ingreso" => "MOVIL"
            ]);
        }
        $co_catalogo = DB::table("ma_cata_acti_fijo_m")
            ->where("nu_catalogo_activo", $nu_catalogo)
            ->where("co_empresa", $user->co_empresa)
            ->select("co_catalogo_activo as codigo")
            ->first()
            ->codigo;
        $periodo = DB::table("temp_ma_empresa")
            ->select(DB::raw("substr(co_periodo,0,4) as periodo"))
            ->first();
        //
        if(strcmp($request->get("aresponsable"), "x") != 0) {
            DB::table("lo_acti_fijo_inven_d")->insert([
                "co_empresa" => $user->co_empresa,
                "nu_inventario" => $inventario,
                "co_periodo" => $periodo->periodo,
                "nu_catalogo_activo" => $nu_catalogo,
                "co_oficina" => $request->get("oficina"),
                "co_usu_toma_invent" => $user->co_cliente,
                "fe_toma_inventario" => date("Y-m-d H:i:s"),
                "co_estado_inventario_ofic" => "ABIERTO",
                "co_usuario" => $user->co_cliente,
                "fe_registro" => date("Y-m-d H:i:s"),
                "flg_faltante" => "N",
                "co_estado_conserva" => "BUENO",
                "co_estado_utilidad" => 1,
                "co_responsable_area" => $request->get("aresponsable"),
                "co_responsable_uso" => $request->get("responsable"),
                "flg_mover_a_nueva_oficina" => "N"
            ]);
        }
        else {
            DB::table("lo_acti_fijo_inven_d")->insert([
                "co_empresa" => $user->co_empresa,
                "nu_inventario" => $inventario,
                "co_periodo" => $periodo->periodo,
                "nu_catalogo_activo" => $nu_catalogo,
                "co_oficina" => $request->get("oficina"),
                "co_usu_toma_invent" => $user->co_cliente,
                "fe_toma_inventario" => date("Y-m-d H:i:s"),
                "co_estado_inventario_ofic" => "ABIERTO",
                "co_usuario" => $user->co_cliente,
                "fe_registro" => date("Y-m-d H:i:s"),
                "flg_faltante" => "N",
                "co_estado_conserva" => "BUENO",
                "co_estado_utilidad" => 1,
                "co_responsable_uso" => $request->get("responsable"),
                "flg_mover_a_nueva_oficina" => "N"
            ]);
        }
        if($request->has("imagenes")) {
            $imagenes = $request->get("imagenes");
            $base_path = implode(DIRECTORY_SEPARATOR, [env("APP_FILES_PATH"), $user->co_cliente]);
            @mkdir($base_path, 0777, true);
            foreach($imagenes as $idx => $encoded_image) {
                $img = base64_decode($encoded_image);
                $filename = $nu_catalogo . "_" . $idx . ".jpg";
                file_put_contents($base_path . DIRECTORY_SEPARATOR . $filename, $img);
                //subir al servidor compartido
                $source_directory = implode(DIRECTORY_SEPARATOR, [env("APP_FILES_PATH"), $user->co_cliente, $filename]);
//comentar desde aqui
                $destination_directory = DB::select("select f_obtener_ruta_imag_trim('ACTIVOFIJO',?,null,null,'',?) dir from dual",[$user->co_empresa,$co_catalogo]);
                $destination_directory = str_replace("public", "publico", $destination_directory[0]->dir);
                $ftp_connection = ftp_connect(env("FTP_HOST"));
                if($ftp_connection) {
                    $connection_result = ftp_login($ftp_connection, env("FTP_USER"), env("FTP_PASSWORD"));
                    ftp_pasv($ftp_connection, true);
                    $vDirs = explode("/", $destination_directory);
                    $sizeDirs = count($vDirs);
                    for($i = 0; $i < $sizeDirs - 1; $i++) {
                        $newDir = "";
                        for($j = 0; $j <= $i; $j++) {
                            $newDir .= (($j == 0 ? "" : "/") . $vDirs[$j]);
                        }
                        @ftp_mkdir($ftp_connection, $newDir);
                    }
                    $path = $destination_directory;
                    $destination_directory = $destination_directory . $filename;
                    $upload = ftp_put($ftp_connection, $destination_directory, $source_directory, FTP_BINARY);
                    if($upload) {
                        //$this->modelws->registra_imagen($emp,$co_catalogo,$filename,$usr,$filename);
                        DB::statement("call web_logistica.sp_registra_archivo_det(?,?,?,?,?)",[$user->co_empresa,$co_catalogo,$filename,$user->co_cliente,$filename]);
                        $message = "Registro almacenado!";
                    }
                    else $message = "<p>Se registró la toma, pero no se pudo enviar la imagen</p>";
                }
//hasta aqui
            }
        }
        return response()->json([
            "status" => true,
            "rqid" => REQ_ACT_NVO_INV_GUARDA,
            "data" => [
                "catalogo" => (int) $nu_catalogo,
                "cliente" => $user->co_cliente,
                "empresa" => $user->co_empresa
            ]
        ]);
    }

    public function upload_img(Request $request) {
        $encoded_image = $request->get("base64");
        $cliente = $request->get("cliente");
        $nu_catalogo = $request->get("catalogo");
        $empresa = $request->get("empresa");
        $base_path = implode(DIRECTORY_SEPARATOR, [env("APP_FILES_PATH"), $cliente]);
        @mkdir($base_path, 0777, true);
//        foreach($imagenes as $idx => $encoded_image) {
            $img = base64_decode($encoded_image);
            $filename = $nu_catalogo . "_" . $idx . ".jpg";
            file_put_contents($base_path . DIRECTORY_SEPARATOR . $filename, $img);
            //subir al servidor compartido
            $source_directory = implode(DIRECTORY_SEPARATOR, [env("APP_FILES_PATH"), $cliente, $filename]);
//comentar desde aqui
            $destination_directory = DB::select("select f_obtener_ruta_imag_trim('ACTIVOFIJO',?,null,null,'',?) dir from dual",[$empresa,$co_catalogo]);
            $destination_directory = str_replace("public", "publico", $destination_directory[0]->dir);
            $ftp_connection = ftp_connect(env("FTP_HOST"));
            if($ftp_connection) {
                $connection_result = ftp_login($ftp_connection, env("FTP_USER"), env("FTP_PASSWORD"));
                ftp_pasv($ftp_connection, true);
                $vDirs = explode("/", $destination_directory);
                $sizeDirs = count($vDirs);
                for($i = 0; $i < $sizeDirs - 1; $i++) {
                    $newDir = "";
                    for($j = 0; $j <= $i; $j++) {
                        $newDir .= (($j == 0 ? "" : "/") . $vDirs[$j]);
                    }
                    @ftp_mkdir($ftp_connection, $newDir);
                }
                $path = $destination_directory;
                $destination_directory = $destination_directory . $filename;
                $upload = ftp_put($ftp_connection, $destination_directory, $source_directory, FTP_BINARY);
                if($upload) {
                    //$this->modelws->registra_imagen($emp,$co_catalogo,$filename,$usr,$filename);
                    $message = "Registro almacenado!";
                }
                else $message = "<p>Se registró la toma, pero no se pudo enviar la imagen</p>";
            }
//hasta aqui
//        }
    }

    public function dt_genera_etiqueta(Request $request) {
        $user = JWTAuth::toUser($request->get("token"));
        $inventario = $request->get("inventario");
        $nucatalogo = $request->get("catalogo");
        DB::statement("call pack_venta.sm_activar_empresa(?)",[$user->de_nombre_comercial]);
        $periodo = DB::table("temp_ma_empresa")
            ->select(DB::raw("substr(co_periodo,0,4) as periodo"))
            ->first();
        $data = DB::select("select * from table(pack_new_activos.f_datos_activo(?,?,?,?))", [$user->co_empresa,$inventario,$nucatalogo,$periodo->periodo]);
        if(count($data) > 0) {
            require_once base_path() . "/vendor/phpqrcode/qrlib.php";
            require_once base_path() . "/vendor/phpqrcode/code128.class.php";
            ini_set('error_reporting', E_ALL & ~E_NOTICE & ~E_WARNING & ~E_STRICT & ~E_DEPRECATED);
            ini_set('display_errors','Off');
            //paths
            $base_path = env("APP_FILES_PATH") . DIRECTORY_SEPARATOR . "temp";
            //go el codigo
            $data = $data[0];
            $texto_qr = "Codigo : [" . $data->catalogo . "]~r~nDescripcion: " . $data->nombre . "~r~nUbicacion: " . $data->oficina . "~r~nFactura : " . $data->factura;
            $img_prefix = date("YmdHis");
            $tmp_qr_path = implode(DIRECTORY_SEPARATOR, [$base_path, "qr_" . $img_prefix . ".png"]);
            $tmp_c128_path = implode(DIRECTORY_SEPARATOR, [$base_path, "c128_" . $img_prefix . ".png"]);
            $tmp_etq_path = implode(DIRECTORY_SEPARATOR, [$base_path, $img_prefix . ".jpg"]);
//$tmp_bmp_path = implode(DIRECTORY_SEPARATOR, [$base_path, $img_prefix . ".bmp"]);
            $verdanab = implode(DIRECTORY_SEPARATOR, [base_path(),"vendor","phpqrcode","font","verdanab.ttf"]);
            $verdana = implode(DIRECTORY_SEPARATOR, [base_path(),"vendor","phpqrcode","font","verdana.ttf"]);
            $arial = implode(DIRECTORY_SEPARATOR, [base_path(),"vendor","phpqrcode","font","arial.ttf"]);
            $arialn = implode(DIRECTORY_SEPARATOR, [base_path(),"vendor","phpqrcode","font","arialn.ttf"]);
            $texto_qr = "Codigo : [" . $data->catalogo . "]~r~nDescripcion: " . $data->nombre . "~r~nUbicacion: " . $data->oficina . "~r~nFactura : " . $data->factura;
            $filename = $data->catalogo . ".jpg";
            //procesar
            \QRcode::png($texto_qr, $tmp_qr_path, QR_ECLEVEL_L, 4);
            $img_qrcode = imagecreatefrompng($tmp_qr_path);
            list($qr_ancho, $qr_alto) = getimagesize($tmp_qr_path);
            $etiqueta = imagecreate(580, 180);
            imagecolorallocate($etiqueta, 255, 255, 255);
            $color_texto = imagecolorallocate($etiqueta, 0, 0, 0);
            imagecopyresampled($etiqueta,$img_qrcode,10,10,0,0,160,160,$qr_ancho,$qr_alto);
            imagettftext($etiqueta, 14, 0, 180, 30, $color_texto, $verdanab, "INVENTARIO " . $data->periodo . " - " . $data->inventario);
            imagettftext($etiqueta, 10, 0, 460, 28, $color_texto, $verdana, $data->fregistro);
            imagettftext($etiqueta, 14, 0, 180, 60, $color_texto, $arial, $data->catalogo);
            imagettftext($etiqueta, 12, 0, 400, 60, $color_texto, $arialn, $data->oficina);
            imagettftext($etiqueta, 14, 0, 180, 80, $color_texto, $arial, $data->nombre);
            imagettftext($etiqueta, 14, 0, 180, 100, $color_texto, $arial, $data->observaciones);
            //
            imagettftext($etiqueta, 14, 0, 180, 130, $color_texto, $arial, "MARCA");
            imagettftext($etiqueta, 14, 0, 180, 150, $color_texto, $arial, "MODELO");
            imagettftext($etiqueta, 14, 0, 180, 170, $color_texto, $arial, "SERIE");
            imagettftext($etiqueta, 14, 0, 300, 130, $color_texto, $arial, $data->marca);
            imagettftext($etiqueta, 14, 0, 300, 150, $color_texto, $arial, $data->modelo);
            imagettftext($etiqueta, 14, 0, 300, 170, $color_texto, $arial, $data->serie);
            imagejpeg($etiqueta, $tmp_etq_path);
//
            $conexion = DB::connection()->getPdo();
            $image = file_get_contents($tmp_qr_path);
            $sql = "insert into ma_cata_acti_fijo_img(co_empresa,nu_catalogo_activo,co_tipo_img,imagen)
                values(" . $user->co_empresa . "," . $nucatalogo . ",0,empty_blob())
                returning image into :image";
            $result = oci_parse($conexion, $sql);
            $blob = oci_new_descriptor($conexion, OCI_D_LOB);
            oci_bind_by_name($result, ":image", $blob, -1, OCI_B_BLOB);
            oci_execute($result, OCI_DEFAULT) or die ("Unable to execute query");
//fin
            $imgdata = file_get_contents($tmp_etq_path);
            $base64 = base64_encode($imgdata);
            //subir al servidor compartido
            $source_directory = $tmp_etq_path;
            $destination_directory = DB::select("select f_obtener_ruta_imag_trim('ACTIVOFIJO',?,null,null,'',?) dir from dual",[$user->co_empresa,$co_catalogo]);
            $destination_directory = str_replace("public", "publico", $destination_directory[0]->dir);
            //
            $ftp_connection = ftp_connect(env("FTP_HOST"));
            if($ftp_connection) {
                $connection_result = ftp_login($ftp_connection, env("FTP_USER"), env("FTP_PASSWORD"));
                ftp_pasv($ftp_connection, true);
                $vDirs = explode("/", $destination_directory);
                $sizeDirs = count($vDirs);
                for($i = 0; $i < $sizeDirs - 1; $i++) {
                    $newDir = "";
                    for($j = 0; $j <= $i; $j++) {
                        $newDir .= (($j == 0 ? "" : "/") . $vDirs[$j]);
                    }
                    @ftp_mkdir($ftp_connection, $newDir);
                }
                $destination_directory = $destination_directory . $filename;
                $upload = ftp_put($ftp_connection, $destination_directory, $source_directory, FTP_BINARY);
                //guarda blob del qr en la bd
                $jar_file = env("APP_JAR_PATH");
                $exec_string = "java -jar \"" . $jar_file . "\" \"" . $tmp_qr_path . "\" " . $user->co_empresa . " " . $nucatalogo;
                exec($exec_string);
                return response()->json([
                    "status" => true,
                    "data" => [
                        "qrcode" => $texto_qr,
                        "image" => $base64,
                        "activo" => $data,
//                        "path" => $destination_bmp
                    ],
                    "rqid" => REQ_ACT_GENERA_ETIQUETA
                ]);
            }
            return response()->json([
                "status" => false,
                "rqid" => REQ_ACT_GENERA_ETIQUETA,
                "message" => "No se pudo conectar con el servidor FTP"
            ]);
        }
        return response()->json([
            "status" => false,
            "rqid" => REQ_ACT_GENERA_ETIQUETA,
            "message" => "Parámetros incorrectos"
        ]);
    }

    public function ls_info_activo(Request $request) {
        $user = JWTAuth::toUser($request->get("token"));
        $inventario = $request->get("inventario");
        $activo = $request->get("activo");
        DB::statement("call pack_venta.sm_activar_empresa(?)",[$user->de_nombre_comercial]);
        $periodo = DB::table("temp_ma_empresa")
            ->select(DB::raw("substr(co_periodo,0,4) as periodo"))
            ->first();
        $info = DB::select("select * from table(pack_new_activos.f_datos_activo_cact(?,?,?,?))",[$user->co_empresa,$inventario,$activo,$periodo->periodo]);
        if(count($info) > 0) {
            $info = $info[0];
            $arrConservacion = ["BUENO", "REGULAR", "MALO"];
            $conservacion = [];
            foreach($arrConservacion as $estado) {
                $iEstado = new \stdClass();
                    $iEstado->value = $estado;
                    $iEstado->text = $estado;
                $conservacion[] = $iEstado;
            }
            $utilidad = DB::table("lo_estado_util_acti_fijo_m")
                ->where("es_vigencia", "Vigente")
                ->select("co_estado_util as value", "de_estado_util as text")
                ->orderBy("de_estado_util", "asc")
                ->get();
            return response()->json([
                "status" => true,
                "rqid" => REQ_ACT_INFO_ACTIVO,
                "data" => compact("info","conservacion","utilidad")
            ]);
        }
        return response()->json([
            "status" => false,
            "rqid" => REQ_ACT_INFO_ACTIVO,
            "message" => "No hay información del activo seleccionado"
        ]);
    }

    public function sv_guarda_toma(Request $request) {
        $user = JWTAuth::toUser($request->get("token"));
        $inventario = $request->get("inventario");
        $catalogo = $request->get("catalogo");
        DB::statement("call pack_venta.sm_activar_empresa(?)",[$user->de_nombre_comercial]);
        $periodo = DB::table("temp_ma_empresa")
            ->select(DB::raw("substr(co_periodo,0,4) as periodo"))
            ->first();
        $cantidad = DB::table("lo_acti_fijo_toma_inven as afi")
            ->join("temp_ma_empresa as tm", function($join) use($periodo) {
                $join->on("afi.co_empresa", "=", "tm.co_empresa")
                    ->on("afi.co_periodo", "=", DB::raw($periodo->periodo));
            })
            ->where("afi.nu_inventario", $inventario)
            ->where("co_catalogo_activo", $catalogo)
            ->count();
        $sresponsable = $request->get("sresponsable");
        if($cantidad == 0) {
            if(strcmp($sresponsable,"S") == 0) {
                DB::table("lo_acti_fijo_toma_inven")->insert([
                    "co_empresa" => $user->co_empresa,
                    "co_periodo" => $periodo->periodo,
                    "nu_inventario" => $inventario,
                    "co_catalogo_activo" => $catalogo,
                    "co_responsable_uso" => $request->get("responsable"),
                    "co_estado_conserva" => $request->get("conserva"),
                    "co_estado_utilidad" => $request->get("utilidad"),
                    "fe_registro" => date("Y-m-d H:i:s"),
                    "co_oficina_empresa" => $request->get("oficina"),
                    "co_usu_toma_invent" => $user->co_cliente,
                    "co_usuario" => $user->co_cliente
                ]);
            }
            else {
                DB::table("lo_acti_fijo_toma_inven")->insert([
                    "co_empresa" => $user->co_empresa,
                    "co_periodo" => $periodo->periodo,
                    "nu_inventario" => $inventario,
                    "co_catalogo_activo" => $catalogo,
                    "co_estado_conserva" => $request->get("conserva"),
                    "co_estado_utilidad" => $request->get("utilidad"),
                    "fe_registro" => date("Y-m-d H:i:s"),
                    "co_oficina_empresa" => $request->get("oficina"),
                    "co_usu_toma_invent" => $user->co_cliente,
                    "co_usuario" => $user->co_cliente
                ]);
            }
            return response()->json([
                "status" => true,
                "rqid" => REQ_ACT_ACT_INV_TOMA,
                "data" => [
                    "result" => "ok",
                    "message" => "Se registró el activo"
                ]
            ]);
        }
        $forzar = strcmp($request->get("forzar"),"S") == 0;
        if($forzar) {
            if(strcmp($sresponsable,"S") == 0) {
                DB::table("lo_acti_fijo_toma_inven")
                    ->where("co_empresa", $user->co_empresa)
                    ->where("co_periodo", $periodo->periodo)
                    ->where("nu_inventario", $inventario)
                    ->where("co_catalogo_activo", $catalogo)
                    ->update([
                        "co_responsable_uso" => $request->get("responsable"),
                        "co_estado_conserva" => $request->get("conserva"),
                        "co_estado_utilidad" => $request->get("utilidad"),
                        "fe_registro" => date("Y-m-d H:i:s"),
                        "co_oficina_empresa" => $request->get("oficina"),
                        "co_usu_toma_invent" => $user->co_cliente,
                        "co_usuario" => $user->co_cliente
                    ]);
            }
            else {
                DB::table("lo_acti_fijo_toma_inven")
                    ->where("co_empresa", $user->co_empresa)
                    ->where("co_periodo", $periodo)
                    ->where("nu_inventario", $inventario)
                    ->where("co_catalogo_activo", $catalogo)
                    ->update([
                        "co_responsable_uso" => DB::raw("null"),
                        "co_estado_conserva" => $request->get("conserva"),
                        "co_estado_utilidad" => $request->get("utilidad"),
                        "fe_registro" => date("Y-m-d H:i:s"),
                        "co_oficina_empresa" => $request->get("oficina"),
                        "co_usu_toma_invent" => $user->co_cliente,
                        "co_usuario" => $user->co_cliente
                    ]);
            }
            return response()->json([
                "status" => true,
                "rqid" => REQ_ACT_ACT_INV_TOMA,
                "data" => [
                    "result" => "ok",
                    "message" => "Se atualizó el registro del activo"
                ]
            ]);
        }
        return response()->json([
            "status" => true,
            "rqid" => REQ_ACT_ACT_INV_TOMA,
            "data" => [
                "result" => "error",
                "message" => "El activo ya fue registrado"
            ]
        ]);
    }

    public function ls_faltantes(Request $request) {
        $user = JWTAuth::toUser($request->get("token"));
        $inventario = $request->get("inventario");
        $oficina = $request->get("oficina");
        DB::statement("call pack_venta.sm_activar_empresa(?)",[$user->de_nombre_comercial]);
        $periodo = DB::table("temp_ma_empresa")
            ->select(DB::raw("substr(co_periodo,0,4) as periodo"))
            ->first();
        $activos = DB::select("select
                invd.nu_catalogo_activo id,
                cata_activo.co_catalogo_activo catalogo,
                nvl(cata_prod.de_nombre,cata_activo.de_nombre || '*') nombre,
                cata_activo.de_marca marca,
                cata_activo.de_modelo modelo,
                cata_activo.de_otras_caract caracteristicas,
                invd.flg_faltante faltante
            from lo_acti_fijo_inven_d invd
                left join ma_cata_acti_fijo_m cata_activo on cata_activo.co_empresa = invd.co_empresa and cata_activo.nu_catalogo_activo = invd.nu_catalogo_activo
                left join ma_cata_prod_m cata_prod on cata_prod.co_catalogo_producto = cata_activo.co_catalogo_producto
            where invd.co_empresa = ?
                and invd.co_periodo = ?
                and invd.nu_inventario = ?
                and invd.co_oficina = ?", [$user->co_empresa, $periodo->periodo, $inventario, $oficina]);
        return response()->json([
            "status" => true,
            "rqid" => REQ_ACT_FALTANTES,
            "data" => compact("activos")
        ]);
    }

    public function sv_cierra_oficina(Request $request) {
        $user = JWTAuth::toUser($request->get("token"));
        $inventario = $request->get("inventario");
        $oficina = $request->get("oficina");
        //
        DB::statement("call pack_venta.sm_activar_empresa(?)",[$user->de_nombre_comercial]);
        $periodo = DB::table("temp_ma_empresa")
            ->select(DB::raw("substr(co_periodo,0,4) as periodo"))
            ->first();
        DB::table("lo_acti_fijo_inven_d")
            ->where("co_empresa", $user->co_empresa)
            ->where("co_periodo", $periodo->periodo)
            ->where("nu_inventario", $inventario)
            ->where("co_oficina", $oficina)
            ->update([
                "co_estado_inventario_ofic" => "CERRADO"
            ]);
        $cantAbiertos = DB::table("lo_acti_fijo_inven_d")
            ->where("co_empresa", $user->co_empresa)
            ->where("co_periodo", $periodo->periodo)
            ->where("nu_inventario", $inventario)
            ->where("co_estado_inventario_ofic", "ABIERTO")
            ->count();
        if($cantAbiertos == 0) {
            return response()->json([
                "status" => true,
                "rqid" => REQ_ACT_CIERRA_OFICINA,
                "message" => "Se cerró el inventario del periodo seleccionado"
            ]);
        }
        return response()->json([
            "status" => true,
            "rqid" => REQ_ACT_CIERRA_OFICINA,
            "message" => "Se cerró el inventario de la oficina"
        ]);
    }

    //

    public function get_user(Request $request) {
        $user = JWTAuth::toUser($request->get("token"));
        return response()->json([
            "status" => true,
            "rqid" => REQ_ACT_USER,
            "data" => $user
        ]);
    }

    public function pruebas() {
        $tmp_qr_path = implode(DIRECTORY_SEPARATOR,["C:","files","inventario","temp","qr_20190523231852.png"]);
        $nucatalogo = 241;
        $co_empresa = 11;
        $jar_file = implode(DIRECTORY_SEPARATOR,[env("APP_JAR_PATH"),"CLifeActivos.jar"]);
        $exec_string = "java -jar \"" . $jar_file . "\" \"" . $tmp_qr_path . "\" " . $co_empresa . " " . $nucatalogo;
        exec($exec_string);
        return "ok";
    }
}