<?php

namespace ckvsoft;

/**
 * VerifyEmail — SMTP-Verify einer Mail-Adresse (RCPT-TO-Probe) mit 3-state-Ergebnis.
 *
 * FORK-PROVENIENZ (ckvsoft 2026-09):
 *   Upstream: https://github.com/hbattat/VerifyEmail (verify.class.php, MIT, Stand
 *   der Portierung im incident-Backup ns1: /vhome/backup/.../emailcheck-module.bak-*).
 *
 * GEANDERT ggü. Original — KEIN drop-in-Update moeglich, Rueckport des Patches nötig:
 *   - Namespace VE -> ckvsoft (cevian-Autoloader: library/ckvsoft/verifyemail.php)
 *   - HELO war hart auf mail.ckvsoft.at, jetzt Konstruktor-Param $helo
 *   - neu: isInconclusive()/getLastRcptResponse() — Greylist/Timeout/4xx laesst
 *     die Probe NICHT als 'ungueltig' werten (3-state: passed/unknown/failed)
 *   - read_line()-Timeout-Erkennung (saemt überommen aus der Server-Version)
 *   - find_mx(): gewichtsloser-MX-Fallback stabilisiert
 *
 * Portiert aus dem alten service-Modul VE\VerifyEmail (hbattat VerifyEmail, MIT).
 * Namespace auf ckvsoft umgestellt, damit der cevian-Autoloader die Klasse
 * library/ckvsoft/verifyemail.php laden kann — sie ist damit im ganzen
 * Framework und aus jedem Modul heraus nutzbar.
 *
 * Statussupport (ckvsoft 2026-09):
 *   verify()         -> bool (Mailbox akzeptiert RCPT-TO-250 oder nicht)
 *   isInconclusive() -> true, wenn die Probe *unbestimmt* war:
 *                       Connect-Fail / Read-Timeout / 4xx-Response (Greylisting).
 *                       Ein 'unknown' darf NICHT als 'failed' gewertet werden,
 *                       sonst werden legitime Mailboxen geblockt.
 *   getLastRcptResponse() -> letzte RCPT-TO-Antwort des Mailservers (fuer Logs).
 */
class VerifyEmail {

	public $email;
	public $verifier_email;
	public $port;
	public $helo;
	private $mx;
	private $connect;
	private $errors = [];
	private $debug;
	private $timeout;
	private $inconclusive = false;
	private $last_rcpt_response = '';

	public function __construct( string $email, string $verifier_email, int $port = 25, int $timeout = 3, string $helo = 'mail.ckvsoft.at' ) {
		$this->email         = $email;
		$this->verifier_email = $verifier_email;
		$this->port          = $port;
		$this->timeout       = $timeout;
		$this->helo          = $helo !== '' ? $helo : 'mail.ckvsoft.at';

		$this->debug   = [];
		$this->errors  = [];
		$this->debug[] = 'initialized with Email: ' . $email . ', Verifier Email: ' . $verifier_email . ', Port: ' . $port . ', Timeout: ' . $timeout . ', HELO: ' . $this->helo;
	}

	public function set_verifier_email( string $email ): void {
		$this->verifier_email = $email;
	}

	public function get_verifier_email(): string {
		return $this->verifier_email;
	}

	public function set_email( string $email ): void {
		$this->email = $email;
	}

	public function get_email(): string {
		return $this->email;
	}

	public function set_port( int $port ): void {
		$this->port = $port;
	}

	public function get_port(): int {
		return $this->port;
	}

	public function set_timeout( int $timeout ): void {
		$this->timeout = $timeout;
	}

	public function get_timeout(): int {
		return $this->timeout;
	}

	public function get_errors(): array {
		return [ 'errors' => $this->errors ];
	}

	public function isInconclusive(): bool {
		return $this->inconclusive;
	}

	public function getLastRcptResponse(): string {
		return $this->last_rcpt_response;
	}

	public function get_debug(): array {
		return $this->debug;
	}

	/**
	 * Fuehrt die SMTP-Probe durch.
	 *
	 * @return bool true = Mailbox akzeptiert RCPT-TO-250,
	 *              false = abgelehnt ODER unbestimmt (dann isInconclusive() == true)
	 */
	public function verify(): bool {
		$this->debug[] = 'Verify function was called.';

		$is_valid = false;

		$this->debug[] = 'Finding MX record...';
		$this->find_mx();

		if ( ! $this->mx ) {
			$this->debug[] = 'No MX record was found.';
			$this->add_error( '100', 'No suitable MX records found.' );
			return $is_valid;
		}
		$this->debug[] = 'Found MX: ' . $this->mx;

		$this->debug[] = 'Connecting to the server...';
		$this->connect_mx();

		if ( ! $this->connect ) {
			$this->debug[] = 'Connection to server failed.';
			$this->add_error( '110', 'Could not connect to the server.' );
			$this->inconclusive = true;
			return $is_valid;
		}
		$this->debug[] = 'Connection to server was successful.';

		$this->debug[] = 'Starting verification...';
		$out = $this->read_line();

		if ( $out !== false && preg_match( '/^220/i', $out ) ) {
			$this->debug[] = 'Got a 220 response. Sending HELO...';
			fputs( $this->connect, 'HELO ' . $this->helo . "\r\n" );
			$out = $this->read_line();
			$this->debug[] = 'Response: ' . $out;

			$this->debug[] = 'Sending MAIL FROM...';
			fputs( $this->connect, 'MAIL FROM:<' . $this->verifier_email . ">\r\n" );
			$from = $this->read_line();
			$this->debug[] = 'Response: ' . $from;

			$this->debug[] = 'Sending RCPT TO...';
			fputs( $this->connect, 'RCPT TO:<' . $this->email . ">\r\n" );
			$to = $this->read_line();
			$this->debug[] = 'Response: ' . $this->email;
			$this->debug[] = 'Response: ' . $to;
			$this->last_rcpt_response = (string) ( $to ?: '' );

			$this->debug[] = 'Sending QUIT...';
			fputs( $this->connect, "QUIT\r\n" );
			fclose( $this->connect );
			$this->connect = null;

			if ( $from === false || $to === false ) {
				// Read timeout — kein verlaessliches Ergebnis
				$this->inconclusive = true;
				return $is_valid;
			}

			$this->debug[] = 'Looking for 250 response...';
			if ( ! preg_match( '/^250/i', $from ) || ! preg_match( '/^250/i', $to ) ) {
				$this->debug[] = 'Not found! Email is invalid.';
				$is_valid = false;
				// 4xx (450/451/452...) = defer/greylist, nicht endgueltig ungueltig
				if ( preg_match( '/^4\d\d/', $to ) === 1 || preg_match( '/^4\d\d/', $from ) === 1 ) {
					$this->inconclusive = true;
				}
			} else {
				$this->debug[] = 'Found! Email is valid.';
				$is_valid = true;
			}
		} else {
			$this->debug[] = 'Encountered an unknown response code.';
			if ( $this->connect ) {
				fclose( $this->connect );
				$this->connect = null;
			}
			$this->inconclusive = true;
		}

		return $is_valid;
	}

	private function get_domain(): string {
		$email_arr = explode( '@', $this->email );
		$domain    = array_slice( $email_arr, -1 );
		return $domain[0];
	}

	/**
	 * Liest eine Zeile vom Socket und erkennt Read-Timeouts.
	 * Gibt false bei Timeout oder EOF zurueck.
	 */
	private function read_line() {
		if ( ! $this->connect ) {
			return false;
		}

		$line = fgets( $this->connect, 1024 );
		$meta = stream_get_meta_data( $this->connect );

		if ( ! empty( $meta['timed_out'] ) ) {
			$this->debug[] = 'Read timed out after ' . $this->timeout . 's.';
			$this->add_error( '120', 'Read timeout while talking to the mail server.' );
			return false;
		}

		if ( $line === false ) {
			$this->debug[] = 'Connection closed by peer.';
			$this->add_error( '121', 'Connection closed by peer.' );
			return false;
		}

		return $line;
	}

	private function find_mx(): void {
		$domain = $this->get_domain();
		$mx_ip  = false;
		$domain = ltrim( $domain, '[' );
		$domain = rtrim( $domain, ']' );

		if ( 'IPv6:' === substr( $domain, 0, strlen( 'IPv6:' ) ) ) {
			$domain = substr( $domain, strlen( 'IPv6' ) + 1 );
		}

		$mxhosts  = [];
		$mxweight = [];

		if ( filter_var( $domain, FILTER_VALIDATE_IP ) ) {
			$mx_ip = $domain;
		} else {
			getmxrr( $domain, $mxhosts, $mxweight );
		}

		if ( ! empty( $mxhosts ) ) {
			if ( ! empty( $mxweight ) ) {
				$mx_ip = $mxhosts[ array_search( min( $mxweight ), $mxweight ) ];
			} else {
				$mx_ip = $mxhosts[0];
			}
		} elseif ( ! $mx_ip ) {
			// Kein MX: Fallback auf A/AAAA der Domain selbst
			$record_a = dns_get_record( $domain, DNS_A );
			if ( ! empty( $record_a ) && ! empty( $record_a[0]['ip'] ) ) {
				$mx_ip = $record_a[0]['ip'];
			} else {
				$record_aaaa = dns_get_record( $domain, DNS_AAAA );
				if ( ! empty( $record_aaaa ) && ! empty( $record_aaaa[0]['ipv6'] ) ) {
					$mx_ip = $record_aaaa[0]['ipv6'];
				}
			}
		}

		$this->mx = $mx_ip;
	}

	private function connect_mx(): void {
		$errno  = 0;
		$errstr = '';

		$this->connect = @fsockopen( $this->mx, $this->port, $errno, $errstr, $this->timeout );

		if ( $this->connect ) {
			stream_set_timeout( $this->connect, $this->timeout );
		} else {
			$this->debug[] = 'fsockopen failed: [' . $errno . '] ' . $errstr;
		}
	}

	private function add_error( string $code, string $msg ): void {
		$this->errors[] = [ 'code' => $code, 'message' => $msg ];
	}

}
