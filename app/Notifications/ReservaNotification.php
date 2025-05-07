<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReservaNotification extends Notification
{
    use Queueable;

    protected $reserva;
    protected $tipo;

    /**
     * Create a new notification instance.
     */
    public function __construct($reserva, $tipo)
    {
        $this->reserva = $reserva;
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
        return (new MailMessage)   
            ->subject($this->tipo === 'nueva' ? '📅 Nueva reserva' : '❌ Reserva cancelada')
            ->line($this->tipo === 'nueva' ? 'Se ha registrado una nueva reserva.' : 'Una reserva ha sido cancelada.')
            ->line('Detalles:')
            ->line('ID: ' . $this->reserva->id)
            ->line('Cliente: ' . $this->reserva->cliente->nombre)
            ->action('Ver en el sistema', url('http://localhost:5173/editar-turno/' . $this->reserva->id))
            ->line('¡Gracias por usar nuestro sistema!');
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
