<?php
declare(strict_types=1);

/**
 * Helper para envio de e-mails do sistema.
 */
class EmailHelper
{
    private static function get_template(string $title, string $content): string
    {
        $shop = settings();
        $shopName = htmlspecialchars($shop['name'] ?? 'Barbearia', ENT_QUOTES, 'UTF-8');
        
        $logo = shop_logo_path();
        if ($logo === '') {
            $logo = product_logo_path();
        }
        // Cria um URL absoluto para o logo, se houver configuração de APP_URL
        $logoUrl = $logo !== '' ? url(media_url($logo)) : '';

        $logoHtml = '';
        if ($logoUrl !== '') {
            // Em produção APP_URL deve incluir o domínio base
            $logoHtml = '<img src="' . $logoUrl . '" alt="' . $shopName . '" style="max-width: 120px; height: auto;">';
        } else {
            $logoHtml = '<h1 style="color: #c9a227; margin: 0; font-size: 24px;">' . $shopName . '</h1>';
        }

        return '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>' . $title . '</title>
</head>
<body style="margin: 0; padding: 0; background-color: #0b0d12; color: #e8eaf0; font-family: sans-serif; -webkit-font-smoothing: antialiased;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #0b0d12; margin: 0; padding: 20px 0;">
        <tr>
            <td align="center">
                <table width="100%" max-width="600" cellpadding="0" cellspacing="0" style="max-width: 600px; background-color: #11172f; border-radius: 8px; overflow: hidden; border: 1px solid rgba(255, 255, 255, 0.08);">
                    <tr>
                        <td align="center" style="padding: 30px 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.08);">
                            ' . $logoHtml . '
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 30px 20px; font-size: 16px; line-height: 1.6; color: #e8eaf0;">
                            ' . $content . '
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding: 20px; font-size: 12px; color: #9aa3b5; border-top: 1px solid rgba(255, 255, 255, 0.08);">
                            &copy; ' . date('Y') . ' ' . $shopName . '. Todos os direitos reservados.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }

    private static function send_html_email(string $to, string $subject, string $htmlContent): bool
    {
        $shop = settings();
        $shopName = $shop['name'] ?? 'Barbearia';
        
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=utf-8\r\n";
        $headers .= "From: " . $shopName . " <no-reply@" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ">\r\n";
        $headers .= "Reply-To: no-reply@" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        return mail($to, $subject, $htmlContent, $headers);
    }

    public static function send_booking_confirmation(array $client, array $appointment, array $barber, array $service): bool
    {
        $to = $client['email'] ?? '';
        if (empty($to)) return false;

        $subject = 'Agendamento Confirmado';
        
        $date = date('d/m/Y', strtotime($appointment['start_time']));
        $time = date('H:i', strtotime($appointment['start_time']));
        
        $content = '
            <h2 style="color: #c9a227; margin-top: 0;">Olá, ' . htmlspecialchars($client['name']) . '!</h2>
            <p>Seu agendamento foi confirmado com sucesso. Aqui estão os detalhes:</p>
            <div style="background-color: rgba(255, 255, 255, 0.05); padding: 15px; border-radius: 6px; margin: 20px 0;">
                <p style="margin: 5px 0;"><strong>Serviço:</strong> ' . htmlspecialchars($service['name']) . '</p>
                <p style="margin: 5px 0;"><strong>Profissional:</strong> ' . htmlspecialchars($barber['name']) . '</p>
                <p style="margin: 5px 0;"><strong>Data:</strong> ' . $date . '</p>
                <p style="margin: 5px 0;"><strong>Horário:</strong> ' . $time . '</p>
            </div>
            <p>Agradecemos a preferência e esperamos você!</p>
        ';

        return self::send_html_email($to, $subject, self::get_template($subject, $content));
    }

    public static function send_booking_reminder(array $client, array $appointment): bool
    {
        $to = $client['email'] ?? '';
        if (empty($to)) return false;

        $subject = 'Lembrete de Agendamento';
        
        $date = date('d/m/Y', strtotime($appointment['start_time']));
        $time = date('H:i', strtotime($appointment['start_time']));
        
        $content = '
            <h2 style="color: #c9a227; margin-top: 0;">Olá, ' . htmlspecialchars($client['name']) . '!</h2>
            <p>Este é um lembrete do seu agendamento que está chegando:</p>
            <div style="background-color: rgba(255, 255, 255, 0.05); padding: 15px; border-radius: 6px; margin: 20px 0;">
                <p style="margin: 5px 0;"><strong>Data:</strong> ' . $date . '</p>
                <p style="margin: 5px 0;"><strong>Horário:</strong> ' . $time . '</p>
            </div>
            <p>Pedimos que chegue com alguns minutos de antecedência.</p>
        ';

        return self::send_html_email($to, $subject, self::get_template($subject, $content));
    }

    public static function send_daily_summary(array $owner, array $stats): bool
    {
        $to = $owner['email'] ?? '';
        if (empty($to)) return false;

        $subject = 'Resumo Diário - ' . date('d/m/Y');
        
        $content = '
            <h2 style="color: #c9a227; margin-top: 0;">Olá, ' . htmlspecialchars($owner['name']) . '!</h2>
            <p>Aqui está o resumo das atividades de hoje:</p>
            <div style="background-color: rgba(255, 255, 255, 0.05); padding: 15px; border-radius: 6px; margin: 20px 0;">
                <p style="margin: 5px 0;"><strong>Total de Agendamentos:</strong> ' . (int)($stats['total_appointments'] ?? 0) . '</p>
                <p style="margin: 5px 0;"><strong>Receita do Dia:</strong> R$ ' . number_format((float)($stats['total_revenue'] ?? 0), 2, ',', '.') . '</p>
                <p style="margin: 5px 0;"><strong>Novos Clientes:</strong> ' . (int)($stats['new_clients'] ?? 0) . '</p>
            </div>
            <p>Acesse o painel para ver mais detalhes.</p>
        ';

        return self::send_html_email($to, $subject, self::get_template($subject, $content));
    }
}
