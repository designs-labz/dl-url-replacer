/**
 * Search & Replace screen and job page.
 *
 * Creates a dry run over REST, keeps calling its run endpoint until it
 * finishes, and renders the report. From a finished dry run the same loop
 * drives the live replacement. The job page embeds a job in
 * #dlz-runner[data-job], which is rendered on load.
 *
 * Everything from the server is inserted with textContent, never as HTML.
 */
( function () {
	'use strict';

	const runner = document.getElementById( 'dlz-runner' );

	if ( ! runner ) {
		return;
	}

	const { __, _n, sprintf } = wp.i18n;
	const apiFetch = wp.apiFetch;
	const speak = wp.a11y.speak;
	const API = '/dlz-relocate/v1/jobs';
	const PAGE_SIZE = 25;

	const form = document.getElementById( 'dlz-search-replace' );
	const submitButton = form ? form.querySelector( '[type="submit"]' ) : null;
	const notices = document.getElementById( 'dlz-notices' );
	const results = document.getElementById( 'dlz-results' );
	const dialog = document.getElementById( 'dlz-confirm' );
	const confirmBackup = document.getElementById( 'dlz-confirm-backup' );
	const confirmBeforeImage = document.getElementById( 'dlz-confirm-before-image' );
	const confirmSubmit = document.getElementById( 'dlz-confirm-submit' );
	const cancelButton = document.getElementById( 'dlz-cancel' );
	const numbers = new Intl.NumberFormat( document.documentElement.lang || undefined );

	const progress = {
		panel: document.getElementById( 'dlz-progress' ),
		heading: document.getElementById( 'dlz-progress-heading' ),
		text: document.getElementById( 'dlz-progress-text' ),
		percent: document.getElementById( 'dlz-progress-percent' ),
		bar: document.getElementById( 'dlz-progress-bar' ),
		fill: document.querySelector( '#dlz-progress-bar .dlz-bar-fill' ),
		scanned: document.getElementById( 'dlz-stat-scanned' ),
		changed: document.getElementById( 'dlz-stat-changed' ),
		changedLabel: document.getElementById( 'dlz-stat-changed-label' ),
		replacements: document.getElementById( 'dlz-stat-replacements' ),
		elapsed: document.getElementById( 'dlz-stat-elapsed' ),
		remaining: document.getElementById( 'dlz-stat-remaining' ),
		tables: document.getElementById( 'dlz-progress-tables' ),
		tableCount: document.getElementById( 'dlz-progress-table-count' ),
		startedAt: 0,
		startPercent: 0,
		timer: null,
		spoken: 0,
	};

	let cancelRequested = false;
	let dryRun = null;
	let dialogOpener = null;

	if ( form ) {
		initForm();
	}

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

	cancelButton.addEventListener( 'click', () => {
		cancelRequested = true;
		cancelButton.disabled = true;
		progress.text.textContent = __( 'Stopping after the current batch…', 'dl-relocate-db' );
	} );

	if ( runner.dataset.job ) {
		showJob( JSON.parse( runner.dataset.job ) );
	}

	/* Form ---------------------------------------------------------------- */

	function initForm() {
		initPairs();

		const filter = document.getElementById( 'dlz-table-filter' );
		const count = document.getElementById( 'dlz-table-count' );
		const none = document.getElementById( 'dlz-table-none' );
		const rows = [ ...form.querySelectorAll( '.dlz-picker-row' ) ];
		const tableBoxes = () => [ ...form.querySelectorAll( 'input[name="tables[]"]' ) ];

		const updateCount = () => {
			const boxes = tableBoxes();
			count.textContent = sprintf(
				/* translators: 1: selected tables, 2: all tables. */
				__( '%1$s of %2$s tables selected', 'dl-relocate-db' ),
				numbers.format( boxes.filter( ( box ) => box.checked ).length ),
				numbers.format( boxes.length )
			);
		};

		filter.addEventListener( 'input', () => {
			const term = filter.value.trim().toLowerCase();
			let visible = 0;

			rows.forEach( ( row ) => {
				const match = ! term || row.dataset.table.toLowerCase().includes( term );
				row.hidden = ! match;
				visible += match ? 1 : 0;
			} );

			// Open collapsed groups so matches are not hidden inside them.
			if ( term ) {
				form.querySelectorAll( '.dlz-picker-group' ).forEach( ( group ) => {
					group.open = true;
				} );
			}

			none.hidden = visible > 0;
		} );

		form.addEventListener( 'click', ( event ) => {
			const button = event.target.closest( '[data-dlz-select]' );

			if ( ! button ) {
				return;
			}

			const mode = button.dataset.dlzSelect;

			// Only the tables the filter shows, so "select all" after filtering does what it says.
			tableBoxes()
				.filter( ( box ) => ! box.closest( '.dlz-picker-row' ).hidden )
				.forEach( ( box ) => {
					box.checked = 'all' === mode || ( 'core' === mode && 'core' === box.dataset.group );
				} );

			updateCount();
		} );

		form.addEventListener( 'change', ( event ) => {
			if ( 'tables[]' === event.target.name ) {
				updateCount();
			}

			const columns = event.target.closest( '.dlz-columns' );
			if ( columns ) {
				columns.querySelector( '.dlz-columns-selected' ).textContent = numbers.format(
					columns.querySelectorAll( 'input:checked' ).length
				);
			}
		} );

		form.addEventListener( 'submit', onSubmit );
		updateCount();
	}

	/**
	 * The "Add another" button and the rows it creates. The limit comes from the
	 * server (data-max), which enforces it again when the job is created.
	 */
	function initPairs() {
		const list = document.getElementById( 'dlz-pairs' );
		const template = document.getElementById( 'dlz-pair-template' );
		const add = document.getElementById( 'dlz-add-pair' );
		const count = document.getElementById( 'dlz-pairs-count' );
		const max = Number( list.dataset.max ) || 1;

		const renumber = () => {
			const rows = [ ...list.children ];

			rows.forEach( ( row, index ) => {
				const number = index + 1;
				const search = row.querySelector( 'input[name="search[]"]' );
				const replace = row.querySelector( 'input[name="replace[]"]' );

				search.id = `dlz-search-${ number }`;
				replace.id = `dlz-replace-${ number }`;

				if ( index > 0 ) {
					const [ searchLabel, replaceLabel ] = row.querySelectorAll( 'label' );
					searchLabel.htmlFor = search.id;
					replaceLabel.htmlFor = replace.id;
					/* translators: %d: pair number. */
					searchLabel.textContent = sprintf( __( 'Search for, pair %d', 'dl-relocate-db' ), number );
					/* translators: %d: pair number. */
					replaceLabel.textContent = sprintf( __( 'Replace with, pair %d', 'dl-relocate-db' ), number );
					/* translators: %d: pair number. */
					row.querySelector( '.dlz-pair-remove .screen-reader-text' ).textContent = sprintf( __( 'Remove pair %d', 'dl-relocate-db' ), number );
				}
			} );

			add.disabled = rows.length >= max;
			count.textContent = sprintf(
				/* translators: 1: pairs in use, 2: maximum pairs. */
				__( '%1$s of %2$s', 'dl-relocate-db' ),
				numbers.format( rows.length ),
				numbers.format( max )
			);
		};

		add.addEventListener( 'click', () => {
			if ( list.children.length >= max ) {
				return;
			}

			list.append( template.content.cloneNode( true ) );
			renumber();
			list.lastElementChild.querySelector( 'input' ).focus();
		} );

		list.addEventListener( 'click', ( event ) => {
			const remove = event.target.closest( '.dlz-pair-remove' );

			if ( ! remove ) {
				return;
			}

			const row = remove.closest( '.dlz-pair' );
			const previous = row.previousElementSibling;

			row.remove();
			renumber();
			( previous ? previous.querySelector( 'input' ) : add ).focus();
			speak( __( 'Pair removed.', 'dl-relocate-db' ) );
		} );

		renumber();
	}

	async function onSubmit( event ) {
		event.preventDefault();

		const data = new FormData( form );
		const tables = data.getAll( 'tables[]' );

		clearNotices();

		if ( ! tables.length ) {
			showNotice( 'error', __( 'Select at least one table to search.', 'dl-relocate-db' ) );
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
					pairs: pairsFrom( data ),
					case_sensitive: ! data.has( 'case_insensitive' ),
					whole_words: data.has( 'whole_words' ),
					url_variants: data.has( 'url_variants' ),
					skip_guids: data.has( 'skip_guids' ),
					tables,
					exclude_columns: excludedColumns( tables ),
				},
			} );
		} catch ( error ) {
			setBusy( false );
			showNotice( 'error', errorMessage( error ) );
			return;
		}

		speak( __( 'Dry run started.', 'dl-relocate-db' ) );
		run( job );
	}

	/**
	 * @param {FormData} data
	 * @return {Object[]} Search and replacement pairs, skipping added rows left completely empty.
	 */
	function pairsFrom( data ) {
		const replaces = data.getAll( 'replace[]' );

		return data.getAll( 'search[]' )
			.map( ( search, index ) => ( { search, replace: replaces[ index ] || '' } ) )
			.filter( ( pair, index ) => 0 === index || pair.search || pair.replace );
	}

	/**
	 * Unticked columns of the selected tables.
	 *
	 * @param {string[]} tables Selected tables.
	 * @return {Object<string, string[]>} Table => columns to leave out.
	 */
	function excludedColumns( tables ) {
		const excluded = {};

		tables.forEach( ( table ) => {
			const row = form.querySelector( `.dlz-picker-row[data-table="${ CSS.escape( table ) }"]` );
			const off = row ? [ ...row.querySelectorAll( '.dlz-columns input:not(:checked)' ) ].map( ( box ) => box.value ) : [];

			if ( off.length ) {
				excluded[ table ] = off;
			}
		} );

		return excluded;
	}

	/* Running a job ------------------------------------------------------- */

	/**
	 * A job embedded in the page: its results if it has finished, otherwise an
	 * offer to carry on from where it stopped.
	 *
	 * @param {Object} job Job as returned by the REST API.
	 */
	function showJob( job ) {
		if ( job.finished ) {
			if ( job.dry_run ) {
				dryRun = job;
				renderDryRun( job, false );
			} else {
				renderLiveRun( job, false );
			}
			return;
		}

		const message = job.interrupted
			? __( 'This job stopped before it finished, probably because the page running it was closed. Continuing carries on from the last completed batch.', 'dl-relocate-db' )
			: __( 'This job is still running, possibly in another tab. If that tab was closed, continue it here.', 'dl-relocate-db' );

		showNotice( 'warning', message, () => run( job ), __( 'Continue', 'dl-relocate-db' ), false );
	}

	/**
	 * Drives a job to the end. On a failed request the job is still safe on the
	 * server, so the notice offers to carry on from where it stopped.
	 *
	 * @param {Object} job Job as returned by the REST API.
	 */
	async function run( job ) {
		cancelRequested = false;
		startProgress( job );
		setBusy( true, ! job.dry_run );

		try {
			while ( ! job.finished ) {
				job = await apiFetch( {
					path: `${ API }/${ job.id }/${ cancelRequested ? 'cancel' : 'run' }`,
					method: 'POST',
				} );
				updateProgress( job );
			}
		} catch ( error ) {
			stopProgress( 'stopped' );
			setBusy( false );
			showNotice( 'error', errorMessage( error ), () => run( job ) );
			return;
		}

		stopProgress( 'completed' === job.status ? 'done' : 'stopped' );
		setBusy( false );

		// Let the bar reach its final state before the results take over.
		await pause( 500 );
		progress.panel.hidden = true;

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
		speak( __( 'Replacement started.', 'dl-relocate-db' ) );
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

	/* Progress ------------------------------------------------------------ */

	function startProgress( job ) {
		progress.panel.hidden = false;
		progress.heading.textContent = job.dry_run
			? __( 'Dry run in progress', 'dl-relocate-db' )
			: __( 'Replacing in the database', 'dl-relocate-db' );
		progress.changedLabel.textContent = job.dry_run
			? __( 'Rows to change', 'dl-relocate-db' )
			: __( 'Rows changed', 'dl-relocate-db' );
		progress.text.textContent = job.dry_run
			? __( 'Starting…', 'dl-relocate-db' )
			: __( 'Starting… Keep this page open until the replacement finishes.', 'dl-relocate-db' );

		cancelButton.disabled = false;
		cancelButton.textContent = job.dry_run ? __( 'Cancel', 'dl-relocate-db' ) : __( 'Stop after this batch', 'dl-relocate-db' );

		progress.bar.classList.remove( 'is-done', 'is-stopped' );
		progress.bar.classList.add( 'is-indeterminate' );
		progress.bar.removeAttribute( 'aria-valuetext' );
		progress.fill.style.width = '';
		progress.percent.textContent = `${ job.progress }%`;
		progress.remaining.textContent = '—';
		progress.startedAt = Date.now();
		progress.startPercent = job.progress;
		progress.spoken = 0;

		progress.tables.replaceChildren(
			...job.tables.map( ( table ) => el( 'li', {}, el( 'span', { className: 'dashicons dashicons-marker', ariaHidden: 'true' } ), table ) )
		);

		clearInterval( progress.timer );
		progress.timer = setInterval( tick, 1000 );
		tick();
		updateStats( job );
		updateTables( job );
		progress.panel.scrollIntoView( { behavior: prefersReducedMotion() ? 'auto' : 'smooth', block: 'start' } );
	}

	function updateProgress( job ) {
		const percent = job.finished && 'completed' === job.status ? 100 : job.progress;

		progress.bar.classList.remove( 'is-indeterminate' );
		progress.fill.style.width = `${ percent }%`;
		progress.bar.setAttribute( 'aria-valuenow', String( percent ) );
		progress.percent.textContent = `${ percent }%`;

		if ( ! cancelRequested && ! job.finished ) {
			progress.text.textContent = job.current_table
				? sprintf(
					/* translators: 1: table name, 2: table number, 3: number of tables. */
					__( 'Working on %1$s (table %2$s of %3$s)', 'dl-relocate-db' ),
					job.current_table,
					numbers.format( job.tables_done + 1 ),
					numbers.format( job.tables_total )
				)
				: __( 'Finishing…', 'dl-relocate-db' );
		}

		progress.bar.setAttribute( 'aria-valuetext', `${ percent }% – ${ progress.text.textContent }` );

		updateStats( job );
		updateTables( job );
		updateRemaining( percent );

		// Screen readers hear every quarter, not every batch.
		const quarter = Math.floor( percent / 25 ) * 25;
		if ( quarter > progress.spoken && percent < 100 ) {
			progress.spoken = quarter;
			/* translators: %s: percentage. */
			speak( sprintf( __( '%s percent done.', 'dl-relocate-db' ), quarter ) );
		}
	}

	function stopProgress( state ) {
		clearInterval( progress.timer );
		progress.bar.classList.remove( 'is-indeterminate' );
		progress.bar.classList.add( 'done' === state ? 'is-done' : 'is-stopped' );
		progress.remaining.textContent = '—';

		if ( 'done' === state ) {
			progress.fill.style.width = '100%';
			progress.percent.textContent = '100%';
			progress.text.textContent = __( 'Done.', 'dl-relocate-db' );
		}
	}

	function updateStats( job ) {
		progress.scanned.textContent = numbers.format( job.totals.rows_scanned );
		progress.changed.textContent = numbers.format( job.totals.rows_changed );
		progress.replacements.textContent = numbers.format( job.totals.replacements );
	}

	function updateTables( job ) {
		const items = [ ...progress.tables.children ];

		items.forEach( ( item, index ) => {
			const state = index < job.tables_done ? 'done' : ( index === job.tables_done && ! job.finished ? 'current' : 'pending' );
			const icon = item.querySelector( '.dashicons' );

			item.className = `is-${ state }`;
			icon.className = `dashicons dashicons-${ { done: 'yes-alt', current: 'update', pending: 'marker' }[ state ] }`;
		} );

		progress.tableCount.textContent = `(${ numbers.format( Math.min( job.tables_done, job.tables_total ) ) } / ${ numbers.format( job.tables_total ) })`;

		const current = progress.tables.querySelector( '.is-current' );
		if ( current && progress.tables.closest( 'details' ).open ) {
			current.scrollIntoView( { block: 'nearest' } );
		}
	}

	function updateRemaining( percent ) {
		const elapsed = ( Date.now() - progress.startedAt ) / 1000;
		const done = percent - progress.startPercent;

		// Estimates are wild at the very start, so wait for a little data first.
		if ( done < 3 || elapsed < 5 || percent >= 100 ) {
			progress.remaining.textContent = percent >= 100 ? '0:00' : '—';
			return;
		}

		progress.remaining.textContent = sprintf(
			/* translators: %s: time, e.g. 2:15. */
			__( 'about %s', 'dl-relocate-db' ),
			clock( ( elapsed / done ) * ( 100 - percent ) )
		);
	}

	function tick() {
		progress.elapsed.textContent = clock( ( Date.now() - progress.startedAt ) / 1000 );
	}

	/* Results ------------------------------------------------------------- */

	function renderDryRun( job, announce = true ) {
		const heading = startResults( __( 'Dry run results', 'dl-relocate-db' ) );

		if ( 'completed' === job.status ) {
			results.append( status( 'success', 'yes-alt', __( 'Dry run complete. Nothing in the database was changed.', 'dl-relocate-db' ) ) );
		} else if ( 'cancelled' === job.status ) {
			results.append( status( 'warning', 'dismiss', __( 'Dry run cancelled. The figures below cover only the part that was searched.', 'dl-relocate-db' ) ) );
		} else {
			results.append( status( 'error', 'warning', job.error || __( 'The dry run stopped because of an error.', 'dl-relocate-db' ) ) );
			results.append( resumeButton( job ) );
		}

		results.append(
			tiles( [
				[ 'database', __( 'Tables searched', 'dl-relocate-db' ), job.tables_done ],
				[ 'editor-table', __( 'Rows scanned', 'dl-relocate-db' ), job.totals.rows_scanned ],
				[ 'edit', __( 'Rows that would change', 'dl-relocate-db' ), job.totals.rows_changed ],
				[ 'update', __( 'Replacements', 'dl-relocate-db' ), job.totals.replacements ],
				[ 'shield', __( 'Left unchanged', 'dl-relocate-db' ), job.totals.skipped ],
			] )
		);

		if ( job.executable ) {
			results.append( applySection( job ) );
		} else if ( 'completed' === job.status && ! job.totals.rows_changed ) {
			results.append( el( 'p', { className: 'dlz-empty' }, __( 'No matches were found, so there is nothing to replace.', 'dl-relocate-db' ) ) );
		}

		appendReport( job, __( 'Rows to change', 'dl-relocate-db' ) );

		finishResults( heading, announce, sprintf(
			/* translators: 1: number of replacements, 2: number of rows. */
			_n(
				'Dry run finished: %1$s replacement in %2$s rows.',
				'Dry run finished: %1$s replacements in %2$s rows.',
				job.totals.replacements,
				'dl-relocate-db'
			),
			numbers.format( job.totals.replacements ),
			numbers.format( job.totals.rows_changed )
		) );
	}

	function renderLiveRun( job, announce = true ) {
		const heading = startResults( __( 'Replacement results', 'dl-relocate-db' ) );
		let message;

		if ( 'completed' === job.status ) {
			message = __( 'Replacement complete. The changes below have been written to the database.', 'dl-relocate-db' );
			results.append( status( 'success', 'yes-alt', message ) );
		} else if ( 'cancelled' === job.status ) {
			message = __( 'Replacement stopped. Batches finished before stopping were written to the database; the rest was not touched.', 'dl-relocate-db' );
			results.append( status( 'warning', 'dismiss', message ) );
		} else {
			message = job.error || __( 'The replacement stopped because of an error.', 'dl-relocate-db' );
			results.append( status( 'error', 'warning', message ) );
			results.append(
				el( 'p', {}, __( 'Batches finished before the error were written to the database. The batch that failed was rolled back completely. Resuming carries on from there.', 'dl-relocate-db' ) ),
				resumeButton( job )
			);
		}

		if ( job.site_address_changed ) {
			results.append(
				el(
					'div',
					{ className: 'notice notice-warning inline' },
					el( 'p', {}, __( 'The site address (siteurl and home) was changed, so you will need to log in again at the new address.', 'dl-relocate-db' ) ),
					el( 'p', {}, el( 'a', { href: job.login_url }, __( 'Log in at the new address', 'dl-relocate-db' ) ) )
				)
			);
		}

		results.append(
			tiles( [
				[ 'database', __( 'Tables processed', 'dl-relocate-db' ), job.tables_done ],
				[ 'editor-table', __( 'Rows scanned', 'dl-relocate-db' ), job.totals.rows_scanned ],
				[ 'edit', __( 'Rows changed', 'dl-relocate-db' ), job.totals.rows_changed ],
				[ 'update', __( 'Replacements', 'dl-relocate-db' ), job.totals.replacements ],
				[ 'shield', __( 'Left unchanged', 'dl-relocate-db' ), job.totals.skipped ],
			] )
		);

		if ( job.before_image_url ) {
			results.append(
				el(
					'section',
					{ className: 'dlz-card' },
					el( 'h3', { className: 'dlz-card-title' }, __( 'Original values', 'dl-relocate-db' ) ),
					el( 'p', {}, __( 'Importing this file into the database puts every changed value back as it was, overwriting any edits made to those values since.', 'dl-relocate-db' ) ),
					el(
						'p',
						{},
						el(
							'a',
							{ className: 'button button-primary', href: job.before_image_url },
							el( 'span', { className: 'dashicons dashicons-download', ariaHidden: 'true' } ),
							' ',
							__( 'Download original values (.sql.gz)', 'dl-relocate-db' )
						)
					)
				)
			);
		}

		appendReport( job, __( 'Rows changed', 'dl-relocate-db' ) );
		finishResults( heading, announce, message );
	}

	function applySection( job ) {
		const button = el(
			'button',
			{ type: 'button', className: 'button button-primary dlz-button-lg' },
			el( 'span', { className: 'dashicons dashicons-update', ariaHidden: 'true' } ),
			__( 'Replace in database…', 'dl-relocate-db' )
		);

		button.addEventListener( 'click', () => openDialog( job, button ) );

		return el(
			'section',
			{ className: 'dlz-card dlz-apply' },
			el( 'h3', { className: 'dlz-card-title' }, __( 'Apply these changes', 'dl-relocate-db' ) ),
			el(
				'p',
				{},
				__( 'This writes the replacements shown here to the database, using exactly the search, replacement, tables and columns of this dry run. Rows are processed in batches; each batch is saved completely or not at all.', 'dl-relocate-db' )
			),
			el( 'p', {}, button )
		);
	}

	function appendReport( job, changedLabel ) {
		if ( job.totals.skipped ) {
			results.append(
				el(
					'div',
					{ className: 'notice notice-info inline' },
					el( 'p', {}, __( 'Some matching values are left unchanged because changing them could corrupt data. The reasons are listed per table below.', 'dl-relocate-db' ) )
				)
			);
		}

		if ( ! job.report ) {
			return;
		}

		results.append(
			el(
				'section',
				{ className: 'dlz-card' },
				el( 'h3', { className: 'dlz-card-title' }, __( 'Results per table', 'dl-relocate-db' ) ),
				tablesReport( job.report.tables, changedLabel )
			)
		);

		if ( job.report.samples.length ) {
			results.append( samples( job.report.samples ) );
		}
	}

	/**
	 * The per-table results with sorting, filtering and paging, all in the
	 * browser: the report is already complete once a job has finished.
	 *
	 * @param {Object[]} tables       Per-table results.
	 * @param {string}   changedLabel Heading of the rows-changed column.
	 * @return {HTMLElement} The table and its controls.
	 */
	function tablesReport( tables, changedLabel ) {
		const columns = [
			{ key: 'name', label: __( 'Table', 'dl-relocate-db' ), numeric: false },
			{ key: 'rows_scanned', label: __( 'Rows scanned', 'dl-relocate-db' ), numeric: true },
			{ key: 'rows_changed', label: changedLabel, numeric: true },
			{ key: 'replacements', label: __( 'Replacements', 'dl-relocate-db' ), numeric: true },
		];
		const state = { sort: 'replacements', direction: 'desc', term: '', changedOnly: tables.some( ( table ) => table.rows_changed ), page: 1 };

		const filter = el( 'input', { type: 'search', className: 'dlz-filter', placeholder: __( 'Filter tables…', 'dl-relocate-db' ) } );
		filter.setAttribute( 'aria-label', __( 'Filter tables', 'dl-relocate-db' ) );
		const changedOnly = el( 'input', { type: 'checkbox', checked: state.changedOnly } );
		const caption = el( 'caption', { className: 'screen-reader-text' } );
		const headRow = el( 'tr' );
		const body = el( 'tbody' );
		const pager = el( 'div', { className: 'dlz-pager' } );
		const announcer = el( 'span', { className: 'screen-reader-text', role: 'status' } );

		columns.forEach( ( column ) => {
			const button = el( 'button', { type: 'button', className: 'dlz-sort' }, column.label, el( 'span', { className: 'dashicons', ariaHidden: 'true' } ) );
			button.addEventListener( 'click', () => {
				state.direction = state.sort === column.key && 'desc' === state.direction ? 'asc' : ( state.sort === column.key ? 'desc' : ( column.numeric ? 'desc' : 'asc' ) );
				state.sort = column.key;
				render();
			} );
			headRow.append( el( 'th', { scope: 'col', className: column.numeric ? 'num' : '' }, button ) );
		} );
		headRow.append(
			el( 'th', { scope: 'col' }, __( 'Columns', 'dl-relocate-db' ) ),
			el( 'th', { scope: 'col' }, __( 'Notes', 'dl-relocate-db' ) )
		);

		filter.addEventListener( 'input', () => {
			state.term = filter.value.trim().toLowerCase();
			state.page = 1;
			render();
		} );
		changedOnly.addEventListener( 'change', () => {
			state.changedOnly = changedOnly.checked;
			state.page = 1;
			render();
		} );

		function render() {
			const rows = tables
				.filter( ( table ) => ( ! state.term || table.name.toLowerCase().includes( state.term ) ) && ( ! state.changedOnly || table.rows_changed || table.skipped.length || table.note ) )
				.sort( ( a, b ) => {
					const result = 'name' === state.sort ? a.name.localeCompare( b.name ) : a[ state.sort ] - b[ state.sort ];
					return 'asc' === state.direction ? result : -result;
				} );
			const pages = Math.max( 1, Math.ceil( rows.length / PAGE_SIZE ) );
			state.page = Math.min( state.page, pages );
			const shown = rows.slice( ( state.page - 1 ) * PAGE_SIZE, state.page * PAGE_SIZE );

			[ ...headRow.children ].slice( 0, columns.length ).forEach( ( th, index ) => {
				const active = columns[ index ].key === state.sort;
				const icon = th.querySelector( '.dashicons' );

				if ( active ) {
					th.setAttribute( 'aria-sort', 'asc' === state.direction ? 'ascending' : 'descending' );
				} else {
					th.removeAttribute( 'aria-sort' );
				}
				icon.className = `dashicons dashicons-arrow-${ active && 'asc' === state.direction ? 'up' : 'down' }`;
			} );

			body.replaceChildren(
				...( shown.length ? shown.map( tableRow ) : [ el( 'tr', {}, el( 'td', { colSpan: columns.length + 2, className: 'dlz-empty' }, __( 'No tables match.', 'dl-relocate-db' ) ) ) ] )
			);

			caption.textContent = __( 'Results per table', 'dl-relocate-db' );
			announcer.textContent = sprintf(
				/* translators: 1: tables shown, 2: tables matching. */
				__( 'Showing %1$s of %2$s tables.', 'dl-relocate-db' ),
				numbers.format( shown.length ),
				numbers.format( rows.length )
			);

			pager.replaceChildren();
			if ( pages > 1 ) {
				const previous = el( 'button', { type: 'button', className: 'button', disabled: 1 === state.page }, '‹ ', __( 'Previous', 'dl-relocate-db' ) );
				const next = el( 'button', { type: 'button', className: 'button', disabled: pages === state.page }, __( 'Next', 'dl-relocate-db' ), ' ›' );
				previous.addEventListener( 'click', () => {
					state.page--;
					render();
				} );
				next.addEventListener( 'click', () => {
					state.page++;
					render();
				} );
				pager.append(
					/* translators: 1: current page, 2: number of pages. */
					el( 'span', {}, sprintf( __( 'Page %1$s of %2$s', 'dl-relocate-db' ), numbers.format( state.page ), numbers.format( pages ) ) ),
					previous,
					next
				);
			}
		}

		render();

		return el(
			'div',
			{},
			el(
				'div',
				{ className: 'dlz-table-tools' },
				filter,
				el( 'label', {}, changedOnly, __( 'Only tables with changes or notes', 'dl-relocate-db' ) ),
				announcer
			),
			el(
				'div',
				{ className: 'dlz-table-scroll' },
				el( 'table', { className: 'wp-list-table widefat striped dlz-tables' }, caption, el( 'thead', {}, headRow ), body )
			),
			pager
		);
	}

	function tableRow( table ) {
		const notes = [];

		if ( table.note ) {
			notes.push( table.note );
		}
		table.skipped.forEach( ( skipped ) => {
			notes.push( sprintf(
				/* translators: 1: number of values, 2: reason. */
				__( '%1$s left unchanged: %2$s', 'dl-relocate-db' ),
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
	}

	function samples( list ) {
		return el(
			'section',
			{ className: 'dlz-card dlz-samples' },
			el( 'h3', { className: 'dlz-card-title' }, __( 'Examples', 'dl-relocate-db' ) ),
			el(
				'p',
				{ className: 'description' },
				sprintf(
					/* translators: %s: number of examples. */
					__( 'The first %s changes found, with a little surrounding text.', 'dl-relocate-db' ),
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
						el( 'dt', {}, __( 'Before', 'dl-relocate-db' ) ),
						el( 'dd', {}, el( 'pre', {}, sample.before ) ),
						el( 'dt', {}, __( 'After', 'dl-relocate-db' ) ),
						el( 'dd', {}, el( 'pre', {}, sample.after ) )
					)
				) )
			)
		);
	}

	/* Confirmation -------------------------------------------------------- */

	function openDialog( job, opener ) {
		document.getElementById( 'dlz-confirm-summary' ).replaceChildren(
			el(
				'p',
				{},
				sprintf(
					/* translators: 1: number of replacements, 2: number of rows, 3: number of tables. */
					__( '%1$s replacements in %2$s rows across %3$s tables will be written to the database.', 'dl-relocate-db' ),
					numbers.format( job.totals.replacements ),
					numbers.format( job.totals.rows_changed ),
					numbers.format( job.tables_total )
				)
			),
			el(
				'ul',
				{ className: 'dlz-pair-list' },
				...job.pairs.map( ( pair ) => el(
					'li',
					{},
					el( 'code', {}, pair.search ),
					el( 'span', { ariaHidden: 'true' }, ' → ' ),
					el( 'span', { className: 'screen-reader-text' }, __( 'replaced with', 'dl-relocate-db' ) ),
					pair.replace ? el( 'code', {}, pair.replace ) : el( 'em', {}, __( '(nothing: removed)', 'dl-relocate-db' ) )
				) )
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
		const untransactional = job.untransactional_tables || [];

		if ( untransactional.length ) {
			list.push( sprintf(
				/* translators: %s: comma-separated table names. */
				__( 'These tables do not support transactions, so if the replacement is interrupted a batch in them could be left partly written: %s', 'dl-relocate-db' ),
				untransactional.join( ', ' )
			) );
		}

		if ( job.pairs.some( ( pair ) => pair.replace.includes( pair.search ) ) ) {
			list.push( __( 'The replacement contains the search text. Running this same replacement a second time would apply it again.', 'dl-relocate-db' ) );
		}

		if ( job.touches_site_address ) {
			list.push( __( 'If this changes the site address (siteurl or home), it is changed last and you will need to log in again at the new address.', 'dl-relocate-db' ) );
		}

		return list;
	}

	/* Helpers ------------------------------------------------------------- */

	function startResults( title ) {
		const heading = el( 'h2', { tabIndex: -1, className: 'dlz-title' }, title );
		results.replaceChildren( heading );
		return heading;
	}

	/**
	 * @param {HTMLElement} heading
	 * @param {boolean}     announce     False when showing a job on page load: moving focus then would be jarring.
	 * @param {string}      announcement Text for screen readers.
	 */
	function finishResults( heading, announce, announcement ) {
		results.hidden = false;

		if ( announce ) {
			heading.focus();
			speak( announcement );
		}
	}

	function tiles( items ) {
		return el(
			'ul',
			{ className: 'dlz-tiles' },
			...items.map( ( [ icon, label, value ] ) => el(
				'li',
				{ className: 'dlz-tile' },
				el( 'span', { className: `dlz-tile-icon dashicons dashicons-${ icon }`, ariaHidden: 'true' } ),
				el( 'span', { className: 'dlz-tile-value' }, numbers.format( value ) ),
				el( 'span', { className: 'dlz-tile-label' }, label )
			) )
		);
	}

	function resumeButton( job ) {
		const button = el( 'button', { type: 'button', className: 'button button-primary' }, __( 'Resume', 'dl-relocate-db' ) );
		button.addEventListener( 'click', () => resume( job ) );
		return el( 'p', {}, button );
	}

	function status( type, icon, text ) {
		return el(
			'p',
			{ className: `dlz-status dlz-status-${ type }` },
			el( 'span', { className: `dashicons dashicons-${ icon }`, ariaHidden: 'true' } ),
			text
		);
	}

	/**
	 * @param {string}    type     Notice type: error, warning, success or info.
	 * @param {string}    message  Message text.
	 * @param {Function=} retry    When given, a button to carry on the job.
	 * @param {string=}   label    Label for that button.
	 * @param {boolean=}  announce Whether to read the message out straight away.
	 */
	function showNotice( type, message, retry, label, announce = true ) {
		const notice = el( 'div', { className: `notice notice-${ type }` }, el( 'p', {}, message ) );

		if ( retry ) {
			const button = el( 'button', { type: 'button', className: 'button' }, label || __( 'Resume', 'dl-relocate-db' ) );
			button.addEventListener( 'click', () => {
				notice.remove();
				retry();
			} );
			notice.append( el( 'p', {}, button ) );
		}

		notices.replaceChildren( notice );

		if ( announce ) {
			speak( message, 'assertive' );
		}
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
		runner.setAttribute( 'aria-busy', busy ? 'true' : 'false' );

		if ( submitButton ) {
			submitButton.disabled = busy;
		}

		if ( busy && live ) {
			window.addEventListener( 'beforeunload', warnBeforeLeaving );
		} else {
			window.removeEventListener( 'beforeunload', warnBeforeLeaving );
		}
	}

	function errorMessage( error ) {
		return ( error && error.message ) || __( 'The request failed. Check your connection and try again.', 'dl-relocate-db' );
	}

	function clock( seconds ) {
		const total = Math.max( 0, Math.round( seconds ) );
		const hours = Math.floor( total / 3600 );
		const minutes = Math.floor( ( total % 3600 ) / 60 );
		const rest = String( total % 60 ).padStart( 2, '0' );

		return hours ? `${ hours }:${ String( minutes ).padStart( 2, '0' ) }:${ rest }` : `${ minutes }:${ rest }`;
	}

	function prefersReducedMotion() {
		return window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	}

	function pause( ms ) {
		return new Promise( ( resolve ) => setTimeout( resolve, prefersReducedMotion() ? 0 : ms ) );
	}

	/**
	 * Small element builder. Children are strings (added as text) or nodes.
	 *
	 * @param {string}           tag
	 * @param {Object}           props    Properties set directly on the element.
	 * @param {...(string|Node)} children
	 * @return {HTMLElement} The element.
	 */
	function el( tag, props = {}, ...children ) {
		const element = Object.assign( document.createElement( tag ), props );
		element.append( ...children.map( ( child ) => ( 'number' === typeof child ? String( child ) : child ) ) );
		return element;
	}
}() );
