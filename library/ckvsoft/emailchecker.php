<?php

namespace ckvsoft;

/**
 * EmailChecker — allgemeine Mail-Adressverifikation fuer das cevian framework
 * und alle Module.
 *
 * Kombiniert:
 *   1. TLD-Blocklist (instant-fail, keine SMTP-Probe)
 *   2. SMTP-Verify (Mailbox existiert am MX und nimmt Mail an)
 *   3. StopForumSpam-Abgleich
 * und cachet das Ergebnis file-basiert (var/cache/emailcheck).
 *
 * Statussemantik (3-state, ckvsoft 2026-09):
 *   'passed'  Adresse verifiziert (SMTP 250) und nicht gelistet
 *   'unknown' Probe unbestimmt (Greylist/Connect-Fail/4xx/Read-Timeout) —
 *             Aufrufer hat fail-open vorzusehen ("passed" behandeln),
 *             zwischengespeichert mit kurzer TTL
 *   'failed'  harte Ablehnung: kein MX, 5xx am RCPT, TLD blockiert, SFS-Listung
 *
 * Wo eingebeunden wird (z. B. Login), entscheidet die integrierende Stelle —
 * die Klasse ist bewusst ohne Abhangigkeit zum Modul-Build nutzbar:
 *
 *     $checker = new \ckvsoft\EmailChecker();
 *     $status  = $checker->verify( $email );   // passed | unknown | failed
 *
 * Optionen (Konststruktor oder verify()-Overwrite):
 *   cache_dir      (string) Cache-Verzeichnis
 *   verifier_email (string) MAIL FROM fuer die SMTP-Probe
 *   helo           (string) HELO-Name der Probe
 *   smtp_timeout   (int)    Sekunden
 *   blocked_tlds   (array)  TLD-Liste fuer instant-fail
 */
class EmailChecker {

	const STATUS_PASSED  = 'passed';
	const STATUS_UNKNOWN = 'unknown';
	const STATUS_FAILED  = 'failed';

	const CACHE_TTL_OK     = 7 * 86400; // 7 Tage (bots landen spaeter in SFS)
	const CACHE_TTL_FAIL   = 86400;     // 1 Tag
	const CACHE_TTL_UNKNOWN = 900;      // 15 Minuten — kurz, damit spaeter neu geprueft wird

	const SMTP_PORT    = 25;
	const SMTP_TIMEOUT = 5;             // Greylisting braucht laenger als 2s
	const HTTP_TIMEOUT = 3;
	const SFS_URL      = 'https://api.stopforumspam.com/api?email=';

	/** @var string */
	private $cacheDir;

	/** @var string MAIL FROM fuer die SMTP-Probe */
	private $verifierEmail = 'www-data@ckvsoft.at';

	/** @var string HELO-Name der SMTP-Probe */
	private $helo = 'mail.ckvsoft.at';

	/** @var array TLD-Blacklist (instant-fail, keine SMTP-Probe) */
	private $blockedTlds = [
		'ru', 'top', 'xyz', 'click', 'gq', 'cf', 'tk',
		'ml', 'loan', 'work', 'icu', 'cam', 'rest',
		'cyou', 'sbs', 'monster',
	];

	/**
	 * @param array|null $opts cache_dir, verifier_email, helo, blocked_tlds (alle optional)
	 */
	public function __construct( ?array $opts = null ) {
		// Delegation an eine Modul-Konfig: Emailcheck_Model setzt die Opts
		// aus module.json (verifier_email etc.) — Core-Default reicht sonst.
		if ( ! empty( $opts ) ) {
			if ( isset( $opts['cache_dir'] ) ) {
				$this->cacheDir = $opts['cache_dir'];
			}
			if ( isset( $opts['verifier_email'] ) ) {
				$this->verifierEmail = $opts['verifier_email'];
			}
			if ( isset( $opts['helo'] ) ) {
				$this->helo = $opts['helo'];
			}
			if ( isset( $opts['blocked_tlds'] ) && is_array( $opts['blocked_tlds'] ) ) {
				$this->blockedTlds = $opts['blocked_tlds'];
			}
		}
	}

	/**
	 * Prueft eine Email-Adresse.
	 *
	 * @param string $email Die zu pruefende Adresse
	 * @return string EmailChecker::STATUS_* — passed | unknown | failed
	 */
	public function verify( string $email ): string {
		$email = trim( $email );
		if ( '' === $email ) {
			return self::STATUS_FAILED;
		}

		// ---- Cache pruefen ----
		$cached = $this->cacheRead( $email );
		if ( $cached !== false ) {
			return $cached;
		}

		$domain = $this->getDomain( $email );
		$tld    = $this->getTld( $domain );

		// ---- TLD-Blocklist: instant-fail, keine SMTP-Probe ----
		if ( in_array( $tld, $this->blockedTlds, true ) ) {
			$this->cacheWrite( $email, self::STATUS_FAILED );
			return self::STATUS_FAILED;
		}

		// ---- SMTP-Verify ----
		$ve = new VerifyEmail( $email, $this->verifierEmail, self::SMTP_PORT, self::SMTP_TIMEOUT, $this->helo );
		$verified = $ve->verify();

		if ( $verified ) {
			// SMTP ok — jetzt blocklist-Check (StopForumSpam)
			$sfs = $this->checkStopForumSpam( $email );

			if ( $sfs === self::STATUS_FAILED ) {
				$status = self::STATUS_FAILED;
			} else {
				$status = self::STATUS_PASSED; // 'passed'/null/fail-open
			}
		} elseif ( $ve->isInconclusive() ) {
			// Greylist/Timeout/4xx — unbestimmt, NICHT failed
			$status = self::STATUS_UNKNOWN;
		} else {
			// Hart abgelehnt (5xx = Mailbox existiert nicht) oder kein MX
			$status = self::STATUS_FAILED;
		}

		$this->cacheWrite( $email, $status );

		return $status;
	}

	/**
	 * StopForumSpam-API-Check.
	 * Antwort: 'passed' | 'failed' | null (API-Fail -> fail-open)
	 */
	private function checkStopForumSpam( string $email ) {
		$ctx = stream_context_create( [
			'http' => [
				'timeout'       => self::HTTP_TIMEOUT,
				'ignore_errors' => true,
			],
			'ssl'  => [
				'verify_peer'      => false,
				'verify_peer_name' => false,
			],
		] );

		$json = @file_get_contents( self::SFS_URL . rawurlencode( $email ) . '&xml', false, $ctx );

		if ( $json === false || $json === '' ) {
			return null; // API nicht erreichbar: fail-open
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

	private function getDomain( string $email ): string {
		$email_arr = explode( '@', $email );
		$domain    = array_slice( $email_arr, -1 );
		return $domain[0];
	}

	private function getTld( string $domain ): string {
		$parts = explode( '.', $domain );
		return strtolower( end( $parts ) );
	}

	private function cacheDirPath(): string {
		return $this->cacheDir ?: __DIR__ . '/../../var/cache/emailcheck';
	}

	private function cachePath( string $email ): string {
		return rtrim( $this->cacheDirPath(), '/' ) . '/' . hash( 'md5', strtolower( trim( $email ) ) ) . '.json';
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

		// je Status eigene TTL (unknown = 15 min)
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
