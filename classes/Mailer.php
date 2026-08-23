<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Settings.php';

class Mailer {
    private $host;
    private $port;
    private $encryption;
    private $username;
    private $password;
    private $fromEmail;
    private $fromName;
    private $companyName;
    private $companyLogo;
    private $lastError = '';

    public function __construct() {
        $settings = new Settings();
        $this->host = $settings->get('smtp_host', '');
        $this->port = intval($settings->get('smtp_port', 587));
        $this->encryption = strtolower($settings->get('smtp_encryption', 'tls'));
        $this->username = $settings->get('smtp_user', '');
        $this->password = $settings->get('smtp_pass', '');
        $this->fromEmail = $settings->get('smtp_from_email', 'noreply@hgabiomed.hu');
        $this->fromName = $settings->get('smtp_from_name', 'HGA Munkaruha Rendszer');
        $this->companyName = $settings->get('company_name', 'HGA Biomed Kft.');
        $this->companyLogo = $settings->get('company_logo', '');
    }

    public function getLastError() {
        return $this->lastError;
    }

    /**
     * Közvetlen SMTP levélküldés socket kapcsolaton keresztül
     */
    public function send($toEmail, $toName, $subject, $htmlBody, $plainBody = '') {
        if (empty($this->host)) {
            $this->lastError = 'Az SMTP szerver nincs beállítva a Rendszerbeállításokban!';
            return false;
        }

        if (empty($plainBody)) {
            $plainBody = strip_tags(str_replace(['<br>', '<p>', '</p>'], ["\n", "\n\n", ""], $htmlBody));
        }

        $timeout = 15;
        $hostPrefix = ($this->encryption === 'ssl') ? 'ssl://' : '';
        $socketHost = $hostPrefix . $this->host;

        $socket = @stream_socket_client($socketHost . ':' . $this->port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
        if (!$socket) {
            $this->lastError = "Nem sikerült kapcsolódni az SMTP szerverhez ({$this->host}:{$this->port}): {$errstr} ({$errno})";
            return false;
        }

        stream_set_timeout($socket, $timeout);

        $response = $this->readResponse($socket);
        if (substr($response, 0, 3) !== '220') {
            $this->lastError = "SMTP üdvözlő hiba: {$response}";
            fclose($socket);
            return false;
        }

        // EHLO
        $this->sendCommand($socket, "EHLO " . gethostname());
        $ehloResp = $this->readResponse($socket);

        // STARTTLS ha TLS van beállítva és nem SSL
        if ($this->encryption === 'tls') {
            $this->sendCommand($socket, "STARTTLS");
            $tlsResp = $this->readResponse($socket);
            if (substr($tlsResp, 0, 3) === '220') {
                $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                    $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
                }
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                    $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
                }
                if (!stream_socket_enable_crypto($socket, true, $cryptoMethod)) {
                    $this->lastError = "TLS titkosítás inicializálása sikertelen!";
                    fclose($socket);
                    return false;
                }
                // Újra EHLO titkosított csatornán
                $this->sendCommand($socket, "EHLO " . gethostname());
                $this->readResponse($socket);
            }
        }

        // AUTH LOGIN ha van felhasználónév
        if (!empty($this->username)) {
            $this->sendCommand($socket, "AUTH LOGIN");
            $authResp = $this->readResponse($socket);
            if (substr($authResp, 0, 3) !== '334') {
                $this->lastError = "AUTH LOGIN nem támogatott vagy hiba: {$authResp}";
                fclose($socket);
                return false;
            }

            $this->sendCommand($socket, base64_encode($this->username));
            $userResp = $this->readResponse($socket);
            if (substr($userResp, 0, 3) !== '334') {
                $this->lastError = "Érvénytelen SMTP felhasználónév: {$userResp}";
                fclose($socket);
                return false;
            }

            $this->sendCommand($socket, base64_encode($this->password));
            $passResp = $this->readResponse($socket);
            if (substr($passResp, 0, 3) !== '235') {
                $this->lastError = "Hibás SMTP jelszó / Hitelesítés sikertelen: {$passResp}";
                fclose($socket);
                return false;
            }
        }

        // MAIL FROM
        $this->sendCommand($socket, "MAIL FROM:<{$this->fromEmail}>");
        $fromResp = $this->readResponse($socket);
        if (substr($fromResp, 0, 3) !== '250') {
            $this->lastError = "MAIL FROM hiba: {$fromResp}";
            fclose($socket);
            return false;
        }

        // RCPT TO
        $this->sendCommand($socket, "RCPT TO:<{$toEmail}>");
        $rcptResp = $this->readResponse($socket);
        if (substr($rcptResp, 0, 3) !== '250' && substr($rcptResp, 0, 3) !== '251') {
            $this->lastError = "Címzett elutasítva ({$toEmail}): {$rcptResp}";
            fclose($socket);
            return false;
        }

        // DATA
        $this->sendCommand($socket, "DATA");
        $dataResp = $this->readResponse($socket);
        if (substr($dataResp, 0, 3) !== '354') {
            $this->lastError = "DATA parancs elutasítva: {$dataResp}";
            fclose($socket);
            return false;
        }

        // MIME fejléc és törzs összeállítása
        $boundary = "----=_Part_" . md5(uniqid(time()));
        $headers = [];
        $headers[] = "From: =?UTF-8?B?" . base64_encode($this->fromName) . "?= <{$this->fromEmail}>";
        $headers[] = "To: " . (!empty($toName) ? "=?UTF-8?B?" . base64_encode($toName) . "?= " : "") . "<{$toEmail}>";
        $headers[] = "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=";
        $headers[] = "Date: " . date('r');
        $headers[] = "Message-ID: <" . md5(uniqid(microtime(true))) . "@" . ($this->host ?: 'localhost') . ">";
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";
        $headers[] = "X-Mailer: HGA-Biomed-Mailer/1.0";

        $body = implode("\r\n", $headers) . "\r\n\r\n";
        
        // Plain text rész
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($plainBody)) . "\r\n";

        // HTML rész
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($htmlBody)) . "\r\n";

        $body .= "--{$boundary}--\r\n";
        $body .= ".\r\n";

        fwrite($socket, $body);
        $finalResp = $this->readResponse($socket);

        $this->sendCommand($socket, "QUIT");
        fclose($socket);

        if (substr($finalResp, 0, 3) === '250') {
            return true;
        } else {
            $this->lastError = "Levélküldési hiba: {$finalResp}";
            return false;
        }
    }

    private function sendCommand($socket, $cmd) {
        fwrite($socket, $cmd . "\r\n");
    }

    private function readResponse($socket) {
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return trim($response);
    }

    /**
     * Elegáns, az arculathoz illeszkedő HTML sablon generátor
     */
    public function wrapInTemplate($title, $headerSubtitle, $contentHtml, $buttonText = '', $buttonUrl = '', $warningText = '') {
        $logoHtml = '';
        if ($this->companyLogo) {
            $logoPath = __DIR__ . '/../' . strtok($this->companyLogo, '?');
            if (file_exists($logoPath)) {
                $ext = pathinfo($logoPath, PATHINFO_EXTENSION);
                $b64 = base64_encode(file_get_contents($logoPath));
                $logoHtml = '<img src="data:image/' . $ext . ';base64,' . $b64 . '" alt="' . htmlspecialchars($this->companyName) . '" style="max-height: 48px; max-width: 200px; object-fit: contain; margin-bottom: 12px;">';
            }
        }

        if (empty($logoHtml)) {
            $logoHtml = '<div style="display: inline-block; padding: 10px 18px; background-color: #16a34a; color: #ffffff; font-weight: 800; font-size: 18px; border-radius: 12px; margin-bottom: 12px; letter-spacing: 0.5px;">👔 ' . htmlspecialchars($this->companyName) . '</div>';
        }

        $buttonHtml = '';
        if (!empty($buttonText) && !empty($buttonUrl)) {
            $buttonHtml = '
            <div style="text-align: center; margin: 32px 0 24px 0;">
              <a href="' . htmlspecialchars($buttonUrl) . '" target="_blank" style="display: inline-block; padding: 14px 32px; background-color: #16a34a; color: #ffffff; font-weight: 700; font-size: 15px; text-decoration: none; border-radius: 12px; box-shadow: 0 4px 12px rgba(22, 163, 74, 0.25);">' . htmlspecialchars($buttonText) . '</a>
            </div>
            <p style="font-size: 12px; color: #64748b; text-align: center; margin-top: 12px; word-break: break-all;">
              Ha a gomb nem működik, másolja be ezt a linket a böngészőjébe:<br>
              <a href="' . htmlspecialchars($buttonUrl) . '" style="color: #16a34a; text-decoration: underline;">' . htmlspecialchars($buttonUrl) . '</a>
            </p>';
        }

        $warningHtml = '';
        if (!empty($warningText)) {
            $warningHtml = '
            <div style="margin: 24px 0; padding: 14px 18px; background-color: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 8px; font-size: 13px; color: #92400e; line-height: 1.5;">
              <strong>⚠️ Biztonsági figyelmeztetés:</strong> ' . $warningText . '
            </div>';
        }

        return '
        <!DOCTYPE html>
        <html lang="hu">
        <head>
          <meta charset="UTF-8">
          <meta name="viewport" content="width=device-width, initial-scale=1.0">
          <title>' . htmlspecialchars($title) . '</title>
        </head>
        <body style="margin: 0; padding: 0; background-color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; color: #1e293b; -webkit-font-smoothing: antialiased;">
          <table border="0" cellpadding="0" cellspacing="0" width="100%" style="table-layout: fixed; background-color: #f8fafc; padding: 40px 10px;">
            <tr>
              <td align="center">
                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 580px; background-color: #ffffff; border-radius: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; overflow: hidden;">
                  
                  <!-- FEJLÉC -->
                  <tr>
                    <td style="padding: 36px 36px 24px 36px; text-align: center; background: linear-gradient(180deg, #f0fdf4 0%, #ffffff 100%); border-bottom: 1px solid #f1f5f9;">
                      ' . $logoHtml . '
                      <h1 style="margin: 8px 0 4px 0; font-size: 22px; font-weight: 800; color: #0f172a; letter-spacing: -0.5px;">' . htmlspecialchars($title) . '</h1>
                      <p style="margin: 0; font-size: 13px; color: #64748b; font-weight: 500;">' . htmlspecialchars($headerSubtitle) . '</p>
                    </td>
                  </tr>

                  <!-- TÖRZS -->
                  <tr>
                    <td style="padding: 32px 36px; font-size: 14px; line-height: 1.6; color: #334155;">
                      ' . $contentHtml . '
                      ' . $buttonHtml . '
                      ' . $warningHtml . '
                    </td>
                  </tr>

                  <!-- LÁBLÉC -->
                  <tr>
                    <td style="padding: 24px 36px; background-color: #f8fafc; border-top: 1px solid #e2e8f0; text-align: center; font-size: 11px; color: #94a3b8; line-height: 1.6;">
                      &copy; ' . date('Y') . ' ' . htmlspecialchars($this->companyName) . ' &bull; Munkaruha és Mosodai Nyilvántartó Rendszer<br>
                      Készítette: <strong style="color: #64748b;">Euro-Creativity Kft.</strong><br>
                      <span style="font-size: 10px; color: #cbd5e1;">Ez egy automatikusan generált rendszerüzenet, kérjük ne válaszoljon rá közvetlenül.</span>
                    </td>
                  </tr>

                </table>
              </td>
            </tr>
          </table>
        </body>
        </html>';
    }

    /**
     * 1. Teszt Email küldése
     */
    public function sendTestEmail($toEmail) {
        $title = "Teszt Értesítés & SMTP Kapcsolat Sikeres";
        $subtitle = "HGA Munkaruha és Mosodai Rendszer";
        $content = "
          <p>Kedves Rendszergazda!</p>
          <p>Ez egy automatikus tesztüzenet a <strong>{$this->companyName}</strong> Munkaruha és Mosodai Nyilvántartó Rendszeréből.</p>
          <div style='background-color: #f0fdf4; border: 1px solid #bbf7d0; padding: 16px; border-radius: 12px; margin: 20px 0;'>
            <p style='margin: 0; color: #166534; font-weight: 700; font-size: 15px;'>✅ Az SMTP kapcsolat tökéletesen működik!</p>
            <p style='margin: 6px 0 0 0; color: #15803d; font-size: 12px;'>A rendszer készen áll az elfelejtett jelszó visszaállítások és az új munkatársak meghívóleveleinek kiküldésére.</p>
          </div>
          <p>Küldés időpontja: <strong>" . date('Y.m.d H:i:s') . "</strong></p>
        ";

        $html = $this->wrapInTemplate($title, $subtitle, $content);
        return $this->send($toEmail, 'Rendszergazda', $title, $html);
    }

    /**
     * 2. Elfelejtett Jelszó Visszaállító Email
     */
    public function sendPasswordResetEmail($user, $resetUrl) {
        $title = "Jelszó Visszaállítási Kérelem";
        $subtitle = "Biztonságos hozzáférés helyreállítása";
        $userName = htmlspecialchars($user['full_name'] ?: $user['username']);
        
        $content = "
          <p>Kedves <strong>{$userName}</strong>!</p>
          <p>Jelszó-visszaállítási kérelmet kezdeményeztek a felhasználói fiókjához (Felhasználónév: <strong>{$user['username']}</strong>).</p>
          <p>Az új jelszava beállításához kérjük kattintson az alábbi gombra:</p>
        ";

        $warning = "A fenti link <strong>1 órán keresztül érvényes</strong>. Amennyiben nem Ön kezdeményezte a jelszó visszaállítását, kérjük hagyja figyelmen kívül ezt a levelet, fiókja és jelszava változatlan marad.";

        $html = $this->wrapInTemplate($title, $subtitle, $content, "Új Jelszó Beállítása", $resetUrl, $warning);
        return $this->send($user['email'], $user['full_name'], "Jelszó visszaállítás - {$this->companyName}", $html);
    }

    /**
     * 3. Új Felhasználó Meghívó / Fiókaktiváló Email
     */
    public function sendUserInvitationEmail($user, $activationUrl) {
        $title = "Meghívó a Munkaruha Rendszerbe";
        $subtitle = "Új felhasználói fiók aktiválása";
        $userName = htmlspecialchars($user['full_name'] ?: $user['username']);
        $roleName = ($user['role'] === 'admin') ? 'Rendszergazda' : (($user['role'] === 'viewer') ? 'Vezető / Megtekintő' : 'Operátor (Raktáros)');

        $content = "
          <p>Kedves <strong>{$userName}</strong>!</p>
          <p>Önnek felhasználói hozzáférést hoztak létre a <strong>{$this->companyName}</strong> Munkaruha és Mosodai Nyilvántartó Rendszerében.</p>
          <table style='width: 100%; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px; margin: 16px 0; font-size: 13px;'>
            <tr><td style='color: #64748b; padding: 4px 8px; width: 40%;'>Felhasználónév:</td><td style='font-weight: 700; font-family: monospace; color: #0f172a; padding: 4px 8px;'>{$user['username']}</td></tr>
            <tr><td style='color: #64748b; padding: 4px 8px;'>Szerepkör:</td><td style='font-weight: 700; color: #0f172a; padding: 4px 8px;'>{$roleName}</td></tr>
          </table>
          <p>A fiókja élesítéséhez és az első jelszava megadásához kérjük kattintson az alábbi aktiváló gombra:</p>
        ";

        $warning = "Az aktiváló link <strong>48 órán keresztül érvényes</strong>. Kérjük válasszon biztonságos jelszót a belépéshez!";

        $html = $this->wrapInTemplate($title, $subtitle, $content, "Fiók Aktiválása & Jelszó Megadása", $activationUrl, $warning);
        return $this->send($user['email'], $user['full_name'], "Fiók aktiválása - {$this->companyName}", $html);
    }
}
