<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Attempt to load Composer autoload if it exists
if (file_exists(dirname(__DIR__) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
}

class Mailer
{
    private $db;
    private $settings;
    private $useSmtp;
    private $host;
    private $port;
    private $username;
    private $password;
    private $fromEmail;
    private $fromName;
    private $lastError = '';

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->loadSettings();
    }

    private function loadSettings()
    {
        $stmt = $this->db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'smtp_%'");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $config = [];
        foreach ($rows as $row) {
            $config[$row['setting_key']] = $row['setting_value'];
        }

        $this->host = $config['smtp_host'] ?? '';
        $this->port = $config['smtp_port'] ?? 587;
        $this->username = $config['smtp_username'] ?? '';
        $this->password = $config['smtp_password'] ?? '';
        $this->fromEmail = $config['smtp_from_email'] ?? 'noreply@ojgherbal.com';
        $this->fromName = $config['smtp_from_name'] ?? 'OJG Herbal';

        $this->useSmtp = !empty($this->host) && !empty($this->username);
    }

    public function setConfig($config)
    {
        $this->host = $config['smtp_host'] ?? $this->host;
        $this->port = $config['smtp_port'] ?? $this->port;
        $this->username = $config['smtp_username'] ?? $this->username;
        $this->password = $config['smtp_password'] ?? $this->password;
        $this->fromEmail = $config['smtp_from_email'] ?? $this->fromEmail;
        $this->fromName = $config['smtp_from_name'] ?? $this->fromName;

        $this->useSmtp = !empty($this->host) && !empty($this->username);
    }

    public function getLastError()
    {
        return $this->lastError;
    }

    public function send($to, $subject, $body, $isHtml = true, $attachmentPath = null, $attachmentName = null)
    {
        // If PHPMailer isn't installed (fallback during transition)
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            return $this->sendNativeFallback($to, $subject, $body, $isHtml, $attachmentPath, $attachmentName);
        }

        $mail = new PHPMailer(true);

        try {
            if ($this->useSmtp) {
                $mail->isSMTP();
                $mail->Host       = $this->host;
                $mail->SMTPAuth   = true;
                $mail->Username   = $this->username;
                $mail->Password   = $this->password;
                
                // Determine encryption
                if ($this->port == 465) {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                } else {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                }
                $mail->Port       = $this->port;
            } else {
                // If not using SMTP, fallback to native mail
                $mail->isMail();
            }

            // Recipients
            $mail->setFrom($this->fromEmail, $this->fromName);
            $mail->addAddress($to);
            $mail->addReplyTo($this->fromEmail, $this->fromName);

            // Attachments
            if ($attachmentPath && file_exists($attachmentPath)) {
                $filename = $attachmentName ?: basename($attachmentPath);
                $mail->addAttachment($attachmentPath, $filename);
            }

            // Content
            $mail->isHTML($isHtml);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            if ($isHtml) {
                $mail->AltBody = strip_tags($body);
            }

            $mail->send();
            return true;
        } catch (Exception $e) {
            $this->lastError = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
            error_log($this->lastError);
            return false;
        }
    }

    private function sendNativeFallback($to, $subject, $body, $isHtml, $attachmentPath, $attachmentName)
    {
        $headers = "From: {$this->fromName} <{$this->fromEmail}>\r\n";
        $headers .= "Reply-To: {$this->fromEmail}\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        if ($attachmentPath && file_exists($attachmentPath)) {
            $boundary = md5(time());
            $headers .= "\r\nMIME-Version: 1.0\r\n";
            $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"";

            // Message Body
            $message = "--{$boundary}\r\n";
            $message .= "Content-Type: " . ($isHtml ? "text/html" : "text/plain") . "; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
            $message .= $body . "\r\n";

            // Attachment
            $filename = $attachmentName ?: basename($attachmentPath);
            $fileContent = chunk_split(base64_encode(file_get_contents($attachmentPath)));
            $message .= "--{$boundary}\r\n";
            $message .= "Content-Type: application/octet-stream; name=\"{$filename}\"\r\n";
            $message .= "Content-Description: {$filename}\r\n";
            $message .= "Content-Disposition: attachment; filename=\"{$filename}\"; size=" . filesize($attachmentPath) . ";\r\n";
            $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $message .= $fileContent . "\r\n";
            $message .= "--{$boundary}--";

            return mail($to, $subject, $message, $headers);
        } else {
            if ($isHtml) {
                $headers .= "\r\nMIME-Version: 1.0\r\n";
                $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            }
            return mail($to, $subject, $body, $headers);
        }
    }
}
