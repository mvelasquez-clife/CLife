<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group(["prefix" => "app-clife"], function() {
	Route::any("registro", "Activos@registro");
	Route::any("login", "Activos@login");
	Route::any("user", "Activos@get_user");
	Route::any("pruebas", "Activos@pruebas");
	//
	Route::group(["prefix" => "activos"], function() {
		Route::any("ls-inventarios", "Activos@ls_inventarios");
		Route::any("ls-sedes", "Activos@ls_sedes");
		Route::any("ls-departamentos", "Activos@ls_departamentos");
		Route::any("ls-oficinas", "Activos@ls_oficinas");
		Route::any("ls-nvo-inventario-inicia", "Activos@ls_nvo_inventario_inicia");
		Route::any("ls-subclases", "Activos@ls_subclases");
		Route::any("ls-busca-usuarios", "Activos@ls_busca_usuarios");
		Route::any("ls-responsables-activo", "Activos@ls_responsables_activo");
		Route::any("sv-inventario-inicial", "Activos@sv_inventario_inicial");
		Route::any("dt-genera-etiqueta", "Activos@dt_genera_etiqueta");
		Route::any("ls-info-activo", "Activos@ls_info_activo");
		Route::any("sv-guarda-toma", "Activos@sv_guarda_toma");
		Route::any("ls-faltantes", "Activos@ls_faltantes");
		Route::any("sv-cierra-oficina", "Activos@sv_cierra_oficina");
	});
});
//
Route::group(["prefix" => "kamill"], function() {
	Route::any("app-login", "Kamill@login");
	Route::any("puntos-venta", "Kamill@puntos_venta");
	Route::any("validar-cliente", "Kamill@validar_cliente");
	Route::any("registrar-cliente", "Kamill@registra_cliente");
	Route::any("datos-cliente", "Kamill@datos_cliente");
});