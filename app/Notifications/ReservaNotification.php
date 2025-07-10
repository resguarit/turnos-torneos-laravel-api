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

class ReservaNotification extends Notification implements ShouldQueue
{
    use Queueable, TenantAware;

    protected $turno;
    protected $tipo;
    protected $configuracion;
    /**
     * Create a new notification instance.
     */
    public function __construct($turno, $tipo, $configuracion = null)
    {
        $this->turno = $turno;
        $this->tipo = $tipo;
        $this->configuracion = $configuracion;
        $this->withTenant(); // Set the tenant subdomain if available
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
        switch ($this->tipo) {
            case 'confirmacion':
                return (new MailMessage)
                    ->subject('Reserva Confirmada (' . $this->turno->id . ')')
                    ->view('emails.turnos.confirmation', [
                        'turno' => $this->turno,
                        'configuracion' => $this->configuracion,
                        'notificacion' => $this
                    ]);
            case 'cancelacion':
                return (new MailMessage)
                    ->subject('Reserva Cancelada (' . $this->turno->id . ')')
                    ->view('emails.turnos.cancelation', [
                        'turno' => $this->turno,
                        'configuracion' => $this->configuracion,
                        'notificacion' => $this
                    ]);
            case 'cancelacion_automatica':
                return (new MailMessage)
                    ->subject('Reserva Cancelada Automáticamente (' . $this->turno->id . ')')
                    ->view('emails.turnos.automatic-cancelation', [
                        'turno' => $this->turno,
                        'configuracion' => $this->configuracion
                    ]);
            case 'admin.confirmacion':
                return (new MailMessage)
                    ->subject('Reserva Confirmada (' . $this->turno->id . ')')
                    ->view('emails.turnos.admin.confirmation', [
                        'turno' => $this->turno,
                        'configuracion' => $this->configuracion,
                        'notificacion' => $this
                    ]);
            case 'admin.cancelacion':
                return (new MailMessage)
                    ->subject('Reserva Cancelada (' . $this->turno->id . ')')
                    ->view('emails.turnos.admin.cancelation', [
                        'turno' => $this->turno,
                        'configuracion' => $this->configuracion,
                        'notificacion' => $this
                    ]);
            case 'admin.cancelacion_automatica':
                return (new MailMessage)
                    ->subject('Reserva Cancelada Automáticamente (' . $this->turno->id . ')')
                    ->view('emails.turnos.admin.automatic-cancelation', [
                        'turno' => $this->turno,
                        'configuracion' => $this->configuracion,
                        'notificacion' => $this
                    ]);
            case 'admin.pending':
                return (new MailMessage)
                    ->subject('Reserva Pendiente (' . $this->turno->id . ')')
                    ->view('emails.turnos.admin.pending', [
                        'turno' => $this->turno,
                        'configuracion' => $this->configuracion,
                        'notificacion' => $this
                    ]);
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
