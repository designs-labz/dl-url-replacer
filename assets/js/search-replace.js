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
		progress.text.textContent = __( 'Stopping after the current batch…', 'designslabz-relocate' );
	} );

	if ( runner.dataset.job ) {
		showJob( JSON.parse( runner.dataset.job ) );
	}

	/* Form ---------------------------------------------------------------- */

	function initForm() {
		const filter = document.getElementById( 'dlz-table-filter' );
		const count = document.getElementById( 'dlz-table-count' );
		const none = document.getElementById( 'dlz-table-none' );
		const rows = [ ...form.querySelectorAll( '.dlz-picker-row' ) ];
		const tableBoxes = () => [ ...form.querySelectorAll( 'input[name="tables[]"]' ) ];

		const updateCount = () => {
			const boxes = tableBoxes();
			count.textContent = sprintf(
				/* translators: 1: selected tables, 2: all tables. */
				__( '%1$s of %2$s tables selected', 'designslabz-relocate' ),
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

	async function onSubmit( event ) {
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
					exclude_columns: excludedColumns( tables ),
				},
			} );
		} catch ( error ) {
			setBusy( false );
			showNotice( 'error', errorMessage( error ) );
			return;
		}

		speak( __( 'Dry run started.', 'designslabz-relocate' ) );
		run( job );
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
			? __( 'This job stopped before it finished, probably because the page running it was closed. Continuing carries on from the last completed batch.', 'designslabz-relocate' )
			: __( 'This job is still running, possibly in another tab. If that tab was closed, continue it here.', 'designslabz-relocate' );

		showNotice( 'warning', message, () => run( job ), __( 'Continue', 'designslabz-relocate' ), false );
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

	/* Progress ------------------------------------------------------------ */

	function startProgress( job ) {
		progress.panel.hidden = false;
		progress.heading.textContent = job.dry_run
			? __( 'Dry run in progress', 'designslabz-relocate' )
			: __( 'Replacing in the database', 'designslabz-relocate' );
		progress.changedLabel.textContent = job.dry_run
			? __( 'Rows to change', 'designslabz-relocate' )
			: __( 'Rows changed', 'designslabz-relocate' );
		progress.text.textContent = job.dry_run
			? __( 'Starting…', 'designslabz-relocate' )
			: __( 'Starting… Keep this page open until the replacement finishes.', 'designslabz-relocate' );

		cancelButton.disabled = false;
		cancelButton.textContent = job.dry_run ? __( 'Cancel', 'designslabz-relocate' ) : __( 'Stop after this batch', 'designslabz-relocate' );

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
					__( 'Working on %1$s (table %2$s of %3$s)', 'designslabz-relocate' ),
					job.current_table,
					numbers.format( job.tables_done + 1 ),
					numbers.format( job.tables_total )
				)
				: __( 'Finishing…', 'designslabz-relocate' );
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
			speak( sprintf( __( '%s percent done.', 'designslabz-relocate' ), quarter ) );
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
			progress.text.textContent = __( 'Done.', 'designslabz-relocate' );
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
			__( 'about %s', 'designslabz-relocate' ),
			clock( ( elapsed / done ) * ( 100 - percent ) )
		);
	}

	function tick() {
		progress.elapsed.textContent = clock( ( Date.now() - progress.startedAt ) / 1000 );
	}

	/* Results ------------------------------------------------------------- */

	function renderDryRun( job, announce = true ) {
		const heading = startResults( __( 'Dry run results', 'designslabz-relocate' ) );

		if ( 'completed' === job.status ) {
			results.append( status( 'success', 'yes-alt', __( 'Dry run complete. Nothing in the database was changed.', 'designslabz-relocate' ) ) );
		} else if ( 'cancelled' === job.status ) {
			results.append( status( 'warning', 'dismiss', __( 'Dry run cancelled. The figures below cover only the part that was searched.', 'designslabz-relocate' ) ) );
		} else {
			results.append( status( 'error', 'warning', job.error || __( 'The dry run stopped because of an error.', 'designslabz-relocate' ) ) );
			results.append( resumeButton( job ) );
		}

		results.append(
			tiles( [
				[ 'database', __( 'Tables searched', 'designslabz-relocate' ), job.tables_done ],
				[ 'editor-table', __( 'Rows scanned', 'designslabz-relocate' ), job.totals.rows_scanned ],
				[ 'edit', __( 'Rows that would change', 'designslabz-relocate' ), job.totals.rows_changed ],
				[ 'update', __( 'Replacements', 'designslabz-relocate' ), job.totals.replacements ],
				[ 'shield', __( 'Left unchanged', 'designslabz-relocate' ), job.totals.skipped ],
			] )
		);

		if ( job.executable ) {
			results.append( applySection( job ) );
		} else if ( 'completed' === job.status && ! job.totals.rows_changed ) {
			results.append( el( 'p', { className: 'dlz-empty' }, __( 'No matches were found, so there is nothing to replace.', 'designslabz-relocate' ) ) );
		}

		appendReport( job, __( 'Rows to change', 'designslabz-relocate' ) );

		finishResults( heading, announce, sprintf(
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

	function renderLiveRun( job, announce = true ) {
		const heading = startResults( __( 'Replacement results', 'designslabz-relocate' ) );
		let message;

		if ( 'completed' === job.status ) {
			message = __( 'Replacement complete. The changes below have been written to the database.', 'designslabz-relocate' );
			results.append( status( 'success', 'yes-alt', message ) );
		} else if ( 'cancelled' === job.status ) {
			message = __( 'Replacement stopped. Batches finished before stopping were written to the database; the rest was not touched.', 'designslabz-relocate' );
			results.append( status( 'warning', 'dismiss', message ) );
		} else {
			message = job.error || __( 'The replacement stopped because of an error.', 'designslabz-relocate' );
			results.append( status( 'error', 'warning', message ) );
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
			tiles( [
				[ 'database', __( 'Tables processed', 'designslabz-relocate' ), job.tables_done ],
				[ 'editor-table', __( 'Rows scanned', 'designslabz-relocate' ), job.totals.rows_scanned ],
				[ 'edit', __( 'Rows changed', 'designslabz-relocate' ), job.totals.rows_changed ],
				[ 'update', __( 'Replacements', 'designslabz-relocate' ), job.totals.replacements ],
				[ 'shield', __( 'Left unchanged', 'designslabz-relocate' ), job.totals.skipped ],
			] )
		);

		if ( job.before_image_url ) {
			results.append(
				el(
					'section',
					{ className: 'dlz-card' },
					el( 'h3', { className: 'dlz-card-title' }, __( 'Original values', 'designslabz-relocate' ) ),
					el( 'p', {}, __( 'Importing this file into the database puts every changed value back as it was, overwriting any edits made to those values since.', 'designslabz-relocate' ) ),
					el(
						'p',
						{},
						el(
							'a',
							{ className: 'button button-primary', href: job.before_image_url },
							el( 'span', { className: 'dashicons dashicons-download', ariaHidden: 'true' } ),
							' ',
							__( 'Download original values (.sql.gz)', 'designslabz-relocate' )
						)
					)
				)
			);
		}

		appendReport( job, __( 'Rows changed', 'designslabz-relocate' ) );
		finishResults( heading, announce, message );
	}

	function applySection( job ) {
		const button = el(
			'button',
			{ type: 'button', className: 'button button-primary dlz-button-lg' },
			el( 'span', { className: 'dashicons dashicons-update', ariaHidden: 'true' } ),
			__( 'Replace in database…', 'designslabz-relocate' )
		);

		button.addEventListener( 'click', () => openDialog( job, button ) );

		return el(
			'section',
			{ className: 'dlz-card dlz-apply' },
			el( 'h3', { className: 'dlz-card-title' }, __( 'Apply these changes', 'designslabz-relocate' ) ),
			el(
				'p',
				{},
				__( 'This writes the replacements shown here to the database, using exactly the search, replacement, tables and columns of this dry run. Rows are processed in batches; each batch is saved completely or not at all.', 'designslabz-relocate' )
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
					el( 'p', {}, __( 'Some matching values are left unchanged because changing them could corrupt data. The reasons are listed per table below.', 'designslabz-relocate' ) )
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
				el( 'h3', { className: 'dlz-card-title' }, __( 'Results per table', 'designslabz-relocate' ) ),
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
			{ key: 'name', label: __( 'Table', 'designslabz-relocate' ), numeric: false },
			{ key: 'rows_scanned', label: __( 'Rows scanned', 'designslabz-relocate' ), numeric: true },
			{ key: 'rows_changed', label: changedLabel, numeric: true },
			{ key: 'replacements', label: __( 'Replacements', 'designslabz-relocate' ), numeric: true },
		];
		const state = { sort: 'replacements', direction: 'desc', term: '', changedOnly: tables.some( ( table ) => table.rows_changed ), page: 1 };

		const filter = el( 'input', { type: 'search', className: 'dlz-filter', placeholder: __( 'Filter tables…', 'designslabz-relocate' ) } );
		filter.setAttribute( 'aria-label', __( 'Filter tables', 'designslabz-relocate' ) );
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
			el( 'th', { scope: 'col' }, __( 'Columns', 'designslabz-relocate' ) ),
			el( 'th', { scope: 'col' }, __( 'Notes', 'designslabz-relocate' ) )
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
				...( shown.length ? shown.map( tableRow ) : [ el( 'tr', {}, el( 'td', { colSpan: columns.length + 2, className: 'dlz-empty' }, __( 'No tables match.', 'designslabz-relocate' ) ) ) ] )
			);

			caption.textContent = __( 'Results per table', 'designslabz-relocate' );
			announcer.textContent = sprintf(
				/* translators: 1: tables shown, 2: tables matching. */
				__( 'Showing %1$s of %2$s tables.', 'designslabz-relocate' ),
				numbers.format( shown.length ),
				numbers.format( rows.length )
			);

			pager.replaceChildren();
			if ( pages > 1 ) {
				const previous = el( 'button', { type: 'button', className: 'button', disabled: 1 === state.page }, '‹ ', __( 'Previous', 'designslabz-relocate' ) );
				const next = el( 'button', { type: 'button', className: 'button', disabled: pages === state.page }, __( 'Next', 'designslabz-relocate' ), ' ›' );
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
					el( 'span', {}, sprintf( __( 'Page %1$s of %2$s', 'designslabz-relocate' ), numbers.format( state.page ), numbers.format( pages ) ) ),
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
				el( 'label', {}, changedOnly, __( 'Only tables with changes or notes', 'designslabz-relocate' ) ),
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
	}

	function samples( list ) {
		return el(
			'section',
			{ className: 'dlz-card dlz-samples' },
			el( 'h3', { className: 'dlz-card-title' }, __( 'Examples', 'designslabz-relocate' ) ),
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

	/* Confirmation -------------------------------------------------------- */

	function openDialog( job, opener ) {
		document.getElementById( 'dlz-confirm-summary' ).replaceChildren(
			el(
				'p',
				{},
				sprintf(
					/* translators: 1: number of replacements, 2: number of rows, 3: number of tables. */
					__( '%1$s replacements in %2$s rows across %3$s tables will be written to the database.', 'designslabz-relocate' ),
					numbers.format( job.totals.replacements ),
					numbers.format( job.totals.rows_changed ),
					numbers.format( job.tables_total )
				)
			),
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
		const untransactional = job.untransactional_tables || [];

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

		if ( job.touches_site_address ) {
			list.push( __( 'If this changes the site address (siteurl or home), it is changed last and you will need to log in again at the new address.', 'designslabz-relocate' ) );
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
		const button = el( 'button', { type: 'button', className: 'button button-primary' }, __( 'Resume', 'designslabz-relocate' ) );
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
			const button = el( 'button', { type: 'button', className: 'button' }, label || __( 'Resume', 'designslabz-relocate' ) );
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
		return ( error && error.message ) || __( 'The request failed. Check your connection and try again.', 'designslabz-relocate' );
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
