/**
 * History screen: asks before deleting jobs, one at a time or in bulk.
 */
( function () {
	'use strict';

	const { __, sprintf } = wp.i18n;

	document.addEventListener( 'click', ( event ) => {
		const link = event.target.closest( '.dlz-delete-job' );

		if ( ! link ) {
			return;
		}

		const message = sprintf(
			/* translators: %s: job title, e.g. Dry run #12. */
			__( 'Delete %s? Its results, log and file of original values are removed permanently. The database changes it made stay as they are.', 'dl-relocate-db' ),
			link.dataset.job
		);

		// eslint-disable-next-line no-alert -- The same confirmation WordPress core uses for permanent deletes.
		if ( ! window.confirm( message ) ) {
			event.preventDefault();
		}
	} );

	document.addEventListener( 'submit', ( event ) => {
		const form = event.target;
		const action = form.querySelector( '#bulk-action-selector-top' );
		const action2 = form.querySelector( '#bulk-action-selector-bottom' );
		const deleting = ( action && 'delete' === action.value ) || ( action2 && 'delete' === action2.value );
		const count = form.querySelectorAll( 'input[name="job_ids[]"]:checked' ).length;

		if ( ! deleting || ! count ) {
			return;
		}

		const message = sprintf(
			/* translators: %d: number of jobs. */
			__( 'Delete %d selected jobs? Their results, logs and files of original values are removed permanently. The database changes they made stay as they are.', 'dl-relocate-db' ),
			count
		);

		// eslint-disable-next-line no-alert -- The same confirmation WordPress core uses for permanent deletes.
		if ( ! window.confirm( message ) ) {
			event.preventDefault();
		}
	} );
}() );
