<?php
declare( strict_types=1 );

namespace DesignsLabz\Relocate\Jobs;

use DesignsLabz\Relocate\Database\TableLayout;
use RuntimeException;

/**
 * A gzipped SQL file holding the original value of every cell a live job changes,
 * as UPDATE statements keyed on each row's primary or unique key.
 *
 * It is a recovery aid, not an undo button: running it puts those cells back
 * exactly as they were, including over any edits made after the job.
 *
 * Files live in uploads/dlz-relocate/ under unguessable names. The folder is
 * closed off for Apache, but that does not help on nginx, so files are only
 * ever handed out through the authenticated download handler.
 *
 * The native gz* functions are used because WP_Filesystem cannot append, and
 * each window's statements must reach the disk before its transaction commits.
 * phpcs:disable WordPress.WP.AlternativeFunctions
 */
final class BeforeImage {

	private const DIRECTORY = 'dlz-relocate';

	public function __construct( private \wpdb $wpdb ) {}

	/**
	 * @return string File name, relative to the before-image directory.
	 * @throws RuntimeException When the file cannot be created.
	 */
	public function create( Job $job ): string {
		$directory = $this->directory();

		if ( ! wp_mkdir_p( $directory ) ) {
			throw new RuntimeException( sprintf( 'Could not create the folder %s.', $directory ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Escaped where it is displayed.
		}

		$this->protect( $directory );

		$file = sprintf( 'job-%d-%s.sql.gz', $job->id, wp_generate_password( 24, false ) );

		$this->write(
			$file,
			implode(
				"\n",
				array(
					sprintf( '-- DesignsLabz Relocate: original values changed by job %d, started %s UTC.', $job->id, gmdate( 'Y-m-d H:i:s' ) ),
					'-- Running this file puts every changed value back, overwriting any edits made to those values since.',
					'-- Statements from a batch that was rolled back restore values that never changed, so they are harmless.',
					'SET NAMES ' . ( $this->wpdb->charset ? $this->wpdb->charset : 'utf8mb4' ) . ';',
					'',
				)
			)
		);

		return $file;
	}

	/**
	 * @param list<array{key: array<string, string>, before: array<string, string>, after: array<string, string>, counts: array<string, int>}> $rows
	 * @throws RuntimeException When the statements cannot be written.
	 */
	public function append( string $file, TableLayout $layout, array $rows ): void {
		$sql = '';

		foreach ( $rows as $row ) {
			$sets  = array();
			$where = array();
			$args  = array( $layout->name );

			foreach ( $row['before'] as $column => $value ) {
				$sets[] = '%i = %s';
				array_push( $args, $column, $value );
			}

			foreach ( $row['key'] as $column => $value ) {
				$where[] = '%i = ' . ( $layout->key[ $column ] ? '%d' : '%s' );
				array_push( $args, $column, $value );
			}

			// prepare() swaps literal % signs for a placeholder token that query() would normally undo.
			$sql .= $this->wpdb->remove_placeholder_escape(
				$this->wpdb->prepare( 'UPDATE %i SET ' . implode( ', ', $sets ) . ' WHERE ' . implode( ' AND ', $where ) . ';', $args )
			) . "\n";
		}

		$this->write( $file, $sql );
	}

	/**
	 * Full path of an existing before-image file, or null.
	 */
	public function path( string $file ): ?string {
		if ( '' === $file ) {
			return null;
		}

		$path = $this->directory() . '/' . basename( $file );

		return is_file( $path ) ? $path : null;
	}

	/**
	 * Removes every before-image file and the folder. Used on uninstall.
	 */
	public function delete_all(): void {
		$directory = $this->directory();

		if ( ! is_dir( $directory ) ) {
			return;
		}

		$names = scandir( $directory );

		foreach ( false === $names ? array() : $names as $name ) {
			if ( is_file( $directory . '/' . $name ) ) {
				wp_delete_file( $directory . '/' . $name );
			}
		}

		rmdir( $directory );
	}

	/**
	 * Appends one gzip member per call. gzip readers treat concatenated members
	 * as one stream, and closing each time guarantees the data is written.
	 *
	 * @throws RuntimeException When the file cannot be written.
	 */
	private function write( string $file, string $contents ): void {
		$path   = $this->directory() . '/' . $file;
		$handle = gzopen( $path, 'ab' );

		if ( false === $handle ) {
			throw new RuntimeException( sprintf( 'Could not open %s for writing.', $path ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Escaped where it is displayed.
		}

		$written = gzwrite( $handle, $contents );

		if ( ! gzclose( $handle ) || strlen( $contents ) !== $written ) {
			throw new RuntimeException( sprintf( 'Could not write to %s. The disk may be full.', $path ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Escaped where it is displayed.
		}
	}

	private function protect( string $directory ): void {
		$files = array(
			'index.php' => "<?php\n// Silence is golden.\n",
			'.htaccess' => "Require all denied\n<IfModule !mod_authz_core.c>\n\tDeny from all\n</IfModule>\n",
		);

		foreach ( $files as $name => $contents ) {
			if ( ! file_exists( $directory . '/' . $name ) ) {
				file_put_contents( $directory . '/' . $name, $contents );
			}
		}
	}

	private function directory(): string {
		return wp_upload_dir( null, false )['basedir'] . '/' . self::DIRECTORY;
	}
}
