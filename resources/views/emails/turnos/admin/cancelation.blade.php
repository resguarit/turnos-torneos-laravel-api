<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reserva Cancelada</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; font-size: 14px; line-height: 1.4; color: #333333;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td align="center" style="padding: 10px;">
                <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width: 100%; background-color: #ffffff; border: 1px solid #dddddd;">
                    <!-- Encabezado simple -->
                    <tr>
                        <td style="padding: 15px 20px; background-color: #f5f5f5; border-bottom: 1px solid #dddddd;">
                            <h2 style="margin: 0; font-size: 18px;">Cancelación de Reserva</h2>
                        </td>
                    </tr>
                    
                    <!-- Contenido -->
                    <tr>
                        <td style="padding: 20px;">
                            <p>Hola, Administrador</p>
                            
                            <p>Una reserva ha sido cancelada en el sistema. Aquí están los detalles:</p>
                            
                            <!-- Detalles sin formato de tabla especial -->
                            <div style="background-color: #f9f9f9; padding: 15px; margin: 15px 0; border-left: 3px solid #999999;">
                                <p style="margin: 5px 0;"><strong>Cliente:</strong> {{ $turno->persona->name }}</p>
                                <p style="margin: 5px 0;"><strong>Email:</strong> {{ $turno->persona->usuario->email }}</p>
                                <p style="margin: 5px 0;"><strong>Teléfono:</strong> {{ $turno->persona->telefono }}</p>
                                <p style="margin: 5px 0;"><strong>Fecha:</strong> {{ $turno->fecha_turno->format('d/m/Y') }}</p>
                                <p style="margin: 5px 0;"><strong>Horario:</strong> {{ $turno->horario->hora_inicio }} - {{ $turno->horario->hora_fin }}</p>
                                <p style="margin: 5px 0;"><strong>Cancha:</strong> #{{ $turno->cancha->nro }} {{ $turno->cancha->tipo_cancha }}</p>
                                <p style="margin: 5px 0;"><strong>Estado:</strong> {{ $turno->estado }}</p>
                                <p style="margin: 5px 0;"><strong>Monto Seña:</strong> ${{ $turno->monto_seña }}</p>
                                <p style="margin: 5px 0;"><strong>Monto Total:</strong> ${{ $turno->monto_total }}</p>
                                <p style="margin: 5px 0;"><strong>ID Reserva:</strong> {{ $turno->id }}</p>
                            </div>
                            
                            <!-- Botón simple -->
                            <p style="margin: 20px 0;">
                                <a href="{{ config('app.url_front') }}/panel-admin?tab=turnos&id={{ $turno->id }}" style="display: inline-block; background-color: #333333; color: #ffffff; text-decoration: none; padding: 8px 15px; border-radius: 3px;">Ver en el sistema</a>
                            </p>
                            
                            <p>El horario está ahora disponible para nuevas reservas.</p>
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