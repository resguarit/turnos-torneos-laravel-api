<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmación de Turno</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; font-size: 14px; line-height: 1.4; color: #333333;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td align="center" style="padding: 10px;">
                <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width: 100%; background-color: #ffffff; border: 1px solid #dddddd;">
                    <!-- Encabezado simple -->
                    <tr>
                        <td style="padding: 15px 20px; background-color:#f5f5f5; border-bottom: 1px solid #dddddd;">
                            <h2 style="margin: 0; font-size: 18px;">Cancelación de Turno</h2>
                        </td>
                    </tr>
                    
                    <!-- Contenido -->
                    <tr>
                        <td style="padding: 20px;">
                            <p>Hola, <strong>{{ $turno->persona->name }}</strong></p>
                            
                            <p>Tu turno ha sido cancelado. Aquí están los detalles del turno cancelado:</p>
                            
                            <!-- Detalles sin formato de tabla especial -->
                            <div style="background-color: #f9f9f9; padding: 15px; margin: 15px 0; border-left: 3px solid #999999;">
                                <p style="margin: 5px 0;"><strong>Fecha:</strong> {{ $turno->fecha_turno->format('d/m/Y') }}</p>
                                <p style="margin: 5px 0;"><strong>Horario:</strong> {{ $turno->horario->hora_inicio }} - {{ $turno->horario->hora_fin }}</p>
                                <p style="margin: 5px 0;"><strong>Cancha:</strong> #{{ $turno->cancha->nro }} {{ $turno->cancha->tipo_cancha }}</p>
                                <p style="margin: 5px 0;"><strong>Estado:</strong> {{ $turno->estado }}</p>
                                <p style="margin: 5px 0;"><strong>Nº Confirmación:</strong> {{ $turno->id }}</p>
                            </div>
                            
                            <p>El turno se reservó a las {{ $turno->fecha_reserva }}. Las cancelaciones realizadas 30 minutos despues de la reserva tienen un cargo del 10% del valor del turno.</p>
                            
                            <!-- Botón simple -->
                            <p style="margin: 20px 0;">
                                <a href="{{ config('app.url_front') }}/user-profile" style="display: inline-block; background-color: #333333; color: #ffffff; text-decoration: none; padding: 8px 15px; border-radius: 3px;">Ver mis turnos</a>
                            </p>
                            
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