<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Complejo;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class SetTenantDatabase
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {

        if ($request->is('api/mercadopago/webhook/*')) {
            return $next($request);
        }

        $subdominio = $request->header('x-complejo') ?? $request->header('X-Complejo');

        if (!$subdominio) {
            return response()->json(['message' => 'Complejo no especificado.'], 400);
        }

        $complejo = Complejo::where('subdominio', $subdominio)->first();

        if (!$complejo) {
            return response()->json(['message' => 'Complejo no encontrado.'], 404);
        }

        DB::purge('mysql_tenant');
            
        Config::set('database.connections.mysql_tenant.host', $complejo->db_host);
        Config::set('database.connections.mysql_tenant.database', $complejo->db_database);
        Config::set('database.connections.mysql_tenant.username', $complejo->db_username);
        Config::set('database.connections.mysql_tenant.password', $complejo->db_password);
        Config::set('database.connections.mysql_tenant.port', $complejo->db_port);

        Config::set('database.default', 'mysql_tenant');

        return $next($request);
    }
}
