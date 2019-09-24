<?php

namespace App\Http\Middleware;
use Closure;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

define("REQ_ACT_JWT_ERROR", 6000);

class VerifyJWTToken {
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */

    public function handle($request, Closure $next) {
        try {
            $user = JWTAuth::toUser($request->get("token"));
        }
        catch (JWTException $e) {
            if($e instanceof \Tymon\JWTAuth\Exceptions\TokenExpiredException) {
                return response()->json([
                    "status" => false,
                    "rqid" => REQ_ACT_JWT_ERROR,
                    "message" => "Su sesión ha caducado. Por favor, ingrese nuevamente"
                ], $e->getStatusCode());
            }
            else if ($e instanceof \Tymon\JWTAuth\Exceptions\TokenInvalidException) {
                return response()->json([
                    "status" => false,
                    "rqid" => REQ_ACT_JWT_ERROR,
                    "message" => "Token inválido"
                ], $e->getStatusCode());
            }
            else {
                return response()->json([
                    "status" => false,
                    "rqid" => REQ_ACT_JWT_ERROR,
                    "message" => "Se requiere un token"
                ]);
            }
        }
       return $next($request);
    }
}
