<?php

namespace ckvsoft;

/**
 * EmailChecker — Email address verification for the cevian framework
 * and all of its modules.
 *
 * PROVENANCE: Rebuilt on top of the ns1 service implementation (module
 * emailcheck + Emailcheck_Model, ckvsoft 2026-09), which itself was a
 * fork of hbattat/VerifyEmail (MIT, https://github.com/hbattat/VerifyEmail
 * — 2016 vintage, as deployed on ns1). The core idea of the RCPT-TO probe
 * goes back to hbattat (MIT license, attribution noted here). The
 * following are cevian additions: 3-state semantics (passed/unknown/
 * failed) incl. greylist/timeout detection, HELO and timeout handling,
 * XML stopforumspam check, TLD blocklist and status-TTL cache. NO
 * drop-in update against the hbattat upstream is possible — this class
 * intentionally diverges (web-context safe timeouts, fail-open policy)
 * and lives in the cevian repo.
 *
 * Pipeline:
 *   1. Syntax (FILTER_VALIDATE_EMAIL)
 *   2. TLD blocklist (instant fail, no SMTP probe)
 *   3. Domain blocklist (instant fail, no SMTP probe)
 *   4. SMTP RCPT-TO probe against the domain's MX/host (3-state, plus
 *      catch-all detection via a second RCPT-TO probe)
 *   5. StopForumSpam (fail-open, simple XML API)
 *   6. File cache (var/cache/emailcheck, overridable) with status TTLs
 *
 * Status semantics:
 *   'passed'  mailbox exists on the server, accepts mail, not listed
 *   'unknown' probe inconclusive: greylist/4xx, connect fail, read
 *             timeout — caller MUST treat this as fail-open (accept)
 *   'unknown_catchall'  domain accepts RCPT-TO for ANY local part
 *             (accept-all): the address may or may not exist. Caller
 *             MAY apply stricter rules (e.g. moderated registration).
 *   'failed'  hard rejection: null-MX/no MX, RCPT 5xx (mailbox gone),
 *             blocked TLD, blocked domain, listed in stopforumspam
 *
 * Usage in the framework (pluggable anywhere — e.g. login/registration):
 *     $checker = new \ckvsoft\EmailChecker(['verifier_email' => 'www-data@ckvsoft.at']);
 *     $status  = $checker->verify( $email );   // passed | unknown | failed
 *
 * As an input filter: `Input\Validate::emailcheck($value)` — 'unknown'
 * means no error (fail-open), 'failed' -> error message.
 *
 * So the SMTP probe works in a web context without hanging:
 *   fsockopen and fgets use their own short timeouts (default 5 s).
 * Self-authored implementation — deliberately NOT a portable external
 * package (the hbattat fork was transitional; no upstream updates needed).
 */
class EmailChecker {

	const STATUS_PASSED  = 'passed';
	const STATUS_UNKNOWN = 'unknown';
	const STATUS_FAILED  = 'failed';
	const STATUS_CATCHALL = 'unknown_catchall';

	const CACHE_TTL_OK      = 7 * 86400; // 7 days (bots may land in SFS later)
	const CACHE_TTL_FAIL    = 86400;     // 1 day
	const CACHE_TTL_UNKNOWN = 900;       // 15 minutes — short, re-check later
	const CACHE_TTL_CATCHALL = 900;      // 15 minutes — accept-all may change

	const CATCHALL_PROBE_CHARS = 8;      // length of the random local part probe

	const SMTP_PORT      = 25;
	const SMTP_TIMEOUT   = 5;
	const SMTP_HELO      = 'mail.ckvsoft.at';
	const SFS_API       = 'https://api.stopforumspam.org/api'; // .org — &xml for XML response
	const SFS_TIMEOUT   = 3;                                  // seconds
	/** @var array TLD blacklist (instant fail, no SMTP probe) */
	private array $blockedTlds;

	/** @var array exact-domain blacklist (instant fail, no SMTP probe) */
	private array $blockedDomains;

	/** @var string MAIL FROM used for the SMTP probe */
	private string $verifierEmail;

	/** @var string HELO name used for the SMTP probe */
	private string $helo;

	/** @var string cache directory */
	private string $cacheDir;

	/** @var array debug trail of the last probe */
	private array $lastProbe = [];

	/**
	 * @param array|null $opts verifier_email, helo, cache_dir, blocked_tlds, blocked_domains (optional)
	 */
	public function __construct( ?array $opts = null ) {
		$this->blockedTlds = [
			'ru', 'top', 'xyz', 'click', 'gq', 'cf', 'tk',
			'ml', 'loan', 'work', 'icu', 'cam', 'rest',
			'cyou', 'sbs', 'monster',
		];
		$this->blockedDomains = [];
		$this->verifierEmail = 'www-data@ckvsoft.at';
		$this->helo          = self::SMTP_HELO;
		$this->cacheDir      = \ckvsoft\Paths::siteRoot() . 'var/cache/emailcheck';

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
			if ( isset( $opts['blocked_domains'] ) && is_array( $opts['blocked_domains'] ) ) {
				$normalized = [];
				foreach ( $opts['blocked_domains'] as $d ) {
					$d = strtolower( trim( (string) $d ) );
					if ( $d !== '' ) {
						$normalized[] = $d;
					}
				}
				$this->blockedDomains = $normalized;
			}
		}
	}

	/**
	 * Full pipeline for one email address.
	 *
	 * @param string $email The address to check
	 * @return string self::STATUS_* — passed | unknown | failed
	 */
	public function verify( string $email ): string {
		$email = trim( $email );
		$domain = '';
		$this->lastProbe = [
			'email'    => $email,
			'status'   => self::STATUS_FAILED,
			'reason'   => '',
			'smtp_rcpt' => '',
		];

		if ( '' === $email || false === filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			$this->lastProbe['reason'] = 'invalid_email';
			return self::STATUS_FAILED;
		}

		// ---- cache check ----
		$cached = $this->cacheRead( $email );
		if ( $cached !== false ) {
			$this->lastProbe['status'] = $cached;
			$this->lastProbe['reason'] = 'cache';
			return $cached;
		}

		$domain = $this->getDomain( $email );
		$tld    = $this->getTld( $domain );
		$this->lastProbe['domain'] = $domain;

		// ---- TLD blocklist: instant fail, no SMTP probe ----
		if ( in_array( $tld, $this->blockedTlds, true ) ) {
			$this->lastProbe['reason'] = 'tld_blocked';
			$this->cacheWrite( $email, self::STATUS_FAILED );
			return self::STATUS_FAILED;
		}

		// ---- domain blocklist: instant fail, no SMTP probe ----
		if ( $this->isBlockedDomain( $domain ) ) {
			$this->lastProbe['reason'] = 'domain_blocked';
			$this->cacheWrite( $email, self::STATUS_FAILED );
			return self::STATUS_FAILED;
		}

		// ---- SMTP RCPT-TO probe ----
		$answer = $this->smtpVerify( $email );

		if ( $answer === self::STATUS_PASSED ) {
			// SMTP ok — now check stopforumspam
			$sfs = $this->checkStopForumSpam( $email );
			$this->lastProbe['reason'] = 'smtp_verified';

			if ( $sfs === self::STATUS_FAILED ) {
				$this->lastProbe['status'] = self::STATUS_FAILED;
				$this->lastProbe['reason'] = 'sfs_listed';
			} else {
				$this->lastProbe['status'] = self::STATUS_PASSED; // passed / fail-open
			}
		} elseif ( $answer === self::STATUS_UNKNOWN ) {
			// greylist/timeout/4xx/connect-fail — inconclusive, NOT failed
			$this->lastProbe['status'] = self::STATUS_UNKNOWN;
			$this->lastProbe['reason'] .= ( $this->lastProbe['reason'] !== '' ? ',' : '' ) . 'smtp_inconclusive';
		} elseif ( $answer === self::STATUS_CATCHALL ) {
			// accept-all server — address may be fake; caller may moderate
			$this->lastProbe['status'] = self::STATUS_CATCHALL;
			$this->lastProbe['reason'] = ( $this->lastProbe['reason'] !== '' ? $this->lastProbe['reason'] . ',' : '' ) . 'catchall';
		} else {
			$this->lastProbe['status'] = self::STATUS_FAILED;
		}

		$this->cacheWrite( $email, $this->lastProbe['status'] );

		return $this->lastProbe['status'];
	}

	/**
	 * Details of the last probe (for debug tooling).
	 */
	public function get_last_probe(): array {
		return $this->lastProbe;
	}

	// ================================================
	// SMTP probe (own implementation)
	// ================================================

	/**
	 * SMTP probe, returns STATUS_* (passed/unknown/failed).
	 */
	private function smtpVerify( string $email ): string {
		$mx = $this->resolveMailHost( $this->getDomain( $email ) );

		if ( $mx === false ) {
			// domain without MX/A — no mail delivery possible -> hard fail
			$this->lastProbe['reason'] = 'no_mx';
			return self::STATUS_FAILED;
		}

		$errno  = 0;
		$errstr = '';
		$sock   = @fsockopen( $mx, self::SMTP_PORT, $errno, $errstr, self::SMTP_TIMEOUT );

		if ( $sock === false ) {
			// connect failed = greylist/temporary overall possible -> unknown
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

		// HELO / MAIL FROM / RCPT TO — read responses
		fputs( $sock, 'HELO ' . $this->helo . "\r\n" );
		$heloRes = $this->smtpRead( $sock );

		fputs( $sock, 'MAIL FROM:<' . $this->verifierEmail . ">\r\n" );
		$fromRes = $this->smtpRead( $sock );

		fputs( $sock, 'RCPT TO:<' . $email . ">\r\n" );
		$rcptRes = $this->smtpRead( $sock );
		$this->lastProbe['smtp_rcpt'] = (string) $rcptRes;

		$toOk = ( $rcptRes !== null && strncmp( $rcptRes, '250', 3 ) === 0 );

		// Catch-all detection: if the real address is accepted, probe a
		// random non-existent local part on the same domain. An accept-all
		// server answers 250 for it too — then the mailbox "exists" signal
		// is meaningless and the address may be a fake/spam one.
		if ( $toOk ) {
			$probe = $this->randomLocal() . '@' . $this->getDomain( $email );
			fputs( $sock, 'RCPT TO:<' . $probe . ">\r\n" );
			$probeRes = $this->smtpRead( $sock );
			$this->lastProbe['smtp_catchall_probe'] = (string) $probeRes;
		} else {
			$probeRes = null;
		}

		// QUIT + close — ignore result
		@fputs( $sock, "QUIT\r\n" );
		fclose( $sock );

		if ( $fromRes === null || $rcptRes === null || $heloRes === null ) {
			// timeouts — inconclusive
			return self::STATUS_UNKNOWN;
		}

		if ( $toOk ) {
			// Real address accepted; how did the random probe behave?
			if ( $probeRes !== null && strncmp( $probeRes, '250', 3 ) === 0 ) {
				// accept-all server — mailbox existence not verifiable
				$this->lastProbe['reason'] = 'catchall';
				return self::STATUS_CATCHALL;
			}
			if ( $probeRes !== null && strncmp( $probeRes, '5', 1 ) === 0 ) {
				// random local part rejected -> real mailbox check works
				return self::STATUS_PASSED;
			}
			// probe inconclusive (4xx/timeout) — fail-open
			return self::STATUS_PASSED;
		}

		if ( strncmp( $fromRes, '4', 1 ) === 0 || strncmp( $rcptRes, '4', 1 ) === 0 ) {
			// greylist/defer — may succeed on a later try
			$this->lastProbe['reason'] = $this->lastProbe['reason'] ?? '';
			$this->lastProbe['reason'] .= ( $this->lastProbe['reason'] !== '' ? ',' : '' ) . 'smtp_4xx';
			return self::STATUS_UNKNOWN;
		}

		$this->lastProbe['reason'] = 'rcpt_rejected';
		return self::STATUS_FAILED;
	}

	/**
	 * Reads one SMTP response line; null on timeout / dropped connection.
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
	 * Mail host (via MX or A/AAAA fallback) of the domain.
	 * false = no route for mail delivery.
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
		$hasMx    = getmxrr( $domain, $mxhosts, $mxweight );

		// getmxrr() treats the null-MX "0 ." as a single empty host entry
		// (hosts == [""] / ["."]); sometimes it even reports true with an
		// empty list. Either way every host is empty/dot-only -> null-MX.
		$real = [];
		foreach ( $mxhosts as $i => $h ) {
			$h = trim( $h, " \t." );
			if ( $h !== '' ) {
				$real[ $i ] = $h;
			}
		}

		if ( $hasMx && empty( $real ) ) {
			// null-MX (RFC 7505): MX record with empty host, e.g. "0 ." —
			// domain explicitly declares it does not accept mail. Postfix
			// rejects such domains ("does not accept mail (nullMX)").
			$this->lastProbe['reason'] = 'null_mx';
			return false;
		}

		if ( $hasMx ) {
			return $real[ array_search( min( $mxweight ), $mxweight ) ];
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
	 * StopForumSpam XML API.
	 * Response: 'passed' | 'failed' | null (API unreachable/invalid -> fail-open)
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

	private function isBlockedDomain( string $domain ): bool {
		return in_array( strtolower( $domain ), $this->blockedDomains, true );
	}

	/**
	 * Random local part for the catch-all probe (no dots/plus — just
	 * lowercase alphanumerics, so hosts can't pattern-match it away).
	 */
	private function randomLocal(): string {
		$chars = 'abcdefghjkmnpqrstuvwxyz23456789';
		$out   = '';
		$max   = strlen( $chars ) - 1;
		for ( $i = 0; $i < self::CATCHALL_PROBE_CHARS; $i++ ) {
			$out .= $chars[ random_int( 0, $max ) ];
		}
		return $out;
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

		// TTL per status (unknown/catchall = 15 min; catchall checked first
		// because it contains the substring "unknown" in its quoted form)
		if ( strpos( $payload, '"unknown_catchall"' ) !== false ) {
			$ttl = self::CACHE_TTL_CATCHALL;
		} elseif ( strpos( $payload, '"passed"' ) !== false ) {
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
