<?php
declare( strict_types=1 );

namespace DesignsLabz\Relocate\Jobs;

enum JobStatus: string {

	case Pending   = 'pending';
	case Running   = 'running';
	case Completed = 'completed';
	case Failed    = 'failed';
	case Cancelled = 'cancelled';

	public function is_finished(): bool {
		return in_array( $this, array( self::Completed, self::Failed, self::Cancelled ), true );
	}

	public function label(): string {
		return match ( $this ) {
			self::Pending   => __( 'Pending', 'dl-relocate-db' ),
			self::Running   => __( 'Running', 'dl-relocate-db' ),
			self::Completed => __( 'Completed', 'dl-relocate-db' ),
			self::Failed    => __( 'Failed', 'dl-relocate-db' ),
			self::Cancelled => __( 'Cancelled', 'dl-relocate-db' ),
		};
	}
}
