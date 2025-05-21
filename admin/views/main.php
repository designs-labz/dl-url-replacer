<div class="dl-wrapper">
    <h2>DL URL Replacer</h2>
    <div class="tabs">
        <button class="tab-button active" data-tab="url-tab">URL Replace</button>
        <button class="tab-button" data-tab="custom-tab">Custom Replace</button>
    </div>
    <div class="tab-content active" id="url-tab">
        <div class="form-group">
            <label for="find-url">Find URL</label>
            <input type="text" id="find-url" placeholder="https://oldsite.com">
        </div>
        <div class="form-group">
            <label for="replace-url">Replace With</label>
            <input type="text" id="replace-url" placeholder="https://newsite.com">
        </div>
        <div class="form-group">
            <input type="checkbox" id="include-https" checked>
            <label for="include-https">Include HTTPS/HTTP variation</label>
        </div>
        <button id="start-url-replace" class="primary-button">Start Replacing</button>
    </div>
    <div class="tab-content" id="custom-tab">
        <div class="form-group">
            <label for="find-text">Find Text</label>
            <input type="text" id="find-text" placeholder="Old text">
        </div>
        <div class="form-group">
            <label for="replace-text">Replace With</label>
            <input type="text" id="replace-text" placeholder="New text">
        </div>
        <button id="start-custom-replace" class="primary-button">Start Replacing</button>
    </div>
    <div id="results-output" class="results-output" style="display: none;"></div>

    <div class="popup-overlay" style="display:none;">
        <div class="popup">
            <div class="progress-wrapper">
                <div class="progress-bar"><span style="width: 0%;" id="progress-bar-inner"></span></div>
                <p id="progress-text">0%</p>
                <button id="popup-close" class="secondary-button">Close</button>
            </div>
        </div>
    </div>
</div>
