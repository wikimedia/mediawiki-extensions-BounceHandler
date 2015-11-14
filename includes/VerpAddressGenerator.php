<?php
/**
 * Class VerpAddressGenerator
 *
 * Generates a VERP return path address of the form
 * wikiId-base36( $UserID )-base36( $Timestamp )-hash( $algorithm, $key, $prefix )@$email_domain
 * for every recipient address
 *
 * @file
 * @ingroup Extensions
 * @author Tony Thomas, Kunal Mehta, Jeff Green
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */
class VerpAddressGenerator {
	/**
	 * @var string
	 */
	protected $prefix;
	/**
	 * @var string
	 */
	protected $algorithm;

	/**
	 * @var string
	 */
	protected $secretKey;

	/**
	 * @var string
	 */
	protected $domain;

	/**
	 * @param string $prefix
	 * @param string $algorithm
	 * @param string $secretKey
	 * @param string $domain
	 */
	public function __construct( $prefix, $algorithm, $secretKey, $domain ) {
		$this->prefix = $prefix;
		$this->algorithm = $algorithm;
		$this->secretKey = $secretKey;
		$this->domain = $domain;
	}

	/**
	 * Generate VERP address
	 * The generated hash is cut down to 12 ( 96 bits ) instead of the full 120 bits.
	 * For attacks attempting to recover the hmac key, this makes the attackers job harder by giving
	 * them less information to work from.
	 * This makes brute force attacks easier. An attacker would be able to brute force the signature by
	 * sending an average of 2^95 emails to us. We would (hopefully) notice that.
	 * This would make finding a collision slightly easier if the secret key was known,
	 * but the constraints on each segment (wiki id must be valid, timestamp needs to be within a certain limit),
	 * combined with the difficulty of finding collisions when the key is unknown, makes this virtually impossible.
	 *
	 * @param int $uid user-id of the failing user
	 * @return string $ReturnPath address
	 */
	public function generateVERP( $uid ) {
		// Get the time in Unix timestamp to compare with seconds
		$timeNow = wfTimestamp();
		$email_domain = $this->domain;
		// Creating the VERP address prefix as wikiId-base36( $UserID )-base36( $Timestamp )
		// and the generated VERP return path is of the form :
		// wikiId-base36( $UserID )-base36( $Timestamp )-hash( $algorithm, $key, $prefix )@$email_domain
		// We dont want repeating '-' in our WikiId
		$wikiId = str_replace( '-', '.', wfWikiID() );
		$email_prefix = $this->prefix . '-' . $wikiId . '-' . base_convert( $uid, 10, 36) .
			'-' . base_convert( $timeNow, 10, 36);
		$verp_hash = base64_encode(
			substr( hash_hmac( $this->algorithm, $email_prefix, $this->secretKey, true ), 0, 12 )
		);
		$returnPath = $email_prefix . '-' . $verp_hash . '@' . $email_domain;
		return $returnPath;
	}
}
