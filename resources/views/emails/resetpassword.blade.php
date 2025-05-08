<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperación de Contraseña</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; font-size: 14px; line-height: 1.4; color: #333333;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td align="center" style="padding: 10px;">
                <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width: 100%; background-color: #ffffff; border: 1px solid #dddddd;">
                    <!-- Encabezado simple -->
                    <tr>
                        <td style="padding: 15px 20px; background-color: #f5f5f5; border-bottom: 1px solid #dddddd;">
                            <h2 style="margin: 0; font-size: 18px;">Recuperación de Contraseña</h2>
                        </td>
                    </tr>
                    
                    <!-- Contenido -->
                    <tr>
                        <td style="padding: 20px;">
                            <p>Hola, <strong>{{ $user->name }}</strong></p>
                            
                            <p>Recibimos una solicitud para restablecer la contraseña de tu cuenta. Haz clic en el siguiente enlace para crear una nueva contraseña:</p>
                            
                            <!-- Botón de acción -->
                            <p style="margin: 25px 0; text-align: center;">
                                <a href="{{ $resetLink }}" style="display: inline-block; background-color: #333333; color: #ffffff; text-decoration: none; padding: 10px 20px; border-radius: 3px; font-weight: bold;">Restablecer contraseña</a>
                            </p>
                            
                            <p>Si no solicitaste este cambio, puedes ignorar este correo y tu contraseña seguirá siendo la misma.</p>
                            
                            <div style="background-color: #f9f9f9; padding: 15px; margin: 15px 0; border-left: 3px solid #999999;">
                                <p style="margin: 5px 0; font-size: 13px;">El enlace caducará en 60 minutos. Si el botón no funciona, copia y pega la siguiente URL en tu navegador:</p>
                                <p style="margin: 10px 0; word-break: break-all;">{{ $resetLink }}</p>
                            </div>
                            
                            <p>Gracias,<br>
                            El equipo de Resguar IT</p>
                        </td>
                    </tr>
                    
                    <!-- Pie de página muy simple -->
                    <tr>
                        <td style="padding: 10px; text-align: center; font-size: 12px; color: #777777; border-top: 1px solid #dddddd; background-color: #f5f5f5;">
                            <p style="margin: 5px 0;">Si no solicitaste este correo, por favor ignóralo o contacta con soporte.</p>
                            <p style="margin: 5px 0;">© {{ date('Y') }} Resguar IT. Todos los derechos reservados.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>