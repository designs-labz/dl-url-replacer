<?php
declare( strict_types=1 );

namespace CraftRoq\Relocate\Jobs;

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
			self::Pending   => __( 'Pending', 'cr-relocate-db' ),
			self::Running   => __( 'Running', 'cr-relocate-db' ),
			self::Completed => __( 'Completed', 'cr-relocate-db' ),
			self::Failed    => __( 'Failed', 'cr-relocate-db' ),
			self::Cancelled => __( 'Cancelled', 'cr-relocate-db' ),
		};
	}
}
