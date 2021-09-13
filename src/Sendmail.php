<?php

namespace zviryatko\nomail;

/**
 * Simple sendmail command implementation, no authorization, just mail.
 */
class Sendmail implements MailInterface {

  /**
   * @var false|resource
   */
  private $sock;

  /**
   * Server name.
   *
   * @var string
   */
  private string $serverName;

  /**
   * Server address.
   *
   * @var string
   */
  private string $smtpAddress;

  /**
   * Server port.
   *
   * @var int
   */
  private int $smtpPort;

  /**
   * @param string $server_name
   *   Server name.
   * @param string $smtp_address
   *   Server address.
   * @param int $smtp_port
   *   Server port.
   */
  public function __construct(string $server_name, string $smtp_address, int $smtp_port) {
    $this->serverName = $server_name;
    $this->smtpAddress = $smtp_address;
    $this->smtpPort = $smtp_port;
  }

  /**
   * {@inheritDoc}
   */
  public function mail(string $from, string $to, string $subject, string $message): int {
    $this->connect();
    $this->sendHelo();
    $this->sendMailFrom($from);
    $this->sendRcptTo($to);
    $this->sendData();
    $this->sendMessage($from, $to, $subject, $message);
    $this->sendQuit();

    return 0;
  }

  /**
   * Parse response code.
   *
   * @param string $response
   *   Response text.
   *
   * @return int
   *   Parsed response code.
   */
  private function parseCode(string $response): int {
    $parts = explode(' ', $response);
    if (!is_numeric($parts[0])) {
      throw new \InvalidArgumentException(sprintf('SMTP response does not contains response code, see: %s', $response));
    }
    return (int) $parts[0];
  }

  /**
   * Gets SMTP return code to message.
   *
   * @param int $code
   *   Return code.
   *
   * @return string
   *   Return message regarding RFC.
   */
  private function returnCodeMessage(int $code): string {
    $codes = [
      211 => 'System status, or system help reply',
      214 => 'Help message (A response to the HELP command)',
      220 => '<domain> Service ready',
      221 => '<domain> Service closing transmission channel',
      235 => 'Authentication succeeded',
      250 => 'Requested mail action okay, completed',
      251 => 'User not local; will forward',
      252 => 'Cannot verify the user, but it will try to deliver the message anyway',
      334 => '(Server challenge - the text part contains the Base64-encoded challenge)',
      354 => 'Start mail input',
      421 => 'Service not available, closing transmission channel (This may be a reply to any command if the service knows it must shut down)',
      432 => 'A password transition is needed',
      450 => 'Requested mail action not taken: mailbox unavailable (e.g., mailbox busy or temporarily blocked for policy reasons)',
      451 => 'Requested action aborted: local error in processing / IMAP server unavailable',
      452 => 'Requested action not taken: insufficient system storage',
      454 => 'Temporary authentication failure',
      455 => 'Server unable to accommodate parameters',
      500 => 'Syntax error, command unrecognized (This may include errors such as command line too long) / Authentication Exchange line is too long',
      501 => 'Syntax error in parameters or arguments / Cannot Base64-decode Client responses / Client initiated Authentication Exchange (only when the SASL mechanism specified that client does not begin the authentication exchange)',
      502 => 'Command not implemented',
      503 => 'Bad sequence of commands',
      504 => 'Command parameter is not implemented / Unrecognized authentication type',
      521 => 'Server does not accept mail',
      523 => 'Encryption Needed',
      530 => 'Authentication required',
      534 => 'Authentication mechanism is too weak',
      535 => 'Authentication credentials invalid',
      538 => 'Encryption required for requested authentication mechanism',
      550 => 'Requested action not taken: mailbox unavailable (e.g., mailbox not found, no access, or command rejected for policy reasons)',
      551 => 'User not local; please try <forward-path>',
      552 => 'Requested mail action aborted: exceeded storage allocation',
      553 => 'Requested action not taken: mailbox name not allowed',
      554 => 'Transaction has failed (Or, in the case of a connection-opening response, "No SMTP service here") / Message too big for system',
      556 => 'Domain does not accept mail',
    ];
    return $codes[$code] ?? '';
  }

  /**
   * Validate response code.
   *
   * @param int $code
   *   Expected code.
   */
  private function validateResponse(int $code) {
    $data = rtrim(fgets($this->sock));
    $response_code = $this->parseCode($data);
    if ($response_code !== $code) {
      $reason = $this->returnCodeMessage($response_code);
      throw new \InvalidArgumentException($reason ?? sprintf('STMP server is not ready, reason: %s', $data));
    }
  }

  /**
   * Connect to SMTP server.
   *
   * @param string $smtp_address
   *   Server address.
   * @param string $smtp_port
   *   Server port.
   */
  private function connect() {
    $this->sock = fsockopen($this->smtpAddress, $this->smtpPort, $error_code, $error_message, 30);
    if (!$this->sock) {
      throw new \InvalidArgumentException(sprintf('Failed to connect to SMTP server, code %d, reason: %s', $error_code, $error_message));
    }
    $this->validateResponse(220);
  }

  /**
   * Sent HELO request.
   */
  private function sendHelo(): void {
    $data = sprintf("HELO %s\r\n", $this->serverName);
    fwrite($this->sock, $data, strlen($data));
    $this->validateResponse(250);
  }

  /**
   * Add from address.
   *
   * @param string $from
   *   From address.
   */
  private function sendMailFrom(string $from): void {
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
      throw new \InvalidArgumentException('Sender email address is invalid');
    }
    fwrite($this->sock, sprintf("MAIL FROM:<%s>\r\n", $from));
    $this->validateResponse(250);
  }

  /**
   * Add to address.
   *
   * @param string $to
   *   To address.
   */
  private function sendRcptTo(string $to): void {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
      throw new \InvalidArgumentException('Receiver email address is invalid');
    }
    fwrite($this->sock, sprintf("RCPT TO:<%s>\r\n", $to));
    $this->validateResponse(250);
  }

  /**
   * Send DATA request.
   */
  private function sendData(): void {
    fwrite($this->sock, "DATA\r\n");
    $this->validateResponse(354);
  }

  /**
   * Add message body.
   *
   * @param string $message
   *   Message body.
   */
  private function sendMessage(string $from, string $to, string $subject, string $message): void {
    $date = date(DATE_RFC2822);
    $subject = mb_encode_mimeheader($subject);
    $data = "From: <{$from}>\r\n";
    $data .= "To: <{$to}>\r\n";
    $data .= "Date: {$date}\r\n";
    $data .= "Subject: {$subject}\r\n";
    $data .= "MIME-Version: 1.0\r\n";
    $data .= "Content-Type: text/html; charset=UTF-8\r\n";
    $data .= "\r\n";
    $data .= $message;
    $data .= "\r\n.\r\n";
    fwrite($this->sock, $data);
    $this->validateResponse(250);
  }

  /**
   * Send QUIT.
   */
  private function sendQuit(): void {
    fwrite($this->sock, "QUIT\r\n");
    $this->validateResponse(221);
  }

}
