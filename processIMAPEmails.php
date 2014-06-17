<?php
//Maintenance script to run Bounce Handler cleanups.

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../..';
}
// Require base maintenance class
require_once( "$IP/maintenance/Maintenance.php" );

class BounceHandlerClearance extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->mDescription = "Connect to IMAP server and take action on bounces";
		$this->addArg( "imapuser", "IMAP account Username", false );
		$this->addArg( "imappass", "IMAP account Password", false );
	}
	public function execute() {
		global $wgIMAPuser, $wgIMAPpass, $wgIMAPserver;
		$imapuser = $this->getArg( 0 );
		$imappass = $this->getArg( 1 );
		if ( !$imapuser && $wgIMAPuser === null ) {
			$this->error( "invalid IMAP username.", true );
		} else {
			$imapuser = $wgIMAPuser === null ? $imapuser : $wgIMAPuser;
		}
		if ( !$imappass && $wgIMAPpass === null ) {
			$this->error( "invalid IMAP password.", true );
		} else {
			$imappass = $wgIMAPpass === null ?  $imappass : $wgIMAPpass;
		}
		if ( $wgIMAPserver === null ) {
			$this->error( "invalid IMAP server.", true );
		}

		// Establish IMAP connection
		$conn = imap_open( $wgIMAPserver, $imapuser, $imappass );
		if ( !$conn ) {
			$this->error( imap_last_error(), 1 );
		}
		$num_recent = imap_num_recent( $conn );

		// Establish Database connection
		$dbr = wfGetDB( DB_MASTER );
                for ( $n = 0; $n <= $num_recent; $n++ ) {
			// Suppress unwanted warnings during IMAP connection
			wfSuppressWarnings();

			$header = imap_header( $conn, $n );

			wfRestoreWarnings();
			// Fetch only unread mails
			if ( $header ) {
				$rowData = array(
					'br_user' => $header->from[0]->mailbox. "@" . $header->from[0]->host,
					'br_timestamp' => $header->date,
					'br_reason' => $header->subject
					);
				$dbr->insert( 'bounce_records', $rowData, __METHOD__ );
			}
		}

		// delete messages
		imap_expunge($conn);

		// close IMAP connection
		imap_close($conn);
	}
}
$maintClass = 'BounceHandlerClearance';
require_once( RUN_MAINTENANCE_IF_MAIN );