<?php
/**
 * NotificationService
 * 
 * Servicio para enviar notificaciones por múltiples canales
 * - Email (SMTP)
 * - SMS (Twilio)
 * - WhatsApp
 * - WebPush
 * 
 * Respeta preferencias de usuario/tenant y quiet hours
 * Usa plantillas traducibles multi-idioma
 */

require_once __DIR__ . '/../helpers/db_helper.php';
require_once __DIR__ . '/../helpers/i18n.php';
require_once __DIR__ . '/../helpers/env.php';

class NotificationService {
    /**
     * Canales disponibles
     */
    const CHANNEL_EMAIL = 'email';
    const CHANNEL_SMS = 'sms';
    const CHANNEL_WHATSAPP = 'whatsapp';
    const CHANNEL_WEBPUSH = 'webpush';
    
    /**
     * Tipos de eventos
     */
    const EVENT_REQUEST_CREATED = 'request.created';
    const EVENT_PROPOSAL_RECEIVED = 'proposal.received';
    const EVENT_PROPOSAL_ACCEPTED = 'proposal.accepted';
    const EVENT_SHIFT_ASSIGNED = 'shift.assigned';
    const EVENT_SHIFT_CHANGED = 'shift.changed';
    const EVENT_REPORT_SUBMITTED = 'report.submitted';
    const EVENT_DISPUTE_CREATED = 'dispute.created';
    const EVENT_DISPUTE_RESOLVED = 'dispute.resolved';
    const EVENT_INVOICE_ISSUED = 'invoice.issued';
    const EVENT_WORKLOG_APPROVED = 'worklog.approved';
    const EVENT_WORKLOG_REJECTED = 'worklog.rejected';
    
    /**
     * Envía una notificación
     * 
     * @param int $tenantId ID del tenant
     * @param int|array $userIds ID(s) del usuario o array de IDs
     * @param string $eventType Tipo de evento
     * @param array $data Datos para la plantilla
     * @param array $channels Canales a usar (si no se especifica, usa preferencias)
     * @return array ['success' => bool, 'sent' => int, 'failed' => int]
     */
    public static function send($tenantId, $userIds, $eventType, $data = [], $channels = null) {
        $conn = DBHelper::getConnection();
        
        // Normalizar userIds a array
        if (!is_array($userIds)) {
            $userIds = [$userIds];
        }
        
        $sent = 0;
        $failed = 0;
        
        foreach ($userIds as $userId) {
            // Obtener preferencias del usuario
            $preferences = self::getPreferences($conn, $tenantId, $userId, $eventType);
            
            // Determinar canales a usar
            $channelsToUse = $channels ?? array_keys($preferences);
            
            // Verificar quiet hours
            $isQuietHours = self::isQuietHours($preferences);
            
            foreach ($channelsToUse as $channel) {
                // Verificar si el canal está habilitado para este evento
                if (!isset($preferences[$channel]) || !$preferences[$channel]['enabled']) {
                    continue;
                }
                
                // Saltar si está en quiet hours (excepto para eventos críticos)
                $criticalEvents = [self::EVENT_DISPUTE_CREATED, self::EVENT_INVOICE_ISSUED];
                if ($isQuietHours && !in_array($eventType, $criticalEvents)) {
                    continue;
                }
                
                // Obtener plantilla y datos del usuario
                $userData = self::getUserData($conn, $userId);
                if (!$userData) {
                    $failed++;
                    continue;
                }
                
                // Obtener idioma del usuario
                $locale = $userData['locale'] ?? 'es';
                
                // Generar contenido de la notificación
                $content = self::generateContent($eventType, $data, $locale);
                
                // Enviar por canal
                $result = self::sendByChannel($channel, $userData, $content, $tenantId);
                
                if ($result['success']) {
                    $sent++;
                } else {
                    $failed++;
                    // Log error
                    error_log("NotificationService: Failed to send {$channel} to user {$userId}: " . ($result['error'] ?? 'Unknown error'));
                }
            }
        }
        
        return [
            'success' => $sent > 0,
            'sent' => $sent,
            'failed' => $failed
        ];
    }
    
    /**
     * Obtiene preferencias de notificación
     * 
     * @param mysqli $conn Conexión a BD
     * @param int $tenantId ID del tenant
     * @param int $userId ID del usuario
     * @param string $eventType Tipo de evento
     * @return array Preferencias por canal
     */
    private static function getPreferences($conn, $tenantId, $userId, $eventType) {
        // Primero buscar preferencias específicas del usuario
        $stmt = $conn->prepare("
            SELECT channel, enabled, quiet_hours_start, quiet_hours_end
            FROM notification_preferences
            WHERE user_id = ? AND event_type = ?
        ");
        $stmt->bind_param('is', $userId, $eventType);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $preferences = [];
        while ($row = $result->fetch_assoc()) {
            $preferences[$row['channel']] = [
                'enabled' => (bool)$row['enabled'],
                'quiet_hours_start' => $row['quiet_hours_start'],
                'quiet_hours_end' => $row['quiet_hours_end']
            ];
        }
        $stmt->close();
        
        // Si no hay preferencias específicas del usuario, usar preferencias del tenant
        if (empty($preferences)) {
            $stmt = $conn->prepare("
                SELECT channel, enabled, quiet_hours_start, quiet_hours_end
                FROM notification_preferences
                WHERE tenant_id = ? AND user_id IS NULL AND event_type = ?
            ");
            $stmt->bind_param('is', $tenantId, $eventType);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $preferences[$row['channel']] = [
                    'enabled' => (bool)$row['enabled'],
                    'quiet_hours_start' => $row['quiet_hours_start'],
                    'quiet_hours_end' => $row['quiet_hours_end']
                ];
            }
            $stmt->close();
        }
        
        // Si aún no hay preferencias, usar defaults (todos habilitados)
        if (empty($preferences)) {
            $preferences = [
                self::CHANNEL_EMAIL => ['enabled' => true, 'quiet_hours_start' => null, 'quiet_hours_end' => null],
                self::CHANNEL_SMS => ['enabled' => false, 'quiet_hours_start' => null, 'quiet_hours_end' => null],
                self::CHANNEL_WHATSAPP => ['enabled' => false, 'quiet_hours_start' => null, 'quiet_hours_end' => null],
                self::CHANNEL_WEBPUSH => ['enabled' => true, 'quiet_hours_start' => null, 'quiet_hours_end' => null]
            ];
        }
        
        return $preferences;
    }
    
    /**
     * Verifica si está en quiet hours
     */
    private static function isQuietHours($preferences) {
        $now = new DateTime();
        $currentTime = $now->format('H:i:s');
        
        foreach ($preferences as $pref) {
            if ($pref['quiet_hours_start'] && $pref['quiet_hours_end']) {
                $start = $pref['quiet_hours_start'];
                $end = $pref['quiet_hours_end'];
                
                // Si start > end, significa que cruza medianoche
                if ($start > $end) {
                    if ($currentTime >= $start || $currentTime <= $end) {
                        return true;
                    }
                } else {
                    if ($currentTime >= $start && $currentTime <= $end) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * Obtiene datos del usuario
     */
    private static function getUserData($conn, $userId) {
        $stmt = $conn->prepare("
            SELECT u.id, u.email, u.phone, u.locale, u.webpush_subscription
            FROM users u
            WHERE u.id = ?
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        return $user;
    }
    
    /**
     * Genera contenido de la notificación desde plantilla
     */
    private static function generateContent($eventType, $data, $locale) {
        // Cargar traducciones de notificaciones
        $translations = self::loadNotificationTranslations($locale);
        
        // Obtener clave de plantilla
        $templateKey = str_replace('.', '_', $eventType);
        
        // Obtener plantilla
        $template = $translations[$templateKey] ?? $translations['default'] ?? 'Notificación';
        
        // Reemplazar variables en plantilla
        $content = $template;
        foreach ($data as $key => $value) {
            $content = str_replace('{' . $key . '}', $value, $content);
        }
        
        // Obtener título
        $titleKey = $templateKey . '_title';
        $title = $translations[$titleKey] ?? $translations['default_title'] ?? 'Notificación';
        foreach ($data as $key => $value) {
            $title = str_replace('{' . $key . '}', $value, $title);
        }
        
        return [
            'title' => $title,
            'body' => $content
        ];
    }
    
    /**
     * Carga traducciones de notificaciones
     */
    private static function loadNotificationTranslations($locale) {
        $file = __DIR__ . '/../lang/' . $locale . '/notifications.php';
        if (file_exists($file)) {
            return require $file;
        }
        
        // Fallback a español
        $file = __DIR__ . '/../lang/es/notifications.php';
        if (file_exists($file)) {
            return require $file;
        }
        
        return [];
    }
    
    /**
     * Envía notificación por canal específico
     */
    private static function sendByChannel($channel, $userData, $content, $tenantId) {
        switch ($channel) {
            case self::CHANNEL_EMAIL:
                return self::sendEmail($userData['email'], $content, $tenantId);
                
            case self::CHANNEL_SMS:
                return self::sendSMS($userData['phone'], $content, $tenantId);
                
            case self::CHANNEL_WHATSAPP:
                return self::sendWhatsApp($userData['phone'], $content, $tenantId);
                
            case self::CHANNEL_WEBPUSH:
                return self::sendWebPush($userData['webpush_subscription'], $content, $tenantId);
                
            default:
                return ['success' => false, 'error' => 'Unknown channel'];
        }
    }
    
    /**
     * Envía email (SMTP)
     */
    private static function sendEmail($email, $content, $tenantId) {
        if (empty($email)) {
            return ['success' => false, 'error' => 'No email address'];
        }
        
        // Obtener configuración SMTP del tenant
        $smtpConfig = self::getSMTPConfig($tenantId);
        
        // Por ahora, usar mail() de PHP (en producción usar PHPMailer o similar)
        $headers = [
            'From: ' . ($smtpConfig['from_email'] ?? 'noreply@workee.app'),
            'Reply-To: ' . ($smtpConfig['reply_to'] ?? 'noreply@workee.app'),
            'Content-Type: text/html; charset=UTF-8',
            'X-Mailer: PHP/' . phpversion()
        ];
        
        $subject = $content['title'];
        $message = '<html><body>' . nl2br(htmlspecialchars($content['body'])) . '</body></html>';
        
        $success = @mail($email, $subject, $message, implode("\r\n", $headers));
        
        return [
            'success' => $success,
            'error' => $success ? null : 'Mail send failed'
        ];
    }
    
    /**
     * Envía SMS (Twilio)
     */
    private static function sendSMS($phone, $content, $tenantId) {
        if (empty($phone)) {
            return ['success' => false, 'error' => 'No phone number'];
        }
        
        // Obtener configuración Twilio del tenant
        $twilioConfig = self::getTwilioConfig($tenantId);
        
        if (empty($twilioConfig['account_sid']) || empty($twilioConfig['auth_token'])) {
            return ['success' => false, 'error' => 'Twilio not configured'];
        }
        
        // Por ahora, solo log (en producción integrar Twilio SDK)
        error_log("SMS to {$phone}: {$content['body']}");
        
        return [
            'success' => true,
            'message' => 'SMS queued (Twilio integration pending)'
        ];
    }
    
    /**
     * Envía WhatsApp
     */
    private static function sendWhatsApp($phone, $content, $tenantId) {
        if (empty($phone)) {
            return ['success' => false, 'error' => 'No phone number'];
        }
        
        // Por ahora, solo log (en producción integrar WhatsApp Business API o Twilio WhatsApp)
        error_log("WhatsApp to {$phone}: {$content['body']}");
        
        return [
            'success' => true,
            'message' => 'WhatsApp queued (integration pending)'
        ];
    }
    
    /**
     * Envía WebPush
     */
    private static function sendWebPush($subscription, $content, $tenantId) {
        if (empty($subscription)) {
            return ['success' => false, 'error' => 'No webpush subscription'];
        }
        
        // Por ahora, solo log (en producción usar web-push library)
        error_log("WebPush: {$content['title']} - {$content['body']}");
        
        return [
            'success' => true,
            'message' => 'WebPush queued (integration pending)'
        ];
    }
    
    /**
     * Obtiene configuración SMTP del tenant
     */
    private static function getSMTPConfig($tenantId) {
        $conn = DBHelper::getConnection();
        
        $stmt = $conn->prepare("
            SELECT config_value
            FROM tenant_config
            WHERE tenant_id = ? AND config_key = 'smtp_config'
        ");
        $stmt->bind_param('i', $tenantId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        if ($row && $row['config_value']) {
            return json_decode($row['config_value'], true);
        }
        
        // Defaults
        return [
            'from_email' => 'noreply@workee.app',
            'from_name' => 'Workee',
            'reply_to' => 'noreply@workee.app'
        ];
    }
    
    /**
     * Obtiene configuración Twilio del tenant
     */
    private static function getTwilioConfig($tenantId) {
        $conn = DBHelper::getConnection();
        
        $stmt = $conn->prepare("
            SELECT config_value
            FROM tenant_config
            WHERE tenant_id = ? AND config_key = 'twilio_config'
        ");
        $stmt->bind_param('i', $tenantId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        if ($row && $row['config_value']) {
            return json_decode($row['config_value'], true);
        }
        
        return [];
    }
}

