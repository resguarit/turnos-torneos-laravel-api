<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Mail\ReservaConfirmada;
use App\Mail\ReservaCancelada;
use App\Models\Turno;
use App\Models\User;
use App\Models\Configuracion;
use App\Notifications\Traits\TenantAware;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class ReservaNotification extends Notification
{
    use Queueable, TenantAware;

    protected $turnoId;
    protected string $tipo;
    protected $configuracionId;
    /**
     * Create a new notification instance.
     * El constructor AHORA requiere el subdominio.
     * Ya no dependemos de withTenant() para esto.
     */
    public function __construct(string $subdominio, $turnoId, string $tipo, $configuracionId = null)
    {
        $this->subdominio = $subdominio; // <-- Lo asignamos directamente
        $this->turnoId = $turnoId;
        $this->tipo = $tipo;
        $this->configuracionId = $configuracionId;

        // En ReservaNotification::__construct()
        Log::info("ReservaNotification constructor called with: Subdominio: {$subdominio}, TurnoId: {$turnoId}, Tipo: {$tipo}, ConfigId: " . ($configuracionId ?? 'null'));
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {

        $turno = Turno::with(['persona.usuario', 'cancha', 'horario'])->find($this->turnoId);
        $configuracion = $this->configuracionId ? Configuracion::find($this->configuracionId) : Configuracion::first();
        
        if (!$turno) {
            Log::error("ReservaNotification: Turno #{$this->turnoId} not found when processing email notification");
            throw new \InvalidArgumentException('Turno not found: ' . $this->turnoId);
        }
        
        $viewData = [
            'turno' => $turno,
            'configuracion' => $configuracion,
            'notification' => $this,
        ];

        switch ($this->tipo) {
            case 'confirmacion':
                return (new MailMessage)
                    ->subject($configuracion->nombre_complejo . ' | Reserva Confirmada (' . $turno->id . ')')
                    ->view('emails.turnos.confirmation', $viewData);
            case 'cancelacion':
                return (new MailMessage)
                    ->subject($configuracion->nombre_complejo . ' | Reserva Cancelada (' . $turno->id . ')')
                    ->view('emails.turnos.cancelation', $viewData);
            case 'cancelacion_automatica':
                return (new MailMessage)
                    ->subject($configuracion->nombre_complejo . ' | Reserva Cancelada Automáticamente (' . $turno->id . ')')
                    ->view('emails.turnos.automatic-cancelation', $viewData);
            case 'admin.confirmacion':
                return (new MailMessage)
                    ->subject($configuracion->nombre_complejo . ' | Reserva Confirmada (' . $turno->id . ')')
                    ->view('emails.turnos.admin.confirmation', $viewData);
            case 'admin.cancelacion':
                return (new MailMessage)
                    ->subject($configuracion->nombre_complejo . ' | Reserva Cancelada (' . $turno->id . ')')
                    ->view('emails.turnos.admin.cancelation', $viewData);
            case 'admin.cancelacion_automatica':
                return (new MailMessage)
                    ->subject($configuracion->nombre_complejo . ' | Reserva Cancelada Automáticamente (' . $turno->id . ')')
                    ->view('emails.turnos.admin.automatic-cancelation', $viewData);
            case 'admin.pending':
                return (new MailMessage)
                    ->subject($configuracion->nombre_complejo . ' | Reserva Pendiente (' . $turno->id . ')')
                    ->view('emails.turnos.admin.pending', $viewData);
            default:
                throw new \InvalidArgumentException('Tipo de notificación no válido: ' . $this->tipo);
        }
    }
    
    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
