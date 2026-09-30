/**
 * Search & Replace tab: creates a job over REST, keeps calling its run
 * endpoint until it finishes, then renders the report.
 *
 * Everything from the server is inserted with textContent, never as HTML.
 */
( function () {
	'use strict';

	const form = document.getElementById( 'dlz-search-replace' );

	if ( ! form ) {
		return;
	}

	const { __, _n, sprintf } = wp.i18n;
	const apiFetch = wp.apiFetch;
	const speak = wp.a11y.speak;
	const API = '/dlz-relocate/v1/jobs';

	const submitButton = form.querySelector( '[type="submit"]' );
	const notices = document.getElementById( 'dlz-notices' );
	const progress = document.getElementById( 'dlz-progress' );
	const progressBar = document.getElementById( 'dlz-progress-bar' );
	const progressText = document.getElementById( 'dlz-progress-text' );
	const cancelButton = document.getElementById( 'dlz-cancel' );
	const results = document.getElementById( 'dlz-results' );
	const numbers = new Intl.NumberFormat( document.documentElement.lang || undefined );

	let cancelRequested = false;

	form.addEventListener( 'submit', async ( event ) => {
		event.preventDefault();

		const data = new FormData( form );
		const tables = data.getAll( 'tables[]' );

		clearNotices();

		if ( ! tables.length ) {
			showNotice( 'error', __( 'Select at least one table to search.', 'designslabz-relocate' ) );
			return;
		}

		results.hidden = true;
		results.replaceChildren();
		setBusy( true );

		let job;
		try {
			job = await apiFetch( {
				path: API,
				method: 'POST',
				data: {
					search: data.get( 'search' ),
					replace: data.get( 'replace' ),
					case_sensitive: ! data.has( 'case_insensitive' ),
					whole_words: data.has( 'whole_words' ),
					url_variants: data.has( 'url_variants' ),
					skip_guids: data.has( 'skip_guids' ),
					tables,
				},
			} );
		} catch ( error ) {
			setBusy( false );
			showNotice( 'error', errorMessage( error ) );
			return;
		}

		speak( __( 'Dry run started.', 'designslabz-relocate' ) );
		run( job );
	} );

	cancelButton.addEventListener( 'click', () => {
		cancelRequested = true;
		cancelButton.disabled = true;
		progressText.textContent = __( 'Cancelling after the current batch…', 'designslabz-relocate' );
	} );

	form.addEventListener( 'click', ( event ) => {
		const button = event.target.closest( '[data-dlz-select]' );

		if ( ! button ) {
			return;
		}

		const mode = button.dataset.dlzSelect;
		form.querySelectorAll( 'input[name="tables[]"]' ).forEach( ( input ) => {
			input.checked = 'all' === mode || ( 'core' === mode && 'core' === input.dataset.group );
		} );
	} );

	/**
	 * Drives the job to the end. On a failed request the job is still safe on
	 * the server, so the notice offers to carry on from where it stopped.
	 *
	 * @param {Object} job Job as returned by the REST API.
	 */
	async function run( job ) {
		cancelRequested = false;
		cancelButton.disabled = false;
		setBusy( true );

		try {
			while ( ! job.finished ) {
				updateProgress( job );

				job = await apiFetch( {
					path: `${ API }/${ job.id }/${ cancelRequested ? 'cancel' : 'run' }`,
					method: 'POST',
				} );
			}
		} catch ( error ) {
			setBusy( false );
			showNotice( 'error', errorMessage( error ), () => run( job ) );
			return;
		}

		setBusy( false );
		renderResults( job );
	}

	function updateProgress( job ) {
		progressBar.value = job.progress;

		if ( cancelRequested ) {
			return;
		}

		progressText.textContent = job.current_table
			? sprintf(
				/* translators: 1: table name, 2: tables done, 3: total tables, 4: rows scanned. */
				__( 'Searching %1$s (table %2$s of %3$s). %4$s rows scanned so far.', 'designslabz-relocate' ),
				job.current_table,
				numbers.format( job.tables_done + 1 ),
				numbers.format( job.tables_total ),
				numbers.format( job.totals.rows_scanned )
			)
			: __( 'Finishing…', 'designslabz-relocate' );
	}

	function renderResults( job ) {
		const totals = job.totals;
		const heading = el( 'h2', { tabIndex: -1 }, __( 'Dry run results', 'designslabz-relocate' ) );

		results.replaceChildren( heading );

		if ( 'completed' === job.status ) {
			results.append( status( 'yes-alt', __( 'Dry run complete. Nothing in the database was changed.', 'designslabz-relocate' ) ) );
		} else if ( 'cancelled' === job.status ) {
			results.append( status( 'dismiss', __( 'Dry run cancelled. The figures below cover only the part that was searched.', 'designslabz-relocate' ) ) );
		} else {
			results.append( status( 'warning', job.error || __( 'The dry run stopped because of an error.', 'designslabz-relocate' ) ) );
		}

		results.append(
			el(
				'dl',
				{ className: 'dlz-summary' },
				...summaryItem( __( 'Tables searched', 'designslabz-relocate' ), job.tables_done ),
				...summaryItem( __( 'Rows scanned', 'designslabz-relocate' ), totals.rows_scanned ),
				...summaryItem( __( 'Rows that would change', 'designslabz-relocate' ), totals.rows_changed ),
				...summaryItem( __( 'Replacements', 'designslabz-relocate' ), totals.replacements ),
				...summaryItem( __( 'Values that would be left unchanged', 'designslabz-relocate' ), totals.skipped )
			)
		);

		if ( totals.skipped ) {
			results.append(
				el(
					'p',
					{},
					__( 'Some matching values would be left unchanged because changing them could corrupt data. The reasons are listed per table below.', 'designslabz-relocate' )
				)
			);
		}

		if ( job.report ) {
			results.append( tablesReport( job.report.tables ) );

			if ( job.report.samples.length ) {
				results.append( samples( job.report.samples ) );
			}
		}

		results.hidden = false;
		heading.focus();

		speak(
			sprintf(
				/* translators: 1: number of replacements, 2: number of rows. */
				_n(
					'Dry run finished: %1$s replacement in %2$s rows.',
					'Dry run finished: %1$s replacements in %2$s rows.',
					totals.replacements,
					'designslabz-relocate'
				),
				numbers.format( totals.replacements ),
				numbers.format( totals.rows_changed )
			)
		);
	}

	function tablesReport( tables ) {
		const rows = tables.map( ( table ) => {
			const notes = [];

			if ( table.note ) {
				notes.push( table.note );
			}
			table.skipped.forEach( ( skipped ) => {
				notes.push( sprintf(
					/* translators: 1: number of values, 2: reason. */
					__( '%1$s left unchanged: %2$s', 'designslabz-relocate' ),
					numbers.format( skipped.count ),
					skipped.reason
				) );
			} );

			const columns = Object.entries( table.columns ).map( ( [ name, count ] ) => `${ name } (${ numbers.format( count ) })` );

			return el(
				'tr',
				{},
				el( 'th', { scope: 'row' }, el( 'code', {}, table.name ) ),
				el( 'td', { className: 'num' }, numbers.format( table.rows_scanned ) ),
				el( 'td', { className: 'num' }, numbers.format( table.rows_changed ) ),
				el( 'td', { className: 'num' }, numbers.format( table.replacements ) ),
				el( 'td', {}, columns.join( ', ' ) || '—' ),
				el( 'td', {}, notes.join( ' ' ) || '—' )
			);
		} );

		return el(
			'div',
			{ className: 'dlz-table-scroll' },
			el(
				'table',
				{ className: 'wp-list-table widefat striped dlz-tables' },
				el( 'caption', { className: 'screen-reader-text' }, __( 'Results per table', 'designslabz-relocate' ) ),
				el(
					'thead',
					{},
					el(
						'tr',
						{},
						el( 'th', { scope: 'col' }, __( 'Table', 'designslabz-relocate' ) ),
						el( 'th', { scope: 'col', className: 'num' }, __( 'Rows scanned', 'designslabz-relocate' ) ),
						el( 'th', { scope: 'col', className: 'num' }, __( 'Rows to change', 'designslabz-relocate' ) ),
						el( 'th', { scope: 'col', className: 'num' }, __( 'Replacements', 'designslabz-relocate' ) ),
						el( 'th', { scope: 'col' }, __( 'Columns', 'designslabz-relocate' ) ),
						el( 'th', { scope: 'col' }, __( 'Notes', 'designslabz-relocate' ) )
					)
				),
				el( 'tbody', {}, ...rows )
			)
		);
	}

	function samples( list ) {
		return el(
			'section',
			{ className: 'dlz-samples' },
			el( 'h3', {}, __( 'Examples', 'designslabz-relocate' ) ),
			el(
				'p',
				{ className: 'description' },
				sprintf(
					/* translators: %s: number of examples. */
					__( 'The first %s changes found, with a little surrounding text.', 'designslabz-relocate' ),
					numbers.format( list.length )
				)
			),
			el(
				'ol',
				{},
				...list.map( ( sample ) => el(
					'li',
					{},
					el( 'p', {}, el( 'code', {}, `${ sample.table }.${ sample.column }` ), ' ', el( 'span', { className: 'description' }, sample.key ) ),
					el(
						'dl',
						{ className: 'dlz-sample' },
						el( 'dt', {}, __( 'Before', 'designslabz-relocate' ) ),
						el( 'dd', {}, el( 'pre', {}, sample.before ) ),
						el( 'dt', {}, __( 'After', 'designslabz-relocate' ) ),
						el( 'dd', {}, el( 'pre', {}, sample.after ) )
					)
				) )
			)
		);
	}

	function summaryItem( label, value ) {
		return [ el( 'dt', {}, label ), el( 'dd', {}, numbers.format( value ) ) ];
	}

	function status( icon, text ) {
		return el(
			'p',
			{ className: 'dlz-status' },
			el( 'span', { className: `dashicons dashicons-${ icon }`, ariaHidden: 'true' } ),
			' ',
			text
		);
	}

	/**
	 * @param {string}    type    Notice type: error, warning, success or info.
	 * @param {string}    message Message text.
	 * @param {Function=} resume  When given, a button to carry on the job.
	 */
	function showNotice( type, message, resume ) {
		const notice = el( 'div', { className: `notice notice-${ type }` }, el( 'p', {}, message ) );

		if ( resume ) {
			const button = el( 'button', { type: 'button', className: 'button' }, __( 'Resume', 'designslabz-relocate' ) );
			button.addEventListener( 'click', () => {
				notice.remove();
				resume();
			} );
			notice.append( el( 'p', {}, button ) );
		}

		notices.replaceChildren( notice );
		speak( message, 'assertive' );
	}

	function clearNotices() {
		notices.replaceChildren();
	}

	function setBusy( busy ) {
		progress.hidden = ! busy;
		submitButton.disabled = busy;
		form.setAttribute( 'aria-busy', busy ? 'true' : 'false' );
	}

	function errorMessage( error ) {
		return ( error && error.message ) || __( 'The request failed. Check your connection and try again.', 'designslabz-relocate' );
	}

	/**
	 * Small element builder. Children are strings (added as text) or nodes.
	 *
	 * @param {string}             tag
	 * @param {Object}             props    Properties set directly on the element.
	 * @param {...(string|Node)}   children
	 * @return {HTMLElement} The element.
	 */
	function el( tag, props, ...children ) {
		const element = Object.assign( document.createElement( tag ), props );
		element.append( ...children.map( ( child ) => ( 'number' === typeof child ? String( child ) : child ) ) );
		return element;
	}
}() );
