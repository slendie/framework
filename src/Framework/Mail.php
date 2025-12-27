<?php

declare(strict_types=1);

namespace Slendie\Framework;

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

use function env;

final class Mail
{
    public string $smtp_server;
    public string $smtp_username;
    public string $smtp_password;
    public int $smtp_port;
    public string $from;
    public string $from_name;


    public string $to;
    public string $subject;

    private PHPMailer $mail;

    public function __construct()
    {
        $this->smtp_server = env('MAIL_HOST');
        $this->smtp_username = env('MAIL_USERNAME');
        $this->smtp_password = env('MAIL_PASSWORD');
        $this->smtp_port = (int) env('MAIL_PORT', 587);
        $this->from = env('MAIL_FROM_ADDRESS');
        $this->from_name = env('MAIL_FROM_NAME');

        // Configuração no php.ini ou via ini_set()
        ini_set('SMTP', $this->smtp_server);
        ini_set('smtp_port', $this->smtp_port);
        ini_set('sendmail_from', $this->from);

        $this->mail = new PHPMailer(true);
    }

    public function send(string $to, string $subject, string $body, bool $isHtml = false): bool
    {
        try {
            //Server settings
            $this->mail->isSMTP();
            $this->mail->CharSet    = "UTF-8";
            $this->mail->Encoding   = "base64";
            $this->mail->Host       = $this->smtp_server;
            $this->mail->SMTPAuth   = env('MAIL_AUTH', true);
            $this->mail->Username   = $this->smtp_username;
            $this->mail->Password   = $this->smtp_password;
            if (mb_strtolower(env('MAIL_ENCRYPTION')) === 'tls') {
                $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif (mb_strtolower(env('MAIL_ENCRYPTION')) === 'ssl') {
                $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            }
            $this->mail->Port       = $this->smtp_port;

            //Recipients
            $this->mail->setFrom($this->from, $this->from_name);
            $this->mail->addAddress($to);

            //Content
            $this->mail->isHTML($isHtml);
            $this->mail->Subject = $subject;
            $this->mail->Body    = nl2br($body);
            if (!$isHtml) {
                $this->mail->AltBody = strip_tags($body);
            }

            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Message could not be sent. Mailer Error: {$this->mail->ErrorInfo}");
            return false;
        }
    }
}
