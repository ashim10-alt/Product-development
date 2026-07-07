<?php
/**
 * mailer.php
 * 
 * Secure Gmail SMTP mailer helper using pure PHP sockets.
 * Connects to smtp.gmail.com on port 587, issues STARTTLS,
 * performs AUTH LOGIN authentication, and sends HTML emails.
 * Correctly handles multiline SMTP responses.
 */

function sendSMTPEmail(string $to, string $subject, string $body): bool {
    $smtp_host = 'smtp.gmail.com';
    $smtp_port = 587;
    $smtp_user = 'asimluitel2@gmail.com';
    $smtp_pass = 'kvvlpjrmjpkgyyhc'; // Spaces stripped for App Password authentication
    $smtp_from = 'asimluitel2@gmail.com';
    $smtp_from_name = 'AIsolution';

    // 1. Establish connection to SMTP server
    $socket = @fsockopen($smtp_host, $smtp_port, $errno, $errstr, 15);
    if (!$socket) {
        error_log("AI-Solution SMTP Connection Failure: $errstr ($errno)");
        return false;
    }
    
    // Helper function to read multiline SMTP response
    $readResponse = function($sock) {
        $response = "";
        while ($line = fgets($sock, 512)) {
            $response .= $line;
            // SMTP status line ends when 4th char is space (e.g. "250 ") instead of "-" (e.g. "250-")
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }
        return $response;
    };

    // Read initial greeting
    $greeting = $readResponse($socket);

    // Helper function to execute and log SMTP dialogue
    $smtpCmd = function($sock, $cmd, $expectedCode) use ($readResponse) {
        fwrite($sock, $cmd . "\r\n");
        $resp = $readResponse($sock);
        if (strpos($resp, (string)$expectedCode) === false) {
            error_log("AI-Solution SMTP Command Failure on '$cmd': Received: $resp");
            return false;
        }
        return true;
    };

    // 2. Say EHLO
    if (!$smtpCmd($socket, "EHLO localhost", 250)) {
        fclose($socket);
        return false;
    }

    // 3. Initiate STARTTLS
    if (!$smtpCmd($socket, "STARTTLS", 220)) {
        fclose($socket);
        return false;
    }

    // 4. Turn on encryption (TLS client mode)
    if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        error_log("AI-Solution SMTP Crypto Enable Failure.");
        fclose($socket);
        return false;
    }

    // 5. Say EHLO again now that we're encrypted
    if (!$smtpCmd($socket, "EHLO localhost", 250)) {
        fclose($socket);
        return false;
    }

    // 6. Authenticate
    if (!$smtpCmd($socket, "AUTH LOGIN", 334)) {
        fclose($socket);
        return false;
    }

    if (!$smtpCmd($socket, base64_encode($smtp_user), 334)) {
        fclose($socket);
        return false;
    }

    if (!$smtpCmd($socket, base64_encode($smtp_pass), 235)) {
        fclose($socket);
        return false;
    }

    // 7. Specify Mail Envelope Sender
    if (!$smtpCmd($socket, "MAIL FROM: <$smtp_from>", 250)) {
        fclose($socket);
        return false;
    }

    // 8. Specify Recipient
    if (!$smtpCmd($socket, "RCPT TO: <$to>", 250)) {
        fclose($socket);
        return false;
    }

    // 9. Send DATA command
    if (!$smtpCmd($socket, "DATA", 354)) {
        fclose($socket);
        return false;
    }

    // 10. Write Mail headers and content
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: =?UTF-8?B?" . base64_encode($smtp_from_name) . "?= <$smtp_from>\r\n";
    $headers .= "To: <$to>\r\n";
    $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    $headers .= "Date: " . date('r') . "\r\n";
    $headers .= "Message-ID: <" . time() . "-" . md5($to) . "@ai-solution.co.uk>\r\n";
    $headers .= "\r\n";

    // Escape sole dots in body if any (SMTP standard)
    $clean_body = str_replace("\n.", "\n..", $body);

    fwrite($socket, $headers . $clean_body . "\r\n.\r\n");
    $resp = $readResponse($socket);
    if (strpos($resp, '250') === false) {
        error_log("AI-Solution SMTP Data Transfer Failure: $resp");
        fclose($socket);
        return false;
    }

    // 11. Say Goodbye
    fwrite($socket, "QUIT\r\n");
    fclose($socket);

    return true;
}
?>
