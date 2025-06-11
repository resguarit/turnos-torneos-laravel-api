<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cancelación Automática de Turno</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; font-size: 14px; line-height: 1.4; color: #333333;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td align="center" style="padding: 10px;">
                <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width: 100%; background-color: #ffffff; border: 1px solid #dddddd;">
                    <!-- Encabezado simple -->
                    <tr>
                        <td style="padding: 15px 20px; background-color:#f5f5f5; border-bottom: 1px solid #dddddd;">
                            <h2 style="margin: 0; font-size: 18px; color: #e74c3c;">⚠️ Cancelación Automática de Turno</h2>
                        </td>
                    </tr>
                    
                    <!-- Contenido -->
                    <tr>
                        <td style="padding: 20px;">
                            <p>Hola, <strong>{{ $turno->persona->name }}</strong></p>
                            
                            <p style="color: #e74c3c; font-weight: bold;">Tu turno ha sido cancelado automáticamente por falta de pago dentro de los 30 minutos.</p>
                            
                            <p>Los detalles del turno cancelado son los siguientes:</p>
                            
                            <!-- Detalles sin formato de tabla especial -->
                            <div style="background-color: #fff5f5; padding: 15px; margin: 15px 0; border-left: 3px solid #e74c3c;">
                                <p style="margin: 5px 0;"><strong>Fecha:</strong> {{ formatearFechaCompleta($turno->fecha_turno) }}</p>
                                <p style="margin: 5px 0;"><strong>Horario:</strong> {{ formatearRangoHorario($turno->horario->hora_inicio, $turno->horario->hora_fin) }}</p>
                                <p style="margin: 5px 0;"><strong>Duración:</strong> {{ calcularDuracion($turno->horario->hora_inicio, $turno->horario->hora_fin) }}</p>
                                <p style="margin: 5px 0;"><strong>Cancha:</strong> #{{ $turno->cancha->nro }} {{ $turno->cancha->tipo_cancha }}</p>
                                <p style="margin: 5px 0;"><strong>Estado:</strong> {{ $turno->estado }}</p>
                                <p style="margin: 5px 0;"><strong>Nº Confirmación:</strong> {{ $turno->id }}</p>
                                <p style="margin: 5px 0;"><strong>Monto Total:</strong> ${{ formatearMonto($turno->monto_total) }}</p>
                            </div>
                            
                            <div style="background-color: #e8f4fd; padding: 15px; margin: 15px 0; border-left: 3px solid #3498db;">
                                <p style="margin: 5px 0; color: #2c3e50;"><strong>ℹ️ Información importante:</strong></p>
                                <p style="margin: 5px 0;">• El turno fue cancelado automáticamente después de 30 minutos sin confirmación de pago.</p>
                                <p style="margin: 5px 0;">• <strong>No se aplicó ningún cargo</strong> por esta cancelación automática.</p>
                                <p style="margin: 5px 0;">• El horario está ahora disponible para nuevas reservas.</p>
                            </div>
                            
                            <p style="margin: 5px 0;"><strong>Fecha de cancelación:</strong> {{ formatearFechaCompleta(now()) }} a las {{ now()->format('H:i') }} hs</p>
                            
                            <!-- Botón simple -->
                            <p style="margin: 20px 0;">
                                <a href="{{ config('app.url_front') }}/user-profile" style="display: inline-block; background-color: #3498db; color: #ffffff; text-decoration: none; padding: 10px 20px; border-radius: 5px; margin-right: 10px;">Ver mis turnos</a>
                                <a href="{{ config('app.url_front') }}/select-deporte" style="display: inline-block; background-color: #27ae60; color: #ffffff; text-decoration: none; padding: 10px 20px; border-radius: 5px;">Reservar nuevo turno</a>
                            </p>
                            
                            <p>Si tienes alguna consulta sobre esta cancelación o necesitas asistencia, no dudes en contactarnos.</p>
                            
                            <p>Gracias por confiar en nosotros.<br>
                            El equipo de Resguar IT</p>
                        </td>
                    </tr>
                    
                    <!-- Pie de página muy simple -->
                    <tr>
                        <td style="padding: 10px; text-align: center; font-size: 12px; color: #777777; border-top: 1px solid #dddddd; background-color: #f5f5f5;">
                            <p style="margin: 5px 0;">Este correo fue enviado automáticamente. Por favor no responda a este mensaje.</p>
                            <p style="margin: 5px 0;">© {{ date('Y') }} Resguar IT. Todos los derechos reservados.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html> 