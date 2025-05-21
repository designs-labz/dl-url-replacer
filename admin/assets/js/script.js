import '../scss/main.scss';

document.addEventListener('DOMContentLoaded', function() {
	console.log('URL Replacer script loaded');
	const tabButtons = document.querySelectorAll('.tab-button');
	const tabContents = document.querySelectorAll('.tab-content');
	const resultOutput = document.getElementById('results-output');
	const popupOverlay = document.querySelector('.popup-overlay');
	const popupClose = document.getElementById('popup-close');

	tabButtons.forEach(button => {
		button.addEventListener('click', function() {
			const tabId = this.dataset.tab;

			tabButtons.forEach(btn => btn.classList.remove('active'));
			this.classList.add('active');

			tabContents.forEach(content => content.classList.remove('active'));
			document.getElementById(tabId).classList.add('active');
		});
	});

	const replaceButtons = [{
			id: 'start-url-replace',
			type: 'url',
			find: 'find-url',
			replace: 'replace-url'
		},
		{
			id: 'start-custom-replace',
			type: 'text',
			find: 'find-text',
			replace: 'replace-text'
		}
	];

	replaceButtons.forEach(({
		id,
		type,
		find,
		replace
	}) => {
		const btn = document.getElementById(id);
		if (btn) {
			btn.addEventListener('click', function() {
				const findValue = document.getElementById(find).value;
				const replaceValue = document.getElementById(replace).value;

				const data = new FormData();
				data.append('action', 'dl_run_replacement');
				data.append('type', type);
				data.append('find', findValue);
				data.append('replace', replaceValue);

				popupOverlay.style.display = 'flex';
				resultOutput.innerHTML = '';

				let width = 0;
				const interval = setInterval(() => {
					width += 5;
					document.getElementById('progress-bar-inner').style.width = width + '%';
					document.getElementById('progress-text').textContent = width + '%';
					if (width >= 100) clearInterval(interval);
				}, 150);

				fetch(dlUrlReplacerAjax.ajax_url, {
						method: 'POST',
						body: data
					})
					.then(response => response.json())
					.then(json => {
						if (json.success && Array.isArray(json.data)) {
							resultOutput.style.display = 'block'; // ✅ show it only now
							resultOutput.innerHTML = '<strong>Replacement Summary:</strong><ul>' + json.data.map(entry =>
								`<li><strong>Table:</strong> ${entry.table} | <strong>Column:</strong> ${entry.column} | <strong>Rows Affected:</strong> ${entry.rows_affected}</li>`
							).join('') + '</ul>';
						} else {
							resultOutput.style.display = 'block';
							resultOutput.innerHTML = '<p>No matches found or something went wrong.</p>';
						}
					});
			});
		}
	});

	popupClose.addEventListener('click', function() {
		popupOverlay.style.display = 'none';
	});
});
