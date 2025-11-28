<?php
/**
 * Funcție helper pentru trimitere email prin Gmail SMTP
 * Folosește PHPMailer pentru trimitere sigură
 */

// Setează encoding-ul intern PHP la UTF-8
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

// Verifică dacă PHPMailer este disponibil
if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    // Încearcă să încarce PHPMailer din diferite locații
    $phpmailer_paths = [
        __DIR__ . '/vendor/autoload.php',
        __DIR__ . '/PHPMailer/PHPMailer.php',
        __DIR__ . '/phpmailer/PHPMailer.php'
    ];
    
    $phpmailer_loaded = false;
    foreach ($phpmailer_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $phpmailer_loaded = true;
            break;
        }
    }
    
    // Dacă nu există PHPMailer, folosește implementare simplă cu socket
    if (!$phpmailer_loaded) {
        // Vom folosi implementare directă SMTP
    }
}

/**
 * Trimite email prin Gmail SMTP
 * 
 * @param string $to Email destinatar
 * @param string $subject Subiect email
 * @param string $message Mesaj HTML
 * @param array $config Configurație SMTP
 * @return array ['success' => bool, 'message' => string]
 */
function trimiteEmailSMTP($to, $subject, $message, $config = []) {
    // Configurație implicită
    $smtp_host = $config['smtp_host'] ?? 'smtp.gmail.com';
    $smtp_port = $config['smtp_port'] ?? 587;
    $smtp_user = $config['smtp_user'] ?? '';
    $smtp_pass = $config['smtp_pass'] ?? '';
    $from_email = $config['from_email'] ?? $smtp_user;
    $from_name = $config['from_name'] ?? 'Biblioteca';
    
    // Verifică dacă PHPMailer este disponibil
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        return trimiteEmailPHPMailer($to, $subject, $message, $config);
    }
    
    // Fallback: folosește implementare SMTP directă
    return trimiteEmailSMTPDirect($to, $subject, $message, $config);
}

/**
 * Trimite email folosind PHPMailer
 */
function trimiteEmailPHPMailer($to, $subject, $message, $config) {
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Configurare SMTP
        $mail->isSMTP();
        $mail->Host = $config['smtp_host'] ?? 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $config['smtp_user'] ?? '';
        $mail->Password = $config['smtp_pass'] ?? '';
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $config['smtp_port'] ?? 587;
        $mail->CharSet = 'UTF-8';
        
        // Expeditor
        $mail->setFrom(
            $config['from_email'] ?? $config['smtp_user'],
            $config['from_name'] ?? 'Biblioteca'
        );
        
        // Destinatar
        $mail->addAddress($to);
        
        // Conținut
        $mail->isHTML(true);
        // Encodăm subiectul pentru UTF-8 cu diacritice
        $mail->Subject = mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n", 0);
        $mail->Body = $message;
        $mail->AltBody = strip_tags($message);
        
        // Trimite
        $mail->send();
        
        return [
            'success' => true,
            'message' => 'Email trimis cu succes!'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Eroare PHPMailer: ' . $mail->ErrorInfo
        ];
    }
}

/**
 * Trimite email folosind socket SMTP direct (fallback)
 */
function trimiteEmailSMTPDirect($to, $subject, $message, $config) {
    $smtp_host = $config['smtp_host'] ?? 'smtp.gmail.com';
    $smtp_port = $config['smtp_port'] ?? 587;
    $smtp_user = $config['smtp_user'] ?? '';
    $smtp_pass = $config['smtp_pass'] ?? '';
    $from_email = $config['from_email'] ?? $smtp_user;
    $from_name = $config['from_name'] ?? 'Biblioteca';
    
    try {
        // Conectare SMTP
        $socket = @fsockopen($smtp_host, $smtp_port, $errno, $errstr, 30);
        
        if (!$socket) {
            return [
                'success' => false,
                'message' => "Nu se poate conecta la $smtp_host:$smtp_port - $errstr ($errno)"
            ];
        }
        
        // Citește răspunsul de conectare
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '220') {
            fclose($socket);
            return [
                'success' => false,
                'message' => "Eroare conectare SMTP: $response"
            ];
        }
        
        // EHLO
        fputs($socket, "EHLO $smtp_host\r\n");
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ') break;
        }
        
        // STARTTLS
        fputs($socket, "STARTTLS\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '220') {
            fclose($socket);
            return [
                'success' => false,
                'message' => "STARTTLS eșuat: $response"
            ];
        }
        
        // Activează criptare TLS
        stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        
        // EHLO din nou după TLS
        fputs($socket, "EHLO $smtp_host\r\n");
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ') break;
        }
        
        // AUTH LOGIN
        fputs($socket, "AUTH LOGIN\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '334') {
            fclose($socket);
            return [
                'success' => false,
                'message' => "AUTH LOGIN eșuat: $response"
            ];
        }
        
        // Username (base64)
        fputs($socket, base64_encode($smtp_user) . "\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '334') {
            fclose($socket);
            return [
                'success' => false,
                'message' => "Autentificare username eșuată: $response"
            ];
        }
        
        // Password (base64) - elimină spațiile din parolă dacă există
        $smtp_pass_clean = str_replace(' ', '', $smtp_pass);
        fputs($socket, base64_encode($smtp_pass_clean) . "\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '235') {
            fclose($socket);
            return [
                'success' => false,
                'message' => "Autentificare parolă eșuată: $response"
            ];
        }
        
        // MAIL FROM
        fputs($socket, "MAIL FROM: <$from_email>\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '250') {
            fclose($socket);
            return [
                'success' => false,
                'message' => "MAIL FROM eșuat: $response"
            ];
        }
        
        // RCPT TO
        fputs($socket, "RCPT TO: <$to>\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '250') {
            fclose($socket);
            return [
                'success' => false,
                'message' => "RCPT TO eșuat: $response"
            ];
        }
        
        // DATA
        fputs($socket, "DATA\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '354') {
            fclose($socket);
            return [
                'success' => false,
                'message' => "DATA eșuat: $response"
            ];
        }
        
        // Headers și body
        // Encodăm numele expeditorului și subiectul pentru UTF-8 cu diacritice
        $from_name_encoded = mb_encode_mimeheader($from_name, 'UTF-8', 'B', "\r\n", 0);
        $subject_encoded = mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n", 0);
        
        $email_data = "From: $from_name_encoded <$from_email>\r\n";
        $email_data .= "To: <$to>\r\n";
        $email_data .= "Subject: $subject_encoded\r\n";
        $email_data .= "MIME-Version: 1.0\r\n";
        $email_data .= "Content-Type: text/html; charset=UTF-8\r\n";
        $email_data .= "Content-Transfer-Encoding: 8bit\r\n";
        $email_data .= "\r\n";
        $email_data .= $message;
        $email_data .= "\r\n.\r\n";
        
        fputs($socket, $email_data);
        $response = fgets($socket, 515);
        
        if (substr($response, 0, 3) != '250') {
            fclose($socket);
            return [
                'success' => false,
                'message' => "Trimitere email eșuată: $response"
            ];
        }
        
        // QUIT
        fputs($socket, "QUIT\r\n");
        fclose($socket);
        
        return [
            'success' => true,
            'message' => 'Email trimis cu succes!'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Eroare: ' . $e->getMessage()
        ];
    }
}

