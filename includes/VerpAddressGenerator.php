<?php
/**
 * Class VerpAddressGenerator
 *
 * Generates a VERP return path address of the form
 * wikiId-base36( $UserID )-base36( $Timestamp )-hash( $algorithm, $key, $prefix )@$email_domain
 * for every recipient address
 *
 * Copyright (c) 2014, Tony Thomas <01tonythomas@gmail.com>
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @author Tony Thomas <01tonythomas@gmail.com>
 * @author Legoktm <legoktm@gmail.com>
 * @author Jeff Green <jgreen@wikimedia.org>
 * @license GPL-2.0
 * @ingroup Extensions
 */
class VerpAddressGenerator {
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
	protected $server;

	/**
	 * @var array
	 */
	protected $smtp;

	/**
	 * @param string $algorithm
	 * @param string $secretKey
	 * @param string $server
	 * @param array $smtp The SMTP setting configurations
	 */
	public function __construct( $algorithm, $secretKey, $server, $smtp ) {
		$this->algorithm = $algorithm;
		$this->secretKey = $secretKey;
		$this->server = $server;
		$this->smtp = $smtp;
	}

	/**
	 * Generate VERP address
	 *
	 * @param string recipient email
	 * @return string ReturnPath address
	 */
	public function generateVERP( $uid ) {
		// Get the time in Unix timestamp to compare with seconds
		$timeNow = wfTimestamp();
		if(  is_array( $this->smtp ) && isset( $this->smtp['IDHost'] ) && $this->smtp['IDHost'] ) {
			$email_domain = $this->smtp['IDHost'];
		} else {
			$url = wfParseUrl( $this->server );
			$email_domain = $url['host'];
		}
		// Creating the VERP address prefix as wikiId-base36( $UserID )-base36( $Timestamp )
		// and the generated VERP return path is of the form :
		// wikiId-base36( $UserID )-base36( $Timestamp )-hash( $algorithm, $key, $prefix )@$email_domain
		// We dont want repeating '-' in our WikiId
		$wikiId = str_replace( '-', '.', wfWikiID() );
		$email_prefix = $wikiId. '-'. base_convert( $uid, 10, 36). '-'. base_convert( $timeNow, 10, 36);
		$verp_hash = hash_hmac( $this->algorithm, $email_prefix, $this->secretKey );
		$returnPath = $email_prefix. '-' .$verp_hash. '@' .$email_domain;
		return $returnPath;
	}
}