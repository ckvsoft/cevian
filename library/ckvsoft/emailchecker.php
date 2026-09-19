<?php

namespace ckvsoft;

/**
 * EmailChecker — Mail-Adressverifikation fuer das cevian Framework und alle Module.
 *
 * PROVENIENZ: Neufassung auf Basis der ns1-Service-Implementierung
 * (module emailcheck + Emailcheck_Model, ckvsoft 2026-09) und des Forks
 * aus hbattat/VerifyEmail (MIT, https://github.com/hbattat/VerifyEmail —
 * Reachstand 2016, am ns1 damals eingebaut). Kern-Idee der RCPT-TO-Probe
 * geht auf hbattat zurueck (MIT-Lizenz, hiermit zugeordnet). Eigene
 * Bestaende: 3-state-Semantik (passed/unknown/failed) inkl.
 * Greylist-/Timeout-Erkennung, HELO- und Timeout-Handling, XML-SFS-Check,
 * TLD-Blocklist und Status-TTL-Cache. Kein Drop-in-Update gegen den
 * hbattat-Upstream moeglich — Klasse leistet vieles andere als der
 * Upstream (Webkontext tauglicher), startet im cevian-Repo.
 *
 * Pipeline:
 *   1. Syntax (FILTER_VALIDATE_EMAIL)
 *   2. TLD-Blocklist (instant-fail, keine SMTP-Probe)
 *   3. SMTP-RCPT-TO-Probe am MX/Host der Domain (3-state!)
 *   4. StopForumSpam (fail-open, simple XML-API)
 *   5. File-Cache (var/cache/emailcheck uebersteuerbar) mit Status-TTLs
 *
 * Statussemantik:
 *   'passed'  Mailbox existiert am Server und nimmt Mail an, nicht gelistet
 *   'unknown' Probe unbestimmt: Greylist/4xx, Connect-Fail, Read-Timeout —
 *             vom Aufrufer als fail-open zu behandeln (passen lassen!)
 *   'failed'  harte Ablehnung: kein MX, RSET-Token 5xx (Mailbox nicht da),
 *             blockierte TLD, gelistet in StopForumSpam
 *
 * Verwendung im Framework (beliebig einbindbar — z. B. Login/Registrierung):
 *     $checker = new \ckvsoft\EmailChecker(['verifier_email' => 'www-data@ckvsoft.at']);
 *     $status  = $checker->verify( $email );   // passed | unknown | failed
 *
 * Als Input-Filter: `Input\Validate::emailcheck($value)` — 'unknown' = kein
 * Fehler (fail-open), 'failed' -> Fehlermeldung.
 *
 * Damit die SMTP-Probe im Webkontext nicht blockiert:
 *   fsockopen und fgets laufen mit eigenen, kurzen Timeouts (default 5 s).
 * Eigener Code — bewusst KEIN Portable-Fremdpaket (hbattat fork ist
 * ein Schwenker; hier kein need for upstream-Updates).
 */
class EmailChecker {

	const STATUS_PASSED  = 'passed';
	const STATUS_UNKNOWN = 'unknown';
	const STATUS_FAILED  = 'failed';

	const CACHE_TTL_OK      = 7 * 86400; // 7 Tage (bots landen spaeter in SFS)
	const CACHE_TTL_FAIL    = 86400;     // 1 Tag
	const CACHE_TTL_UNKNOWN = 900;       // 15 Minuten — kurz, spaeter neu pruefen

	const SMTP_PORT      = 25;
	const SMTP_TIMEOUT   = 5;
	const SMTP_HELO      = 'mail.ckvsoft.at';
	const SFS_API       = 'https://api.stopforumspam.org/api'; // .org — mit &xml XML-Antwort
	const SFS_TIMEOUT   = 3;                                  // Sekunden
	/** @var array TLD-Blacklist (instant-fail, keine SMTP-Probe) */
	private array $blockedTlds;

	/** @var string MAIL FROM fuer die SMTP-Probe */
	private string $verifierEmail;

	/** @var string HELO-Name der SMTP-Probe */
	private string $helo;

	/** @var string Cache-Verzeichnis */
	private string $cacheDir;

	/** @var array Debug-Spur der letzten Probe */
	private array $lastProbe = [];

	/**
	 * @param array|null $opts verifier_email, helo, cache_dir, blocked_tlds (optional)
	 */
	public function __construct( ?array $opts = null ) {
		$this->blockedTlds = [
			'ru', 'top', 'xyz', 'click', 'gq', 'cf', 'tk',
			'ml', 'loan', 'work', 'icu', 'cam', 'rest',
			'cyou', 'sbs', 'monster',
		];
		$this->verifierEmail = 'www-data@ckvsoft.at';
		$this->helo          = self::SMTP_HELO;
		$this->cacheDir      = __DIR__ . '/../../var/cache/emailcheck';

		if ( is_array( $opts ) ) {
			if ( isset( $opts['verifier_email'] ) && $opts['verifier_email'] !== '' ) {
				$this->verifierEmail = $opts['verifier_email'];
			}
			if ( isset( $opts['helo'] ) && $opts['helo'] !== '' ) {
				$this->helo = $opts['helo'];
			}
			if ( isset( $opts['cache_dir'] ) && $opts['cache_dir'] !== '' ) {
				$this->cacheDir = $opts['cache_dir'];
			}
			if ( isset( $opts['blocked_tlds'] ) && is_array( $opts['blocked_tlds'] ) ) {
				$this->blockedTlds = $opts['blocked_tlds'];
			}
		}
	}

	/**
	 * Vollst. Pipeline auf einer Mail-Adresse.
	 *
	 * @param string $email Die zu pruefende Adresse
	 * @return string self::STATUS_* — passed | unknown | failed
	 */
	public function verify( string $email ): string {
		$email = trim( $email );
		$domain = '';
		$this->lastProbe = [
			'email'    => $email,
			'status'   => self::STATUS_FAILED,
			'reason'   => '',
			'smtp_new' => '',
		];

		if ( '' === $email || false === filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			$this->lastProbe['reason'] = 'invalid_email';
			return self::STATUS_FAILED;
		}

		// ---- Cache pruefen ----
		$cached = $this->cacheRead( $email );
		if ( $cached !== false ) {
			$this->lastProbe['status'] = $cached;
			$this->lastProbe['reason'] = 'cache';
			return $cached;
		}

		$domain = $this->getDomain( $email );
		$tld    = $this->getTld( $domain );
		$this->lastProbe['domain'] = $domain;

		// ---- TLD-Blocklist: instant-fail, keine SMTP-Probe ----
		if ( in_array( $tld, $this->blockedTlds, true ) ) {
			$this->lastProbe['reason'] = 'tld_blocked';
			$this->cacheWrite( $email, self::STATUS_FAILED );
			return self::STATUS_FAILED;
		}

		// ---- SMTP-RCPT-TO-Probe ----
		$answer = $this->smtpVerify( $email );

		if ( $answer === self::STATUS_PASSED ) {
			// SMTP ok — jetzt StopForumSpam
			$sfs = $this->checkStopForumSpam( $email );
			$this->lastProbe['reason'] = 'smtp_verified';

			if ( $sfs === self::STATUS_FAILED ) {
				$this->lastProbe['status'] = self::STATUS_FAILED;
				$this->lastProbe['reason'] = 'sfs_listed';
			} else {
				$this->lastProbe['status'] = self::STATUS_PASSED; // passed / fail-open
			}
		} elseif ( $answer === self::STATUS_UNKNOWN ) {
			// Greylist/Timeout/4xx/Connect-Fail — unbestimmt, NICHT failed
			$this->lastProbe['status'] = self::STATUS_UNKNOWN;
			$this->lastProbe['reason'] .= ( $this->lastProbe['reason'] !== '' ? ',' : '' ) . 'smtp_inconclusive';
		} else {
			$this->lastProbe['status'] = self::STATUS_FAILED;
		}

		$this->cacheWrite( $email, $this->lastProbe['status'] );

		return $this->lastProbe['status'];
	}

	/**
	 * Details der letzen Probe (fuer Debug tomorrow / Tools).
	 */
	public function get_last_probe(): array {
		return $this->lastProbe;
	}

	// ================================================
	// SMTP-Probe (eigene Implementierung)
	// ================================================

	/**
	 * SMTP-verified probe. return STATUS_* (passed/unknown/failed).
	 */
	private function smtpVerify( string $email ): string {
		$mx = $this->resolveMailHost( $this->getDomain( $email ) );

		if ( $mx === false ) {
			// Domain ohne MX/A — Mail-Zustellung unmöglich → hard fail
			$this->lastProbe['reason'] = 'no_mx';
			return self::STATUS_FAILED;
		}

		$errno  = 0;
		$errstr = '';
		$sock   = @fsockopen( $mx, self::SMTP_PORT, $errno, $errstr, self::SMTP_TIMEOUT );

		if ( $sock === false ) {
			// Connect nicht moeglich = Greylist/Temporaries moeglich → unknown
			$this->lastProbe['reason'] = 'connect_failed';
			return self::STATUS_UNKNOWN;
		}

		stream_set_timeout( $sock, self::SMTP_TIMEOUT );

		// Banner
		$banner = $this->smtpRead( $sock );
		if ( $banner === null || strncmp( $banner, '220', 3 ) !== 0 ) {
			fclose( $sock );
			return self::STATUS_UNKNOWN;
		}

		// HELO/MAIL FROM/RCPT TO — Antworten lesen
		fputs( $sock, 'HELO ' . $this->helo . "\r\n" );
		$heloRes = $this->smtpRead( $sock );

		fputs( $sock, 'MAIL FROM:<' . $this->verifierEmail . ">\r\n" );
		$fromRes = $this->smtpRead( $sock );

		fputs( $sock, 'RCPT TO:<' . $email . ">\r\n" );
		$rcptRes = $this->smtpRead( $sock );
		$this->lastProbe['smtp_rcpt'] = (string) $rcptRes;

		// QUIT + schliessen — egal was kommt
		@fputs( $sock, "QUIT\r\n" );
		fclose( $sock );

		if ( $fromRes === null || $rcptRes === null || $heloRes === null ) {
			// Timeouts — unbestimplt
			return self::STATUS_UNKNOWN;
		}

		$fromOk = ( strncmp( $fromRes, '250', 3 ) === 0 );
		$toOk   = ( strncmp( $rcptRes, '250', 3 ) === 0 );

		if ( $toOk ) {
			return self::STATUS_PASSED;
		}

		if ( strncmp( $fromRes, '4', 1 ) === 0 || strncmp( $rcptRes, '4', 1 ) === 0 ) {
			// Greylist/defer — beim naechsten Versuch vielleicht ok
			$this->lastProbe['reason'] = $this->lastProbe['reason'] ?? '';
			$this->lastProbe['reason'] .= ( $this->lastProbe['reason'] !== '' ? ',' : '' ) . 'smtp_4xx';
			return self::STATUS_UNKNOWN;
		}

		$this->lastProbe['reason'] = 'rcpt_rejected';
		return self::STATUS_FAILED;
	}

	/**
	 * Liest eine SMTP-Antwortzeile; null bei Timeout/Verbindungsabriss.
	 */
	private function smtpRead( $sock ) {
		$line = fgets( $sock, 1024 );
		$meta = stream_get_meta_data( $sock );

		if ( ! empty( $meta['timed_out'] ) || $line === false ) {
			return null;
		}

		return trim( $line );
	}

	/**
	 * MX-Host (oder A/AAAA-Fallback) der Domain.
	 * false = keine Route zum Mailempfang.
	 */
	private function resolveMailHost( string $domain ) {
		$domain = ltrim( $domain, '[' );
		$domain = rtrim( $domain, ']' );

		if ( 'IPv6:' === substr( $domain, 0, 5 ) ) {
			$domain = substr( $domain, 5 );
		}

		if ( filter_var( $domain, FILTER_VALIDATE_IP ) ) {
			return $domain;
		}

		$mxhosts  = [];
		$mxweight = [];
		if ( getmxrr( $domain, $mxhosts, $mxweight ) && ! empty( $mxhosts ) ) {
			return $mxhosts[ array_search( min( $mxweight ), $mxweight ) ];
		}

		$recordA = @dns_get_record( $domain, DNS_A );
		if ( ! empty( $recordA ) ) {
			$ip = $recordA[0]['ip'] ?? false;
		} else {
			$recordAAAA = @dns_get_record( $domain, DNS_AAAA );
			$ip = $recordAAAA[0]['ipv6'] ?? false;
		}

		return $ip ?: false;
	}

	// ================================================
	// StopForumSpam
	// ============================================

	/**
	 * StopForumSpam-XML-API.
	 * Antwort: 'passed' | 'failed' | null (API nicht erreichbar / ungueltig → fail-open)
	 */
	private function checkStopForumSpam( string $email ) {
		$ctx = stream_context_create( [
			'http' => [
				'timeout'       => self::SFS_TIMEOUT,
				'ignore_errors' => true,
			],
			'ssl'  => [
				'verify_peer'      => false,
				'verify_peer_name' => false,
			],
		] );

		$json = @file_get_contents( self::SFS_API . '?email=' . rawurlencode( $email ) . '&xml', false, $ctx );

		if ( $json === false || $json === '' ) {
			return null;
		}

		$spam = @simplexml_load_string( $json );

		if ( $spam === false ) {
			return null;
		}

		if ( (string) $spam['success'] === 'true' ) {
			if ( (string) $spam->appears === 'no' ) {
				return self::STATUS_PASSED;
			}
			return self::STATUS_FAILED;
		}

		return null;
	}

	// ====================================
	// Cache
	// ==================================

	private function getDomain( string $email ): string {
		$email_arr = explode( '@', $email );
		return (string) ( array_slice( $email_arr, -1 )[0] ?? '' );
	}

	private function getTld( string $domain ): string {
		$parts = explode( '.', $domain );
		return strtolower( (string) end( $parts ) );
	}

	private function cacheDirPath(): string {
		return rtrim( $this->cacheDir, '/' );
	}

	private function cachePath( string $email ): string {
		return $this->cacheDirPath() . '/' . hash( 'sha1', strtolower( $email ) ) . '.json';
	}

	private function cacheRead( string $email ) {
		$file = $this->cachePath( $email );
		if ( ! is_file( $file ) ) {
			return false;
		}

		$payload = @file_get_contents( $file );
		if ( $payload === false || $payload === '' ) {
			return false;
		}

		$age = time() - (int) @filemtime( $file );

		// TTL je Status (unknown = 15 min)
		if ( strpos( $payload, '"passed"' ) !== false ) {
			$ttl = self::CACHE_TTL_OK;
		} elseif ( strpos( $payload, '"unknown"' ) !== false ) {
			$ttl = self::CACHE_TTL_UNKNOWN;
		} else {
			$ttl = self::CACHE_TTL_FAIL;
		}

		if ( $age > $ttl ) {
			return false;
		}

		$j = json_decode( $payload, true );
		return is_array( $j ) && isset( $j['status'] ) ? (string) $j['status'] : self::STATUS_FAILED;
	}

	private function cacheWrite( string $email, string $status ): void {
		$dir = $this->cacheDirPath();
		if ( ! is_dir( $dir ) && ! @mkdir( $dir, 0700, true ) && ! is_dir( $dir ) ) {
			return;
		}

		$file = $this->cachePath( $email );
		$tmp  = $file . '.' . getmypid() . '.tmp';
		$payload = json_encode( [ 'status' => $status, 'ts' => time() ] );

		if ( $payload && @file_put_contents( $tmp, $payload ) !== false ) {
			@rename( $tmp, $file );
		}
	}

}
