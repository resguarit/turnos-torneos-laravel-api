<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cancelación Automática de Reserva</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; font-size: 14px; line-height: 1.4; color: #333333;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td align="center" style="padding: 10px;">
                <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width: 100%; background-color: #ffffff; border: 1px solid #dddddd;">
                    <!-- Encabezado simple -->
                    <tr>
                        <td style="padding: 15px 20px; background-color: #f5f5f5; border-bottom: 1px solid #dddddd;">
                            <h2 style="margin: 0; font-size: 18px; color: #e74c3c;">🤖 Cancelación Automática de Reserva</h2>
                        </td>
                    </tr>
                    
                    <!-- Contenido -->
                    <tr>
                        <td style="padding: 20px;">
                            <p>Hola, Administrador</p>
                            
                            <p style="color: #e74c3c; font-weight: bold;">Una reserva ha sido cancelada automáticamente por el sistema debido a falta de pago dentro de los 30 minutos.</p>
                            
                            <p>Aquí están los detalles de la reserva cancelada:</p>
                            
                            <!-- Detalles sin formato de tabla especial -->
                            <div style="background-color: #fff5f5; padding: 15px; margin: 15px 0; border-left: 3px solid #e74c3c;">
                                <p style="margin: 5px 0;"><strong>Cliente:</strong> {{ $turno->persona->name }}</p>
                                <p style="margin: 5px 0;"><strong>Email:</strong> {{ $turno->persona->usuario->email }}</p>
                                <p style="margin: 5px 0;"><strong>Teléfono:</strong> {{ $turno->persona->telefono }}</p>
                                <p style="margin: 5px 0;"><strong>Fecha:</strong> {{ formatearFechaCompleta($turno->fecha_turno) }}</p>
                                <p style="margin: 5px 0;"><strong>Horario:</strong> {{ formatearRangoHorario($turno->horario->hora_inicio, $turno->horario->hora_fin) }}</p>
                                <p style="margin: 5px 0;"><strong>Duración:</strong> {{ calcularDuracion($turno->horario->hora_inicio, $turno->horario->hora_fin) }}</p>
                                <p style="margin: 5px 0;"><strong>Cancha:</strong> #{{ $turno->cancha->nro }} {{ $turno->cancha->tipo_cancha }}</p>
                                <p style="margin: 5px 0;"><strong>Estado:</strong> {{ $turno->estado }}</p>
                                <p style="margin: 5px 0;"><strong>Monto Seña:</strong> ${{ formatearMonto($turno->monto_seña) }}</p>
                                <p style="margin: 5px 0;"><strong>Monto Total:</strong> ${{ formatearMonto($turno->monto_total) }}</p>
                                <p style="margin: 5px 0;"><strong>ID Reserva:</strong> {{ $turno->id }}</p>
                                @if(isset($configuracion))
                                <p style="margin: 5px 0;"><strong>Complejo:</strong> {{ $configuracion->nombre_complejo }}</p>
                                <p style="margin: 5px 0;"><strong>Dirección del Complejo:</strong> {{ $configuracion->direccion_complejo }}</p>
                                <p style="margin: 5px 0;"><strong>Teléfono del Complejo:</strong> {{ $configuracion->telefono_complejo }}</p>
                                @endif
                            </div>
                            
                            <div style="background-color: #e8f4fd; padding: 15px; margin: 15px 0; border-left: 3px solid #3498db;">
                                <p style="margin: 5px 0; color: #2c3e50;"><strong>ℹ️ Detalles de la cancelación automática:</strong></p>
                                <p style="margin: 5px 0;">• <strong>Motivo:</strong> Falta de pago dentro de los 30 minutos</p>
                                <p style="margin: 5px 0;">• <strong>Tiempo transcurrido:</strong> 30 minutos desde la creación</p>
                                <p style="margin: 5px 0;">• <strong>Acción tomada:</strong> Cancelación automática sin cargo</p>
                                <p style="margin: 5px 0;">• <strong>Estado de cuenta corriente:</strong> Monto devuelto automáticamente</p>
                                <p style="margin: 5px 0;">• <strong>Notificación al cliente:</strong> Email enviado automáticamente</p>
                            </div>
                            
                            <p style="margin: 5px 0; color: #27ae60; font-weight: bold;">✅ El horario está ahora disponible para nuevas reservas.</p>
                            
                            <!-- Botón simple -->
                            <p style="margin: 20px 0;">
                                <a href="{{ config('app.url_front') }}/panel-admin?tab=turnos&id={{ $turno->id }}" style="display: inline-block; background-color: #333333; color: #ffffff; text-decoration: none; padding: 8px 15px; border-radius: 3px; margin-right: 10px;">Ver en el sistema</a>
                            </p>
                            
                            <p style="margin: 5px 0;"><strong>Fecha de cancelación:</strong> {{ formatearFechaCompleta(now()) }} a las {{ now()->format('H:i') }} hs</p>
                            
                            <p>No se requiere ninguna acción manual. El sistema procesó automáticamente la devolución del monto y liberó el horario.</p>
                        </td>
                    </tr>
                    
                    <!-- Pie de página muy simple -->
                    <tr>
                        <td style="padding: 10px; text-align: center; font-size: 12px; color: #777777; border-top: 1px solid #dddddd; background-color: #f5f5f5;">
                            <p style="margin: 5px 0;">© {{ date('Y') }} Resguar IT. Todos los derechos reservados.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html> 