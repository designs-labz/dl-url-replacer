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
			self::Pending   => __( 'Pending', 'designslabz-relocate' ),
			self::Running   => __( 'Running', 'designslabz-relocate' ),
			self::Completed => __( 'Completed', 'designslabz-relocate' ),
			self::Failed    => __( 'Failed', 'designslabz-relocate' ),
			self::Cancelled => __( 'Cancelled', 'designslabz-relocate' ),
		};
	}
}
