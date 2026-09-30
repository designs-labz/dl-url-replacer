<?php
declare( strict_types=1 );

namespace DesignsLabz\Relocate\Jobs;

use RuntimeException;

/**
 * A job could not be started. The message is translated and safe to show.
 */
final class JobException extends RuntimeException {

	/**
	 * @param string $error_code Machine-readable code, e.g. for WP_Error.
	 * @param string $message    Translated message.
	 * @param int    $status     HTTP status the REST API answers with.
	 */
	public function __construct(
		public readonly string $error_code,
		string $message,
		public readonly int $status = 400
	) {
		parent::__construct( $message );
	}
}
