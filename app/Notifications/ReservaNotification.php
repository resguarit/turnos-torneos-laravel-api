<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Mail\ReservaConfirmada;
use App\Mail\ReservaCancelada;
use App\Models\Turno;

class ReservaNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $turno;
    protected $tipo;
    /**
     * Create a new notification instance.
     */
    public function __construct($turno, $tipo)
    {
        $this->turno = $turno;
        $this->tipo = $tipo;
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
                    ->view('emails.turnos.confirmation', ['turno' => $this->turno]);
            case 'cancelacion':
                return (new MailMessage)
                    ->subject('Reserva Cancelada (' . $this->turno->id . ')')
                    ->view('emails.turnos.cancelation', ['turno' => $this->turno]);
            case 'admin.confirmacion':
                return (new MailMessage)
                    ->subject('Reserva Confirmada (' . $this->turno->id . ')')
                    ->view('emails.turnos.admin.confirmation', ['turno' => $this->turno]);
            case 'admin.cancelacion':
                return (new MailMessage)
                    ->subject('Reserva Cancelada (' . $this->turno->id . ')')
                    ->view('emails.turnos.admin.cancelation', ['turno' => $this->turno]);
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
