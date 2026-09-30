/**
 * Search & Replace tab: creates a dry run over REST, keeps calling its run
 * endpoint until it finishes and renders the report. From a finished dry run
 * the same loop drives the live replacement.
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
	const progressHeading = document.getElementById( 'dlz-progress-heading' );
	const progressBar = document.getElementById( 'dlz-progress-bar' );
	const progressText = document.getElementById( 'dlz-progress-text' );
	const cancelButton = document.getElementById( 'dlz-cancel' );
	const results = document.getElementById( 'dlz-results' );
	const dialog = document.getElementById( 'dlz-confirm' );
	const confirmBackup = document.getElementById( 'dlz-confirm-backup' );
	const confirmBeforeImage = document.getElementById( 'dlz-confirm-before-image' );
	const confirmSubmit = document.getElementById( 'dlz-confirm-submit' );
	const numbers = new Intl.NumberFormat( document.documentElement.lang || undefined );

	let cancelRequested = false;
	let dryRun = null;
	let dialogOpener = null;

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
		progressText.textContent = __( 'Stopping after the current batch…', 'designslabz-relocate' );
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

	confirmBackup.addEventListener( 'change', () => {
		confirmSubmit.disabled = ! confirmBackup.checked;
	} );

	dialog.addEventListener( 'close', () => {
		if ( dialogOpener ) {
			dialogOpener.focus();
		}

		if ( 'confirm' === dialog.returnValue && confirmBackup.checked ) {
			execute( dryRun, confirmBeforeImage.checked );
		}
	} );

	/**
	 * Drives a job to the end. On a failed request the job is still safe on the
	 * server, so the notice offers to carry on from where it stopped.
	 *
	 * @param {Object} job Job as returned by the REST API.
	 */
	async function run( job ) {
		cancelRequested = false;
		cancelButton.disabled = false;
		cancelButton.textContent = job.dry_run ? __( 'Cancel', 'designslabz-relocate' ) : __( 'Stop after this batch', 'designslabz-relocate' );
		progressHeading.textContent = job.dry_run
			? __( 'Dry run in progress', 'designslabz-relocate' )
			: __( 'Replacing in the database', 'designslabz-relocate' );
		setBusy( true, ! job.dry_run );

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

		if ( job.dry_run ) {
			dryRun = job;
			renderDryRun( job );
		} else {
			renderLiveRun( job );
		}
	}

	async function execute( job, beforeImage ) {
		clearNotices();

		let live;
		try {
			live = await apiFetch( {
				path: `${ API }/${ job.id }/execute`,
				method: 'POST',
				data: { confirmed: true, before_image: beforeImage },
			} );
		} catch ( error ) {
			showNotice( 'error', errorMessage( error ) );
			return;
		}

		results.hidden = true;
		speak( __( 'Replacement started.', 'designslabz-relocate' ) );
		run( live );
	}

	async function resume( job ) {
		clearNotices();

		try {
			job = await apiFetch( { path: `${ API }/${ job.id }/resume`, method: 'POST' } );
		} catch ( error ) {
			showNotice( 'error', errorMessage( error ) );
			return;
		}

		results.hidden = true;
		run( job );
	}

	function updateProgress( job ) {
		progressBar.value = job.progress;

		if ( cancelRequested ) {
			return;
		}

		progressText.textContent = job.current_table
			? sprintf(
				/* translators: 1: table name, 2: tables done, 3: total tables, 4: rows scanned. */
				__( 'Working on %1$s (table %2$s of %3$s). %4$s rows scanned so far.', 'designslabz-relocate' ),
				job.current_table,
				numbers.format( job.tables_done + 1 ),
				numbers.format( job.tables_total ),
				numbers.format( job.totals.rows_scanned )
			)
			: __( 'Finishing…', 'designslabz-relocate' );
	}

	function renderDryRun( job ) {
		const heading = startResults( __( 'Dry run results', 'designslabz-relocate' ) );

		if ( 'completed' === job.status ) {
			results.append( status( 'yes-alt', __( 'Dry run complete. Nothing in the database was changed.', 'designslabz-relocate' ) ) );
		} else if ( 'cancelled' === job.status ) {
			results.append( status( 'dismiss', __( 'Dry run cancelled. The figures below cover only the part that was searched.', 'designslabz-relocate' ) ) );
		} else {
			results.append( status( 'warning', job.error || __( 'The dry run stopped because of an error.', 'designslabz-relocate' ) ) );
			results.append( resumeButton( job ) );
		}

		results.append(
			summary( [
				[ __( 'Tables searched', 'designslabz-relocate' ), job.tables_done ],
				[ __( 'Rows scanned', 'designslabz-relocate' ), job.totals.rows_scanned ],
				[ __( 'Rows that would change', 'designslabz-relocate' ), job.totals.rows_changed ],
				[ __( 'Replacements', 'designslabz-relocate' ), job.totals.replacements ],
				[ __( 'Values that would be left unchanged', 'designslabz-relocate' ), job.totals.skipped ],
			] )
		);

		appendReport( job, __( 'Rows to change', 'designslabz-relocate' ) );

		if ( job.executable ) {
			results.append( applySection( job ) );
		} else if ( 'completed' === job.status && ! job.totals.rows_changed ) {
			results.append( el( 'p', {}, __( 'No matches were found, so there is nothing to replace.', 'designslabz-relocate' ) ) );
		}

		finishResults( heading, sprintf(
			/* translators: 1: number of replacements, 2: number of rows. */
			_n(
				'Dry run finished: %1$s replacement in %2$s rows.',
				'Dry run finished: %1$s replacements in %2$s rows.',
				job.totals.replacements,
				'designslabz-relocate'
			),
			numbers.format( job.totals.replacements ),
			numbers.format( job.totals.rows_changed )
		) );
	}

	function renderLiveRun( job ) {
		const heading = startResults( __( 'Replacement results', 'designslabz-relocate' ) );
		let message;

		if ( 'completed' === job.status ) {
			message = __( 'Replacement complete. The changes below have been written to the database.', 'designslabz-relocate' );
			results.append( status( 'yes-alt', message ) );
		} else if ( 'cancelled' === job.status ) {
			message = __( 'Replacement stopped. Batches finished before stopping were written to the database; the rest was not touched.', 'designslabz-relocate' );
			results.append( status( 'dismiss', message ) );
		} else {
			message = job.error || __( 'The replacement stopped because of an error.', 'designslabz-relocate' );
			results.append( status( 'warning', message ) );
			results.append(
				el( 'p', {}, __( 'Batches finished before the error were written to the database. The batch that failed was rolled back completely. Resuming carries on from there.', 'designslabz-relocate' ) ),
				resumeButton( job )
			);
		}

		if ( job.site_address_changed ) {
			results.append(
				el(
					'div',
					{ className: 'notice notice-warning inline' },
					el( 'p', {}, __( 'The site address (siteurl and home) was changed, so you will need to log in again at the new address.', 'designslabz-relocate' ) ),
					el( 'p', {}, el( 'a', { href: job.login_url }, __( 'Log in at the new address', 'designslabz-relocate' ) ) )
				)
			);
		}

		results.append(
			summary( [
				[ __( 'Tables processed', 'designslabz-relocate' ), job.tables_done ],
				[ __( 'Rows scanned', 'designslabz-relocate' ), job.totals.rows_scanned ],
				[ __( 'Rows changed', 'designslabz-relocate' ), job.totals.rows_changed ],
				[ __( 'Replacements', 'designslabz-relocate' ), job.totals.replacements ],
				[ __( 'Values left unchanged', 'designslabz-relocate' ), job.totals.skipped ],
			] )
		);

		if ( job.before_image_url ) {
			results.append(
				el(
					'p',
					{},
					el( 'a', { className: 'button', href: job.before_image_url }, __( 'Download original values (.sql.gz)', 'designslabz-relocate' ) ),
					' ',
					el( 'span', { className: 'description' }, __( 'Importing this file into the database puts every changed value back as it was, overwriting any edits made to those values since.', 'designslabz-relocate' ) )
				)
			);
		}

		appendReport( job, __( 'Rows changed', 'designslabz-relocate' ) );
		finishResults( heading, message );
	}

	function applySection( job ) {
		const button = el( 'button', { type: 'button', className: 'button button-primary' }, __( 'Replace in database…', 'designslabz-relocate' ) );

		button.addEventListener( 'click', () => openDialog( job, button ) );

		return el(
			'section',
			{ className: 'dlz-apply' },
			el( 'h3', {}, __( 'Apply these changes', 'designslabz-relocate' ) ),
			el(
				'p',
				{},
				__( 'This writes the replacements shown above to the database, using exactly the search, replacement and tables of this dry run. Rows are processed in batches; each batch is saved completely or not at all.', 'designslabz-relocate' )
			),
			el( 'p', {}, button )
		);
	}

	function openDialog( job, opener ) {
		const summaryText = sprintf(
			/* translators: 1: number of replacements, 2: number of rows, 3: number of tables. */
			__( '%1$s replacements in %2$s rows across %3$s tables will be written to the database.', 'designslabz-relocate' ),
			numbers.format( job.totals.replacements ),
			numbers.format( job.totals.rows_changed ),
			numbers.format( job.tables_total )
		);

		document.getElementById( 'dlz-confirm-summary' ).replaceChildren(
			el( 'p', {}, summaryText ),
			el(
				'dl',
				{ className: 'dlz-summary' },
				el( 'dt', {}, __( 'Search for', 'designslabz-relocate' ) ),
				el( 'dd', {}, el( 'code', {}, job.search ) ),
				el( 'dt', {}, __( 'Replace with', 'designslabz-relocate' ) ),
				el( 'dd', {}, job.replace ? el( 'code', {}, job.replace ) : el( 'em', {}, __( '(nothing: matches are removed)', 'designslabz-relocate' ) ) )
			)
		);

		document.getElementById( 'dlz-confirm-warnings' ).replaceChildren( ...warnings( job ).map( ( text ) => el( 'li', {}, text ) ) );

		confirmBackup.checked = false;
		confirmSubmit.disabled = true;
		dialogOpener = opener;
		dialog.returnValue = '';
		dialog.showModal();
		confirmBackup.focus();
	}

	function warnings( job ) {
		const list = [];
		const engines = {};

		form.querySelectorAll( 'input[name="tables[]"]' ).forEach( ( input ) => {
			engines[ input.value ] = input.dataset.engine;
		} );

		const untransactional = job.tables.filter( ( table ) => engines[ table ] && 'innodb' !== engines[ table ].toLowerCase() );

		if ( untransactional.length ) {
			list.push( sprintf(
				/* translators: %s: comma-separated table names. */
				__( 'These tables do not support transactions, so if the replacement is interrupted a batch in them could be left partly written: %s', 'designslabz-relocate' ),
				untransactional.join( ', ' )
			) );
		}

		if ( job.replace.includes( job.search ) ) {
			list.push( __( 'The replacement contains the search text. Running this same replacement a second time would apply it again.', 'designslabz-relocate' ) );
		}

		if ( job.tables.some( ( table ) => /options$/.test( table ) ) ) {
			list.push( __( 'If this changes the site address (siteurl or home), it is changed last and you will need to log in again at the new address.', 'designslabz-relocate' ) );
		}

		return list;
	}

	function appendReport( job, changedLabel ) {
		if ( job.totals.skipped ) {
			results.append(
				el(
					'p',
					{},
					__( 'Some matching values are left unchanged because changing them could corrupt data. The reasons are listed per table below.', 'designslabz-relocate' )
				)
			);
		}

		if ( job.report ) {
			results.append( tablesReport( job.report.tables, changedLabel ) );

			if ( job.report.samples.length ) {
				results.append( samples( job.report.samples ) );
			}
		}
	}

	function tablesReport( tables, changedLabel ) {
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
						el( 'th', { scope: 'col', className: 'num' }, changedLabel ),
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

	function startResults( title ) {
		const heading = el( 'h2', { tabIndex: -1 }, title );
		results.replaceChildren( heading );
		return heading;
	}

	function finishResults( heading, announcement ) {
		results.hidden = false;
		heading.focus();
		speak( announcement );
	}

	function summary( items ) {
		return el(
			'dl',
			{ className: 'dlz-summary' },
			...items.flatMap( ( [ label, value ] ) => [ el( 'dt', {}, label ), el( 'dd', {}, numbers.format( value ) ) ] )
		);
	}

	function resumeButton( job ) {
		const button = el( 'button', { type: 'button', className: 'button' }, __( 'Resume', 'designslabz-relocate' ) );
		button.addEventListener( 'click', () => resume( job ) );
		return el( 'p', {}, button );
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
	 * @param {Function=} retry   When given, a button to carry on the job.
	 */
	function showNotice( type, message, retry ) {
		const notice = el( 'div', { className: `notice notice-${ type }` }, el( 'p', {}, message ) );

		if ( retry ) {
			const button = el( 'button', { type: 'button', className: 'button' }, __( 'Resume', 'designslabz-relocate' ) );
			button.addEventListener( 'click', () => {
				notice.remove();
				retry();
			} );
			notice.append( el( 'p', {}, button ) );
		}

		notices.replaceChildren( notice );
		speak( message, 'assertive' );
	}

	function clearNotices() {
		notices.replaceChildren();
	}

	function warnBeforeLeaving( event ) {
		event.preventDefault();
		event.returnValue = '';
	}

	/**
	 * @param {boolean} busy
	 * @param {boolean} live Whether a live replacement is running, which makes leaving the page worth a warning.
	 */
	function setBusy( busy, live = false ) {
		progress.hidden = ! busy;
		submitButton.disabled = busy;
		form.setAttribute( 'aria-busy', busy ? 'true' : 'false' );

		if ( busy && live ) {
			window.addEventListener( 'beforeunload', warnBeforeLeaving );
		} else {
			window.removeEventListener( 'beforeunload', warnBeforeLeaving );
		}
	}

	function errorMessage( error ) {
		return ( error && error.message ) || __( 'The request failed. Check your connection and try again.', 'designslabz-relocate' );
	}

	/**
	 * Small element builder. Children are strings (added as text) or nodes.
	 *
	 * @param {string}           tag
	 * @param {Object}           props    Properties set directly on the element.
	 * @param {...(string|Node)} children
	 * @return {HTMLElement} The element.
	 */
	function el( tag, props, ...children ) {
		const element = Object.assign( document.createElement( tag ), props );
		element.append( ...children.map( ( child ) => ( 'number' === typeof child ? String( child ) : child ) ) );
		return element;
	}
}() );
