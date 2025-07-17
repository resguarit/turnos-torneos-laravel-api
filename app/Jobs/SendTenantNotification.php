<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Complejo;

class SendTenantNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $subdominio;
    protected $userId;
    protected string $notificationClass;
    protected array $notificationParams;

    /**
     * Create a new job instance.
     */
    public function __construct(string $subdominio, $userId, string $notificationClass, array $notificationParams)
    {
        $this->subdominio = $subdominio;
        $this->userId = $userId;
        $this->notificationClass = $notificationClass;
        $this->notificationParams = $notificationParams;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // 1. Buscamos y establecemos la conexión del tenant
        $complejo = Complejo::where('subdominio', $this->subdominio)->first();

        if (!$complejo) {
            Log::error("SendTenantNotification: No se pudo encontrar el complejo: {$this->subdominio}");
            return;
        }

        DB::purge('mysql_tenant');
        Config::set('database.connections.mysql_tenant.host', $complejo->db_host);
        Config::set('database.connections.mysql_tenant.database', $complejo->db_database);
        Config::set('database.connections.mysql_tenant.username', $complejo->db_username);
        Config::set('database.connections.mysql_tenant.password', $complejo->db_password);
        Config::set('database.connections.mysql_tenant.port', $complejo->db_port);
        Config::set('database.default', 'mysql_tenant');
        
        // 2. Ahora que estamos en la BD correcta, buscamos al usuario
        $user = User::find($this->userId);
        if (!$user) {
            Log::error("SendTenantNotification: No se pudo encontrar al usuario con ID {$this->userId} en el complejo {$this->subdominio}");
            return;
        }

        // --- LA LÍNEA MÁS IMPORTANTE ---
        // Construimos el array de parámetros para el constructor de la notificación,
        // poniendo el subdominio primero.
        Log::info("Params before merge: Subdominio: {$this->subdominio}, NotificationParams: " . json_encode($this->notificationParams));
        $params = array_merge([$this->subdominio], $this->notificationParams);
        Log::info("Final params for constructor: " . json_encode($params));
        
        // 3. Creamos la instancia de la notificación real usando los parámetros completos
        $notification = new $this->notificationClass(...$params);
        
        // 4. La enviamos de forma síncrona (dentro del job)
        $user->notifyNow($notification);
        
        Log::info("Notificación {$this->notificationClass} enviada al usuario {$this->userId} en el complejo {$this->subdominio}.");
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Error al enviar notificación a usuario {$this->userId} en complejo {$this->subdominio}: " . $exception->getMessage());
    }
}
