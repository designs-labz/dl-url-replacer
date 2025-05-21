/*
 * ATTENTION: The "eval" devtool has been used (maybe by default in mode: "development").
 * This devtool is neither made for production nor for readable output files.
 * It uses "eval()" calls to create a separate source file in the browser devtools.
 * If you are trying to read the output file, select a different devtool (https://webpack.js.org/configuration/devtool/)
 * or disable the default devtool with "devtool: false".
 * If you are looking for production-ready output files, see mode: "production" (https://webpack.js.org/configuration/mode/).
 */
/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./admin/assets/js/script.js":
/*!***********************************!*\
  !*** ./admin/assets/js/script.js ***!
  \***********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

eval("__webpack_require__.r(__webpack_exports__);\n/* harmony import */ var _scss_main_scss__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../scss/main.scss */ \"./admin/assets/scss/main.scss\");\n\n\ndocument.addEventListener('DOMContentLoaded', function() {\n\tconsole.log('URL Replacer script loaded');\n\tconst tabButtons = document.querySelectorAll('.tab-button');\n\tconst tabContents = document.querySelectorAll('.tab-content');\n\tconst resultOutput = document.getElementById('results-output');\n\tconst popupOverlay = document.querySelector('.popup-overlay');\n\tconst popupClose = document.getElementById('popup-close');\n\n\ttabButtons.forEach(button => {\n\t\tbutton.addEventListener('click', function() {\n\t\t\tconst tabId = this.dataset.tab;\n\n\t\t\ttabButtons.forEach(btn => btn.classList.remove('active'));\n\t\t\tthis.classList.add('active');\n\n\t\t\ttabContents.forEach(content => content.classList.remove('active'));\n\t\t\tdocument.getElementById(tabId).classList.add('active');\n\t\t});\n\t});\n\n\tconst replaceButtons = [{\n\t\t\tid: 'start-url-replace',\n\t\t\ttype: 'url',\n\t\t\tfind: 'find-url',\n\t\t\treplace: 'replace-url'\n\t\t},\n\t\t{\n\t\t\tid: 'start-custom-replace',\n\t\t\ttype: 'text',\n\t\t\tfind: 'find-text',\n\t\t\treplace: 'replace-text'\n\t\t}\n\t];\n\n\treplaceButtons.forEach(({\n\t\tid,\n\t\ttype,\n\t\tfind,\n\t\treplace\n\t}) => {\n\t\tconst btn = document.getElementById(id);\n\t\tif (btn) {\n\t\t\tbtn.addEventListener('click', function() {\n\t\t\t\tconst findValue = document.getElementById(find).value;\n\t\t\t\tconst replaceValue = document.getElementById(replace).value;\n\n\t\t\t\tconst data = new FormData();\n\t\t\t\tdata.append('action', 'dl_run_replacement');\n\t\t\t\tdata.append('type', type);\n\t\t\t\tdata.append('find', findValue);\n\t\t\t\tdata.append('replace', replaceValue);\n\n\t\t\t\tpopupOverlay.style.display = 'flex';\n\t\t\t\tresultOutput.innerHTML = '';\n\n\t\t\t\tlet width = 0;\n\t\t\t\tconst interval = setInterval(() => {\n\t\t\t\t\twidth += 5;\n\t\t\t\t\tdocument.getElementById('progress-bar-inner').style.width = width + '%';\n\t\t\t\t\tdocument.getElementById('progress-text').textContent = width + '%';\n\t\t\t\t\tif (width >= 100) clearInterval(interval);\n\t\t\t\t}, 150);\n\n\t\t\t\tfetch(dlUrlReplacerAjax.ajax_url, {\n\t\t\t\t\t\tmethod: 'POST',\n\t\t\t\t\t\tbody: data\n\t\t\t\t\t})\n\t\t\t\t\t.then(response => response.json())\n\t\t\t\t\t.then(json => {\n\t\t\t\t\t\tif (json.success && Array.isArray(json.data)) {\n\t\t\t\t\t\t\tresultOutput.style.display = 'block'; // ✅ show it only now\n\t\t\t\t\t\t\tresultOutput.innerHTML = '<strong>Replacement Summary:</strong><ul>' + json.data.map(entry =>\n\t\t\t\t\t\t\t\t`<li><strong>Table:</strong> ${entry.table} | <strong>Column:</strong> ${entry.column} | <strong>Rows Affected:</strong> ${entry.rows_affected}</li>`\n\t\t\t\t\t\t\t).join('') + '</ul>';\n\t\t\t\t\t\t} else {\n\t\t\t\t\t\t\tresultOutput.style.display = 'block';\n\t\t\t\t\t\t\tresultOutput.innerHTML = '<p>No matches found or something went wrong.</p>';\n\t\t\t\t\t\t}\n\t\t\t\t\t});\n\t\t\t});\n\t\t}\n\t});\n\n\tpopupClose.addEventListener('click', function() {\n\t\tpopupOverlay.style.display = 'none';\n\t});\n});\n\n\n//# sourceURL=webpack://dl-url-replacer/./admin/assets/js/script.js?");

/***/ }),

/***/ "./admin/assets/scss/main.scss":
/*!*************************************!*\
  !*** ./admin/assets/scss/main.scss ***!
  \*************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

eval("__webpack_require__.r(__webpack_exports__);\n// extracted by mini-css-extract-plugin\n\n\n//# sourceURL=webpack://dl-url-replacer/./admin/assets/scss/main.scss?");

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
/******/ 	
/******/ 	// startup
/******/ 	// Load entry module and return exports
/******/ 	// This entry module can't be inlined because the eval devtool is used.
/******/ 	var __webpack_exports__ = __webpack_require__("./admin/assets/js/script.js");
/******/ 	
/******/ })()
;