(function () {
	'use strict';
	if (window.__pandaSharedAdminUI) return;
	window.__pandaSharedAdminUI = true;

	var config = window.CONTACTPANDA_ADMIN_UI || {};
	var german = /^de(?:-|$)/i.test(document.documentElement.lang || '');
	var i18n = Object.assign(german ? {chooseColor:'Farbe auswählen',colorValue:'Farbwert',themeColors:'Theme- und Vorlagenfarben',chooseImage:'Bild auswählen',useImage:'Bild verwenden',dropImage:'Bild anklicken oder hier ablegen',changeImage:'Bild ersetzen',editImage:'Bild bearbeiten',removeImage:'Bild entfernen',externalImage:'Externes Bild verknüpfen',externalImagePrompt:'URL des externen Bildes eingeben:',chooseFile:'Datei auswählen oder hier ablegen',fileFormats:'Von WordPress unterstützte Dateitypen',imageFormats:'JPG, PNG, WebP oder SVG'} : {}, config.i18n || {});
	/* Public Panda action contract. Prefer data-contactpanda-action in markup. */
	var actionDefinitions = {
		doorClosed: {customIcon: 'door-closed'}, doorOpen: {customIcon: 'door-open'},
		view: {icon: 'visibility'}, edit: {icon: 'edit'}, duplicate: {icon: 'admin-page'}, delete: {icon: 'trash', state: 'danger'},
		confirm: {icon: 'yes-alt', state: 'success'}, cancel: {icon: 'dismiss', state: 'danger'}, email: {icon: 'email-alt'}, report: {icon: 'chart-bar'},
		external: {icon: 'external'}, publish: {icon: 'megaphone', state: 'primary'}, archive: {icon: 'archive'}, deactivate: {customIcon: 'power', state: 'danger'},
		remove: {icon: 'minus'}, refresh: {icon: 'update'}, website: {icon: 'admin-site-alt3'}, export: {customIcon: 'file-output'}, import: {customIcon: 'file-input'},
		download: {icon: 'download'}, add: {icon: 'plus-alt2', state: 'primary'}, drag: {icon: 'move'}, moveUp: {icon: 'arrow-up-alt2'}, moveDown: {icon: 'arrow-down-alt2'},
		undo: {icon: 'undo'}, redo: {icon: 'redo'}, image: {icon: 'format-image'}, desktop: {icon: 'desktop'}, tablet: {icon: 'tablet'}, mobile: {icon: 'smartphone'},
		template: {icon: 'layout'}, templateCreate: {icon: 'insert', state: 'primary'}, templateGallery: {icon: 'grid-view'}, draft: {icon: 'edit-page'}, save: {icon: 'saved', state: 'success'},
		schedule: {icon: 'calendar-alt'}, levelUp: {customIcon: 'corner-left-up'}, back: {icon: 'arrow-left-alt2'}, print: {icon: 'printer'}, attachment: {icon: 'paperclip'},
		search: {icon: 'search'}, searchAdd: {customIcon: 'search-add'}, filter: {icon: 'filter'}, list: {icon: 'list-view'}, unlock: {icon: 'unlock'}, restore: {icon: 'undo'},
		apply: {icon: 'yes-alt'}, accept: {customIcon: 'arrow-right-to-line'}, activate: {customIcon: 'power'}, reset: {icon: 'undo'},
		test: {icon: 'admin-tools'}, start: {icon: 'controls-play'}, people: {icon: 'admin-users'}
	};
	var actionIcons = [
		[/^(open door|tür öffnen|tuer oeffnen)$/i, 'doorOpen'], [/^(close door|tür schließen|tuer schliessen)$/i, 'doorClosed'],
		[/^(view|preview|details|show|anzeigen|ansehen|vorschau|details anzeigen|view \/ edit|ansehen \/ bearbeiten)$/i, 'view'],
		[/^(edit|bearbeiten|ändern)( .+)?$/i, 'edit'], [/^(delete|trash|löschen|in den papierkorb|in papierkorb verschieben)( .+)?$/i, 'delete'],
		[/^(remove assignment|zuordnung entfernen|remove|entfernen)$/i, 'remove'], [/^(duplicate|copy|duplizieren|kopieren)( .+)?$/i, 'duplicate'],
		[/^(export|exportieren)$/i, 'export'], [/^(import|importieren)$/i, 'import'], [/^(download|herunterladen)$/i, 'download'], [/^(restore|wiederherstellen)$/i, 'restore'],
		[/^(view records|datensätze anzeigen|show records)$/i, 'list'], [/^(rollback import|import zurückrollen|import rückgängig machen)$/i, 'undo'],
		[/^(allow address again|adresse wieder zulassen)$/i, 'unlock'], [/^(open review|prüfung öffnen|kontrolle öffnen|review|inspect|prüfen|kontrollieren)$/i, 'search'],
		[/^(activate|approve|confirm|aktivieren|genehmigen|bestätigen)$/i, 'confirm'], [/^(cancel|close|abbrechen|schließen)$/i, 'cancel'],
		[/^(deactivate|disable|pause|deaktivieren|sperren|pausieren)$/i, 'deactivate'], [/^(send|resend|send test|versenden|erneut senden|test senden|email|e-mail)$/i, 'email'],
		[/^(publish|veröffentlichen)$/i, 'publish'], [/^(save as draft|draft|als entwurf speichern|entwurf speichern)$/i, 'draft'],
		[/^(save|save changes|speichern|änderungen speichern)$/i, 'save'], [/^(schedule|schedule publication|planen|veröffentlichung planen)$/i, 'schedule'],
		[/^(archive|archivieren)$/i, 'archive'], [/^(refresh|sync|synchronisieren|aktualisieren)$/i, 'refresh'],
		[/^(report|log|reporting|bericht|protokoll|auswertung)$/i, 'report'], [/^(template|vorlage)$/i, 'template'],
		[/^(create template|vorlage erstellen)$/i, 'templateCreate'], [/^(template gallery|vorlagengalerie)$/i, 'templateGallery'],
		[/^(one level up|up one level|eine ebene höher|zur übergeordneten ebene)$/i, 'levelUp'], [/^(back|zurück)$/i, 'back'],
		[/^(print|print list|drucken|liste drucken)$/i, 'print'], [/^(search|suchen)$/i, 'search'], [/^(filter|filtern)$/i, 'filter'],
		[/^(open|öffnen)$/i, 'external']
	];
	var largeActionLabels = [
		[/^(find duplicates|dubletten suchen)$/i, 'search'],
		[/^(all|alle|active forms|aktive formulare)$/i, 'list'],
		[/^(customers|besteller|kunden|people|personen)$/i, 'people'],
		[/^(companies|firmen)$/i, 'website'],
		[/^(discover|entdecken)( .+)?$/i, 'external'],
		[/^(event attendees|event-besucher)$/i, 'schedule'],
		[/^(archive|archiv)$/i, 'archive'],
		[/^(save as draft|save draft|draft|als entwurf speichern|entwurf speichern)$/i, 'draft'],
		[/^(publish|publish automation|publish \/ update|update \/ publish|veröffentlichen|automatisierung veröffentlichen)( .+)?$/i, 'publish'],
		[/^(schedule|schedule publication|planen|veröffentlichung planen)( .+)?$/i, 'schedule'],
		[/^(save|save changes|save and use|speichern|änderungen speichern|speichern und verwenden|portaleinstellungen speichern)( .+)?$/i, 'save'],
		[/^(apply|apply changes|anwenden)( .+)?$/i, 'apply'],
		[/^(accept|adopt|use|übernehmen)( .+)?$/i, 'accept'],
		[/^(update|refresh|sync|aktualisieren|erneuern|synchronisieren)( .+)?$/i, 'refresh'],
		[/^(activate|enable|aktivieren|freischalten)( .+)?$/i, 'activate'],
		[/^(deactivate|disable|deaktivieren|sperren)( .+)?$/i, 'deactivate'],
		[/^(reset|restore defaults|zurücksetzen|standard wiederherstellen)( .+)?$/i, 'reset'],
		[/^(test|test connection|verbindung testen|prüfen)( .+)?$/i, 'test'],
		[/^(check for updates|nach updates suchen|retry|erneut versuchen)( .+)?$/i, 'refresh'],
		[/^(confirm|bestätigen)( .+)?$/i, 'apply'], [/^(continue|fortfahren)( .+)?$/i, 'accept'],
		[/^(cancel|abbrechen|close|schließen)$/i, 'cancel'],
		[/^(preview|vorschau)( .+)?$/i, 'view'],
		[/^(send test|test senden|send|versenden)( .+)?$/i, 'email'],
		[/^(import|importieren)( .+)?$/i, 'import'], [/^(export|exportieren)( .+)?$/i, 'export'],
		[/^(download|herunterladen)( .+)?$/i, 'download'], [/^(print|drucken)( .+)?$/i, 'print'],
		[/^(start|run|execute|migration starten|starten|ausführen)( .+)?$/i, 'start'],
		[/^(add|new|create|hinzufügen|neu(?:e|er|es|en)?|erstellen)( .+)?$/i, 'add'],
		[/^(copy|duplicate|kopieren|duplizieren)( .+)?$/i, 'duplicate'], [/^(clear|leeren)( .+)?$/i, 'reset'],
		[/^(open|öffnen)( .+)?$/i, 'external'], [/^(edit|bearbeiten)( .+)?$/i, 'edit'],
		[/^(search|suchen)( .+)?$/i, 'search'], [/^(filter|filtern)( .+)?$/i, 'filter']
	];
	/* Detect action words anywhere in descriptive labels. Order matters when a
	 * label combines actions: publishing or scheduling is more specific than
	 * saving, and deactivating is more specific than activating. */
	var largeActionSuffixes = [
		[/\b(draft|entwurf)\b/i, 'draft'],
		[/\b(publish|publishing|veröffentlichen|veröffentlichung)\b/i, 'publish'],
		[/\b(schedule|scheduled|scheduling|planen|geplant)\b/i, 'schedule'],
		[/\b(deactivate|disable|deaktivieren|sperren)\b/i, 'deactivate'],
		[/\b(activate|enable|aktivieren|freischalten)\b/i, 'activate'],
		[/\b(save|saving|saved|speichern|gespeichert)\b/i, 'save'],
		[/\b(apply|applying|anwenden)\b/i, 'apply'], [/\b(accept|adopt|use)\b|übernehmen/i, 'accept'],
		[/\b(update|updates|updating|updated|updaten|refresh|sync|fetch|retrieve|reload|aktualisieren|aktualisierung|aktualisierungen|erneuern|synchronisieren|abrufen)\b|neu laden/i, 'refresh'],
		[/\b(reset|restore|zurücksetzen|wiederherstellen)\b/i, 'reset'],
		[/\b(test|check|validate|testen|prüfen|validieren)\b/i, 'test'],
		[/\b(send|sending|versenden|senden)\b/i, 'email'], [/\b(import|importieren)\b/i, 'import'],
		[/\b(export|exportieren)\b/i, 'export'], [/\b(download|herunterladen)\b/i, 'download'],
		[/\b(start|run|execute|starten|ausführen)\b/i, 'start'],
		[/\b(add|create|new|hinzufügen|erstellen|anlegen|zuordnen|neu(?:e|er|es|en)?)\b/i, 'add'],
		[/\b(copy|duplicate|kopieren|duplizieren)\b/i, 'duplicate'],
		[/\b(delete|trash|löschen|papierkorb)\b/i, 'delete'],
		[/\b(confirm|approve|bestätigen|genehmigen)\b/i, 'confirm'],
		[/\b(cancel|close|abbrechen|schließen)\b/i, 'cancel'],
		[/\b(disconnect|separate|trennen)\b/i, 'deactivate'],
		[/\b(preview|view|show|anzeigen|ansehen|vorschau)\b/i, 'view'],
		[/\b(print|drucken)\b/i, 'print'], [/\b(open|öffnen)\b/i, 'external'],
		[/\b(search|suchen)\b/i, 'search'], [/\b(filter|filtern)\b/i, 'filter']
	];
	var largeActionRootSelector = [
		'.contactpanda-wrap', '.contactpanda-business-wrap', '.contactpanda-dashboard',
		'.contactpanda-newsletter-page', '.contactpanda-mailing-editor-page', '.contactpanda-mailing-details-page',
		'.contactpanda-form-editor-page', '.contactpanda-flow-composer-page', '.contactpanda-helpdesk', '.contactpanda-tags-page',
		'.pandawoo-pro-admin', '.pandawoo-page', '.pandawoo-page-shell', '.panda-seasons-dashboard',
		'.panda-seasons-free-dashboard', '.panda-seasons-templates-page', '.panda-seasons-preview-page', '.cpls-wrap', '.wrap'
	].join(',');
	var compactActionContainers = [
		'td', '.contactpanda-list-action', '.contactpanda-inline-row-action', '.contactpanda-template-actions',
		'.contactpanda-gallery-row-actions', '.contactpanda-system-email-block-actions', '.cpb-actions', '.cpb-card-actions',
		'.pandawoo-template-actions', '.pandawoo-pro-license-actions', '.panda-seasons-actions',
		'.panda-seasons-composer__door-actions', '.panda-seasons-composer__layer-actions', '.cpls-actions', '.cpls-row-actions'
	].join(',');

	function findAll(root, selector) {
		root = root || document;
		var matches = [];
		if (root.nodeType === 1 && root.matches(selector)) matches.push(root);
		return matches.concat(Array.prototype.slice.call(root.querySelectorAll(selector)));
	}

	function proxyInputAction(control, label) {
		if (control.tagName !== 'INPUT') return control;
		var proxy = document.createElement('button');
		var inputType = (control.getAttribute('type') || 'submit').toLowerCase();
		proxy.type = inputType === 'reset' ? 'reset' : inputType === 'submit' ? 'submit' : 'button';
		proxy.className = control.className;
		proxy.textContent = label;
		proxy.disabled = control.disabled;
		Array.prototype.slice.call(control.attributes).forEach(function (attribute) {
			if (attribute.name !== 'type' && attribute.name !== 'value' && attribute.name !== 'class') proxy.setAttribute(attribute.name, attribute.value);
		});
		if (control.name) proxy.name = control.name;
		if (control.value) proxy.value = control.value;
		proxy.dataset.contactpandaLargeActionReady = '1';
		control.dataset.contactpandaLargeActionReady = '1';
		if (proxy.type === 'button') {
			control.classList.add('contactpanda-large-action-source');
			control.hidden = true;
			control.style.setProperty('display', 'none', 'important');
			control.tabIndex = -1;
			control.setAttribute('aria-hidden', 'true');
			control.parentNode.insertBefore(proxy, control);
			proxy.addEventListener('click', function () { if (!control.disabled) control.click(); });
		} else {
			control.parentNode.replaceChild(proxy, control);
		}
		return proxy;
	}

	function parseColor(value) {
		value = String(value || '').trim();
		var match;
		if ((match = value.match(/^#([0-9a-f]{3}|[0-9a-f]{6})$/i))) {
			var hex = match[1];
			if (hex.length === 3) hex = hex.split('').map(function (part) { return part + part; }).join('');
			return {r: parseInt(hex.slice(0, 2), 16), g: parseInt(hex.slice(2, 4), 16), b: parseInt(hex.slice(4, 6), 16), a: 1};
		}
		if ((match = value.match(/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})(?:\s*,\s*(0|1|0?\.\d+))?\s*\)$/i))) {
			return {r: Math.min(255, Number(match[1])), g: Math.min(255, Number(match[2])), b: Math.min(255, Number(match[3])), a: match[4] === undefined ? 1 : Math.min(1, Math.max(0, Number(match[4])))};
		}
		return null;
	}

	function colorString(color) {
		function hex(part) { return Math.round(part).toString(16).padStart(2, '0'); }
		if (color.a >= 0.999) return '#' + hex(color.r) + hex(color.g) + hex(color.b);
		return 'rgba(' + Math.round(color.r) + ', ' + Math.round(color.g) + ', ' + Math.round(color.b) + ', ' + Math.round(color.a * 100) / 100 + ')';
	}

	function rgbToHsv(color) {
		var r = color.r / 255, g = color.g / 255, b = color.b / 255, max = Math.max(r, g, b), min = Math.min(r, g, b), delta = max - min, hue = 0;
		if (delta) {
			if (max === r) hue = ((g - b) / delta) % 6;
			else if (max === g) hue = (b - r) / delta + 2;
			else hue = (r - g) / delta + 4;
			hue *= 60;
			if (hue < 0) hue += 360;
		}
		return {h: hue, s: max ? delta / max : 0, v: max, a: color.a};
	}

	function hsvToRgb(color) {
		var chroma = color.v * color.s, section = color.h / 60, x = chroma * (1 - Math.abs(section % 2 - 1)), rgb = [0, 0, 0];
		if (section < 1) rgb = [chroma, x, 0]; else if (section < 2) rgb = [x, chroma, 0]; else if (section < 3) rgb = [0, chroma, x]; else if (section < 4) rgb = [0, x, chroma]; else if (section < 5) rgb = [x, 0, chroma]; else rgb = [chroma, 0, x];
		var m = color.v - chroma;
		return {r: (rgb[0] + m) * 255, g: (rgb[1] + m) * 255, b: (rgb[2] + m) * 255, a: color.a};
	}

	function uniqueColors(values) {
		var found = [];
		(values || []).forEach(function (value) {
			value = String(value || '').trim();
			var parsed = parseColor(value);
			if (!parsed) return;
			value = colorString(parsed).toLowerCase();
			if (found.indexOf(value) < 0) found.push(value);
		});
		return found;
	}

	function availableColors(field) {
		var colors = (field.dataset.colors || '').split(',').concat(config.themeColors || []);
		document.querySelectorAll('.contactpanda-theme-color-palette [data-color]').forEach(function (swatch) { colors.push(swatch.dataset.color); });
		return uniqueColors(colors);
	}

	function bindPointer(control, callback) {
		function update(event) {
			var rect = control.getBoundingClientRect();
			callback(Math.min(1, Math.max(0, (event.clientX - rect.left) / rect.width)), Math.min(1, Math.max(0, (event.clientY - rect.top) / rect.height)));
		}
		control.addEventListener('pointerdown', function (event) {
			event.preventDefault(); control.setPointerCapture(event.pointerId); update(event);
		});
		control.addEventListener('pointermove', function (event) { if (control.hasPointerCapture(event.pointerId)) update(event); });
	}

	function enhanceColorPicker(input) {
		if (!input || input.dataset.contactpandaColorReady === '1') return;
		input.dataset.contactpandaColorReady = '1';
		var field = input.closest('.contactpanda-settings-color-field, .contactpanda-color-field');
		if (!field) {
			if (input.parentElement && input.parentElement.tagName === 'LABEL') {
				field = input.parentElement;
				field.classList.add('contactpanda-settings-color-field', 'contactpanda-color-field');
			} else {
				field = document.createElement('span');
				field.className = 'contactpanda-settings-color-field contactpanda-color-field';
				input.parentNode.insertBefore(field, input);
				field.appendChild(input);
			}
		}
		var colors = availableColors(field), oldControl = input.closest('.contactpanda-settings-color-control');
		input.parentNode.removeChild(input);
		if (oldControl) oldControl.remove();
		field.querySelectorAll('.contactpanda-theme-color-palette').forEach(function (palette) { palette.remove(); });
		input.type = 'hidden';

		var picker = document.createElement('span'); picker.className = 'contactpanda-unified-color';
		var summary = document.createElement('span'); summary.className = 'contactpanda-unified-color-summary';
		var preview = document.createElement('button'); preview.type = 'button'; preview.className = 'contactpanda-unified-color-preview'; preview.title = i18n.chooseColor || 'Select color'; preview.setAttribute('aria-label', preview.title); preview.innerHTML = '<span></span>';
		var textInput = document.createElement('input'); textInput.type = 'text'; textInput.className = 'contactpanda-unified-color-text'; textInput.setAttribute('aria-label', i18n.colorValue || 'Color value');
		var themeButton = document.createElement('button'); themeButton.type = 'button'; themeButton.className = 'contactpanda-unified-color-theme'; themeButton.title = i18n.themeColors || 'Theme and template colors'; themeButton.setAttribute('aria-label', themeButton.title); themeButton.innerHTML = '<span class="dashicons dashicons-admin-site-alt3" aria-hidden="true"></span>';
		summary.appendChild(preview); summary.appendChild(textInput); summary.appendChild(themeButton); picker.appendChild(input); picker.appendChild(summary);

		var popover = document.createElement('span'); popover.className = 'contactpanda-unified-color-popover'; popover.hidden = true;
		var square = document.createElement('span'); square.className = 'contactpanda-unified-color-square'; square.tabIndex = 0; square.innerHTML = '<span class="contactpanda-unified-color-square-handle"></span>';
		var hue = document.createElement('span'); hue.className = 'contactpanda-unified-color-slider is-hue'; hue.tabIndex = 0; hue.innerHTML = '<span></span>';
		var alpha = document.createElement('span'); alpha.className = 'contactpanda-unified-color-slider is-alpha'; alpha.tabIndex = 0; alpha.innerHTML = '<span></span>';
		popover.appendChild(square); popover.appendChild(hue); popover.appendChild(alpha); picker.appendChild(popover);

		var palette = document.createElement('span'); palette.className = 'contactpanda-unified-color-palette'; palette.hidden = true;
		var paletteTitle = document.createElement('strong'); paletteTitle.textContent = i18n.themeColors || 'Theme and template colors'; palette.appendChild(paletteTitle);
		var paletteGrid = document.createElement('span'); paletteGrid.className = 'contactpanda-unified-color-palette-grid'; palette.appendChild(paletteGrid); picker.appendChild(palette);

		var initial = parseColor(input.dataset.contactpandaColorValue || input.getAttribute('value') || input.value) || {r: 255, g: 255, b: 255, a: 1}, state = rgbToHsv(initial), syncing = false;
		function closePanels() { popover.hidden = true; palette.hidden = true; picker.classList.remove('is-open'); }
		function render(updateInput, finalChange) {
			var rgb = hsvToRgb(state), serialized = colorString(rgb), solid = 'rgb(' + Math.round(rgb.r) + ', ' + Math.round(rgb.g) + ', ' + Math.round(rgb.b) + ')';
			preview.querySelector('span').style.backgroundColor = serialized; textInput.value = serialized;
			square.style.backgroundColor = 'hsl(' + Math.round(state.h) + ',100%,50%)';
			square.querySelector('span').style.left = (state.s * 100) + '%'; square.querySelector('span').style.top = ((1 - state.v) * 100) + '%';
			hue.querySelector('span').style.top = ((1 - state.h / 360) * 100) + '%';
			alpha.style.setProperty('--contactpanda-alpha-color', solid); alpha.querySelector('span').style.top = ((1 - state.a) * 100) + '%';
			input.value = serialized;
			if (updateInput) {
				syncing = true; input.dispatchEvent(new Event('input', {bubbles: true})); if (finalChange) input.dispatchEvent(new Event('change', {bubbles: true})); syncing = false;
			}
		}
		function useValue(value, finalChange) { var parsed = parseColor(value); if (!parsed) { textInput.setCustomValidity(i18n.invalidColor || 'Enter a valid HEX, RGB, or RGBA color.'); return false; } textInput.setCustomValidity(''); state = rgbToHsv(parsed); render(true, finalChange); return true; }
		bindPointer(square, function (x, y) { state.s = x; state.v = 1 - y; render(true, false); });
		bindPointer(hue, function (x, y) { state.h = (1 - y) * 360; render(true, false); });
		bindPointer(alpha, function (x, y) { state.a = 1 - y; render(true, false); });
		[square, hue, alpha].forEach(function (control) { control.addEventListener('pointerup', function () { render(true, true); }); });
		[square, hue, alpha].forEach(function (control) {
			control.addEventListener('keydown', function (event) {
				if (['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].indexOf(event.key) < 0) return;
				event.preventDefault();
				var step = event.shiftKey ? 0.1 : 0.02;
				if (control === square) {
					if (event.key === 'ArrowLeft') state.s = Math.max(0, state.s - step);
					if (event.key === 'ArrowRight') state.s = Math.min(1, state.s + step);
					if (event.key === 'ArrowUp') state.v = Math.min(1, state.v + step);
					if (event.key === 'ArrowDown') state.v = Math.max(0, state.v - step);
				} else if (control === hue) {
					state.h = (state.h + ((event.key === 'ArrowUp' || event.key === 'ArrowRight') ? 6 : -6) + 360) % 360;
				} else {
					state.a = Math.min(1, Math.max(0, state.a + ((event.key === 'ArrowUp' || event.key === 'ArrowRight') ? step : -step)));
				}
				render(true, true);
			});
		});
		preview.addEventListener('click', function (event) { event.preventDefault(); var opening = popover.hidden; closePanels(); popover.hidden = !opening; picker.classList.toggle('is-open', opening); });
		themeButton.addEventListener('click', function (event) { event.preventDefault(); var opening = palette.hidden; closePanels(); palette.hidden = !opening; picker.classList.toggle('is-open', opening); });
		textInput.addEventListener('change', function () { useValue(textInput.value, true); });
		textInput.addEventListener('keydown', function (event) { if (event.key === 'Enter') { event.preventDefault(); if (useValue(textInput.value, true)) closePanels(); } });
		input.addEventListener('input', function () { if (!syncing) { var parsed = parseColor(input.value); if (parsed) { state = rgbToHsv(parsed); render(false, false); } } });
		colors.forEach(function (color) { var swatch = document.createElement('button'); swatch.type = 'button'; swatch.className = 'contactpanda-unified-color-swatch'; swatch.dataset.color = color; swatch.title = color; swatch.setAttribute('aria-label', color); swatch.style.backgroundColor = color; swatch.addEventListener('click', function (event) { event.preventDefault(); useValue(color, true); closePanels(); }); paletteGrid.appendChild(swatch); });
		if (!colors.length) themeButton.hidden = true;
		field.appendChild(picker); render(false, false);
		var form = input.closest('form'); if (form) form.addEventListener('reset', function () { window.setTimeout(function () { useValue(input.defaultValue || '#ffffff', false); }, 0); });
	}

	function normaliseImage(item) {
		item = item || {};
		var url = item.url || item.source_url || '';
		var name = item.filename || item.name || (url ? decodeURIComponent(url.split('/').pop().split('?')[0]) : '');
		var mime = item.subtype || item.mime || item.mime_type || '';
		if (mime.indexOf('/') >= 0) mime = mime.split('/').pop();
		return {
			id: Number(item.id || item.attachment_id || 0), url: url, alt: item.alt || item.alt_text || item.title || '',
			name: name, width: Number(item.width || 0), height: Number(item.height || 0), format: String(mime || '').toUpperCase(),
			size: item.filesizeHumanReadable || item.filesize_human_readable || ''
		};
	}

	function imageMeta(item) {
		var parts = [];
		if (item.width && item.height) parts.push(item.width + ' × ' + item.height + ' px');
		if (item.format) parts.push(item.format);
		if (item.size) parts.push(item.size);
		return parts.join(' · ') || (i18n.imageFormats || 'JPG, PNG, WebP or SVG');
	}

	function createImagePicker(options) {
		options = options || {};
		var fileMode = !!options.fileMode;
		var root = document.createElement('div');
		root.className = 'contactpanda-image-picker';
		root.classList.toggle('is-multiple', !!options.multiple);
		var drop = document.createElement('button');
		drop.type = 'button';
		drop.className = 'contactpanda-image-picker-drop';
		drop.innerHTML = '<span class="dashicons dashicons-format-image" aria-hidden="true"></span><span class="contactpanda-image-picker-drop-copy"><strong></strong><small></small></span><span class="contactpanda-image-picker-preview"></span><span class="contactpanda-image-picker-status" aria-live="polite"></span>';
		drop.querySelector('strong').textContent = options.dropLabel || (fileMode ? (i18n.chooseFile || 'Select file or drop it here') : (i18n.dropImage || 'Click or drop an image here'));
		if (fileMode) drop.querySelector('.dashicons').className = 'dashicons dashicons-media-document';
		drop.querySelector('.contactpanda-image-picker-drop-copy small').textContent = fileMode ? (i18n.fileFormats || 'File types supported by WordPress') : (i18n.imageFormats || 'JPG, PNG, WebP or SVG');
		root.appendChild(drop);
		var emptyActions = document.createElement('span');
		emptyActions.className = 'contactpanda-image-picker-empty-actions';
		drop.appendChild(emptyActions);
		var selectedPanel = document.createElement('div');
		selectedPanel.className = 'contactpanda-image-picker-selected';
		selectedPanel.innerHTML = '<button type="button" class="contactpanda-image-picker-thumb" aria-label=""><img alt=""></button><span class="contactpanda-image-picker-details"><strong class="contactpanda-image-picker-name"></strong><small class="contactpanda-image-picker-meta"></small><span class="contactpanda-image-picker-actions"></span></span>';
		root.appendChild(selectedPanel);
		var actions = selectedPanel.querySelector('.contactpanda-image-picker-actions');
		function imageAction(action, icon, label, state) {
			var button = document.createElement('button');
			button.type = 'button';
			button.className = 'contactpanda-image-picker-action contactpanda-image-picker-' + action + (state ? ' is-' + state : '');
			button.title = label; button.setAttribute('aria-label', label);
			button.innerHTML = '<span class="dashicons dashicons-' + icon + '" aria-hidden="true"></span><span class="contactpanda-image-picker-action-label"></span>';
			button.querySelector('.contactpanda-image-picker-action-label').textContent = label;
			actions.appendChild(button);
			return button;
		}
		var replace = imageAction('replace', 'update', i18n.changeImage || 'Change image');
		var edit = imageAction('edit', 'edit', i18n.editImage || 'Edit image');
		var allowExternal = !fileMode && options.allowExternal !== false;
		var external = allowExternal ? imageAction('external', 'admin-links', i18n.externalImage || 'Link external image') : null;
		var emptyExternal = null;
		if (allowExternal) {
			emptyExternal = document.createElement('button');
			emptyExternal.type = 'button';
			emptyExternal.className = 'contactpanda-image-picker-action contactpanda-image-picker-external';
			emptyExternal.title = i18n.externalImage || 'Link external image';
			emptyExternal.setAttribute('aria-label', emptyExternal.title);
			emptyExternal.innerHTML = '<span class="dashicons dashicons-admin-links" aria-hidden="true"></span>';
			emptyActions.appendChild(emptyExternal);
		}
		var remove = imageAction('remove', 'trash', options.removeLabel || i18n.removeImage || 'Remove image', 'danger');
		var items = (options.items || (options.url ? [{id: options.attachmentId, url: options.url, alt: options.alt}] : [])).map(normaliseImage).filter(function (item) { return item.url; });

		function render(notify) {
			var preview = root.querySelector('.contactpanda-image-picker-preview');
			preview.replaceChildren();
			(fileMode ? [] : items).forEach(function (item) { var image = document.createElement('img'); image.src = item.url; image.alt = item.alt || ''; preview.appendChild(image); });
			root.classList.toggle('has-image', items.length > 0);
			drop.hidden = !options.multiple && items.length > 0;
			selectedPanel.hidden = options.multiple || !items.length;
			if (!options.multiple && items[0]) {
				var item = items[0], thumbnail = selectedPanel.querySelector('img');
				thumbnail.hidden = fileMode;
				if (!fileMode) { thumbnail.src = item.url; thumbnail.alt = item.alt || ''; }
				var fileIcon = selectedPanel.querySelector('.contactpanda-file-icon');
				if (fileMode && !fileIcon) { fileIcon = document.createElement('span'); fileIcon.className = 'dashicons dashicons-media-document contactpanda-file-icon'; fileIcon.setAttribute('aria-hidden', 'true'); selectedPanel.querySelector('.contactpanda-image-picker-thumb').appendChild(fileIcon); }
				selectedPanel.querySelector('.contactpanda-image-picker-thumb').setAttribute('aria-label', i18n.changeImage || 'Change image');
				selectedPanel.querySelector('.contactpanda-image-picker-name').textContent = item.name || i18n.selectedImage || 'Selected image';
				selectedPanel.querySelector('.contactpanda-image-picker-meta').textContent = imageMeta(item);
				edit.hidden = !item.id;
			}
			remove.hidden = !items.length;
			if (notify && typeof options.onChange === 'function') options.onChange(options.multiple ? items.slice() : (items[0] || null));
		}
		function select(selected) {
			selected = (Array.isArray(selected) ? selected : (selected ? [selected] : [])).map(normaliseImage).filter(function (item) { return item.url; });
			items = options.multiple ? selected.slice(0, options.max || 20) : selected.slice(0, 1);
			render(true);
		}
		function openMedia() {
			if (!window.wp || !wp.media) return;
			var config = {title: options.title || (fileMode ? (i18n.chooseFile || 'Select file') : (i18n.chooseImage || 'Select image')), button: {text: options.buttonLabel || i18n.useImage || 'Use selection'}, multiple: options.multiple ? 'add' : false};
			if (!fileMode) config.library = {type: 'image'};
			var frame = wp.media(config);
			frame.on('open', function () {
				if (!options.multiple && items[0] && items[0].id) frame.state().get('selection').add(wp.media.attachment(items[0].id));
			});
			frame.on('select', function () { select(frame.state().get('selection').toJSON()); });
			frame.open();
		}
		function upload(files) {
			var images = Array.prototype.filter.call(files || [], function (file) { return fileMode || /^image\//.test(file.type); }).slice(0, options.multiple ? (options.max || 20) : 1);
			if (!images.length || !window.wp || !wp.apiFetch) return;
			root.classList.add('is-uploading');
			root.querySelector('.contactpanda-image-picker-status').textContent = i18n.uploading || 'Uploading image…';
			Promise.all(images.map(function (file) { var data = new FormData(); data.append('file', file, file.name); return wp.apiFetch({path: '/wp/v2/media', method: 'POST', body: data}); }))
				.then(function (uploaded) { select(uploaded); root.querySelector('.contactpanda-image-picker-status').textContent = ''; }, function () { root.querySelector('.contactpanda-image-picker-status').textContent = i18n.uploadFailed || 'The image could not be uploaded.'; })
				.then(function () { root.classList.remove('is-uploading'); });
		}
		drop.addEventListener('click', openMedia);
		drop.addEventListener('dragover', function (event) { event.preventDefault(); root.classList.add('is-dragging'); });
		drop.addEventListener('dragleave', function () { root.classList.remove('is-dragging'); });
		drop.addEventListener('drop', function (event) { event.preventDefault(); root.classList.remove('is-dragging'); upload(event.dataTransfer.files); });
		selectedPanel.querySelector('.contactpanda-image-picker-thumb').addEventListener('click', openMedia);
		replace.addEventListener('click', openMedia);
		edit.addEventListener('click', function () { if (items[0] && items[0].id) window.open((window.ajaxurl || '').split('admin-ajax.php')[0] + 'post.php?post=' + items[0].id + '&action=edit', '_blank', 'noopener'); });
		function linkExternal(event) {
			if (event) { event.preventDefault(); event.stopPropagation(); }
			var value = window.prompt(i18n.externalImagePrompt || 'Enter the external image URL:', items[0] ? items[0].url : '');
			if (value && /^https?:\/\//i.test(value.trim())) select([{id: 0, url: value.trim(), alt: ''}]);
		}
		if (external) external.addEventListener('click', linkExternal);
		if (emptyExternal) emptyExternal.addEventListener('click', linkExternal);
		remove.addEventListener('click', function () { items = []; render(true); });
		root.contactPandaSetImages = select;
		root.contactPandaSetImage = function (item) { select(item ? [item] : []); };
		window.setTimeout(function () { render(false); }, 0);
		return root;
	}

	function enhanceStaticImagePicker(root) {
		if (!root || root.dataset.contactpandaImageReady === '1') return;
		root.dataset.contactpandaImageReady = '1';
		var url = root.querySelector('[data-contactpanda-image-url], input[type="url"]');
		var id = root.querySelector('[data-contactpanda-image-id], input[type="hidden"]');
		var legacyButton = root.querySelector('.contactpanda-newsletter-media-button');
		var legacyPreview = root.querySelector('.contactpanda-newsletter-logo-preview');
		var picker = createImagePicker({
			url: url ? url.value : '', attachmentId: id ? id.value : 0,
			onChange: function (image) {
				if (url) url.value = image ? image.url : '';
				if (id) id.value = image ? image.id : 0;
			}
		});
		root.insertBefore(picker, root.firstChild);
		if (url) { url.hidden = true; url.classList.add('contactpanda-image-picker-storage'); url.setAttribute('aria-hidden', 'true'); url.tabIndex = -1; }
		if (id) { id.hidden = true; id.classList.add('contactpanda-image-picker-storage'); }
		if (legacyButton) legacyButton.hidden = true;
		if (legacyPreview) legacyPreview.hidden = true;
		if (url) url.addEventListener('change', function () { picker.contactPandaSetImages(url.value ? [{id: id ? id.value : 0, url: url.value, alt: ''}] : []); });
	}

	function actionIcon(control) {
		if (!control || control.dataset.contactpandaActionReady === '1') return;
		var label = (control.getAttribute('aria-label') || control.getAttribute('title') || control.textContent || '').trim().replace(/\s+/g, ' ');
		var action = control.dataset.contactpandaAction || '';
		if (!action) actionIcons.some(function (entry) { if (entry[0].test(label)) { action = entry[1]; return true; } return false; });
		var definition = actionDefinitions[action];
		var visible = control.cloneNode(true);
		visible.querySelectorAll('.dashicons,.contactpanda-action-icon,.cpls-action-icon,.screen-reader-text').forEach(function (node) { node.remove(); });
		var labelled = control.dataset.contactpandaActionMode === 'labelled' || control.classList.contains('contactpanda-action-labelled') || (control.dataset.contactpandaActionMode !== 'icon' && !control.matches('.contactpanda-list-action,.cpls-action') && visible.textContent.trim() !== '');
		if (!definition) {
			if (control.querySelector('.dashicons')) { control.dataset.contactpandaActionReady = '1'; control.classList.add(labelled ? 'contactpanda-action-labelled' : 'contactpanda-list-action'); if (label) control.setAttribute('aria-label', label); control.querySelectorAll('.dashicons').forEach(function (icon) { icon.setAttribute('aria-hidden', 'true'); }); }
			return;
		}
		if (!label) label = action.replace(/([A-Z])/g, ' $1').replace(/^./, function (letter) { return letter.toUpperCase(); });
		control.dataset.contactpandaActionReady = '1';
		control.dataset.contactpandaAction = action;
		control.classList.add(labelled ? 'contactpanda-action-labelled' : 'contactpanda-list-action', 'contactpanda-action-' + action.replace(/([A-Z])/g, '-$1').toLowerCase());
		if (definition.state) control.classList.add('is-' + definition.state);
		if (control.disabled || control.getAttribute('aria-disabled') === 'true') control.classList.add('is-disabled');
		control.title = label;
		control.setAttribute('aria-label', label);
		var glyph = document.createElement('span');
		glyph.className = definition.customIcon ? 'contactpanda-action-icon contactpanda-action-icon-' + definition.customIcon : 'dashicons dashicons-' + definition.icon;
		glyph.setAttribute('aria-hidden', 'true');
		if (labelled) { control.querySelectorAll('.dashicons,.contactpanda-action-icon,.cpls-action-icon').forEach(function (node) { node.remove(); }); control.prepend(glyph); return; }
		control.replaceChildren(); control.appendChild(glyph);
		var text = document.createElement('span'); text.className = 'screen-reader-text'; text.textContent = label; control.appendChild(text);
	}

	function enhanceListActions(root) {
		findAll(root, 'table').forEach(function (table) {
			var actionColumns = [];
			var occupied = [];
			table.querySelectorAll('thead tr').forEach(function (row, rowIndex) {
				var column = 0;
				Array.from(row.children).forEach(function (heading) {
					while ((occupied[column] || 0) > rowIndex) column++;
					var width = Number(heading.colSpan) || 1;
					if (/^(actions|aktionen)$/i.test(heading.textContent.trim()) || /(column-actions|actions?-column)/.test(heading.className)) actionColumns.push(column);
					for (var offset = 0; offset < width; offset++) occupied[column + offset] = rowIndex + (Number(heading.rowSpan) || 1);
					column += width;
				});
			});
			table.querySelectorAll('tbody tr').forEach(function (row) {
				actionColumns.forEach(function (index) { var cell = row.children[index]; if (cell) cell.querySelectorAll('a,button').forEach(actionIcon); });
			});
		});
		findAll(root, [
			'[data-contactpanda-action]', '.contactpanda-list-action', '.contactpanda-person-row-action', '.contactpanda-inline-row-action', '.contactpanda-eye-action', '.contactpanda-trash-row-action',
			'.contactpanda-form-actions a', '.contactpanda-form-actions button', '.contactpanda-order-actions a', '.contactpanda-order-actions button',
			'.contactpanda-template-actions a', '.contactpanda-template-actions button', '.contactpanda-gallery-row-actions a', '.contactpanda-gallery-row-actions button',
			'.contactpanda-system-email-block-actions a', '.contactpanda-system-email-block-actions button', '.contactpanda-system-email-add a', '.contactpanda-system-email-add button',
			'.cpb-actions a', '.cpb-actions button', '.cpb-card-actions a', '.cpb-card-actions button',
			'.pandawoo-template-actions a', '.pandawoo-template-actions button', '.pandawoo-inline-actions a', '.pandawoo-inline-actions button',
			'.pandawoo-pro-license-actions a', '.pandawoo-pro-license-actions button', '.panda-seasons-actions a', '.panda-seasons-actions button',
			'.panda-seasons-composer__door-actions button', '.panda-seasons-composer__layer-actions button',
			'.cpls-actions a', '.cpls-actions button', '.cpls-row-actions a', '.cpls-row-actions button'
		].join(',')).forEach(actionIcon);
	}

	function largeActionIcon(control) {
		if (!control || control.dataset.contactpandaLargeActionReady === '1') return;
		if (control.matches('.button-small,.button-link,.button-link-delete,[data-contactpanda-action-mode="icon"]') || control.closest(compactActionContainers + ',.wp-picker-container,.contactpanda-unified-color,.media-modal,.wp-editor-wrap,.mce-toolbar,.components-toolbar')) return;
		var label = (control.value || control.textContent || control.getAttribute('aria-label') || control.getAttribute('title') || '').trim().replace(/\s+/g, ' ');
		var action = control.dataset.contactpandaAction || '';
		if (!action && /^(?:back(?: to)?|return(?: to)?|zurück|zurueck)(?:\s|$)/i.test(label)) action = 'back';
		if (!action) largeActionSuffixes.some(function (entry) { if (entry[0].test(label)) { action = entry[1]; return true; } return false; });
		if (!action) largeActionLabels.some(function (entry) { if (entry[0].test(label)) { action = entry[1]; return true; } return false; });
		var definition = actionDefinitions[action];
		if (!label) return;
		if (definition) control = proxyInputAction(control, label);
		control.dataset.contactpandaLargeActionReady = '1';
		control.classList.add('contactpanda-large-action');
		if (action === 'delete' || action === 'deactivate') control.classList.add('is-danger');
		if (control.classList.contains('button-primary') || action === 'publish') control.classList.add('is-primary');
		if (control.disabled || control.getAttribute('aria-disabled') === 'true') control.classList.add('is-disabled');
		if (!definition || control.querySelector('svg')) return;
		control.dataset.contactpandaActionReady = '1';
		control.dataset.contactpandaAction = action;
		control.classList.add('contactpanda-large-action-' + action.replace(/([A-Z])/g, '-$1').toLowerCase());
		Array.prototype.slice.call(control.children).forEach(function (child) {
			if (child.classList.contains('dashicons') || child.classList.contains('contactpanda-action-icon')) child.remove();
		});
		var glyph = document.createElement('span');
		glyph.className = definition.customIcon ? 'contactpanda-action-icon contactpanda-action-icon-' + definition.customIcon : 'dashicons dashicons-' + definition.icon;
		glyph.setAttribute('aria-hidden', 'true');
		control.prepend(glyph);
	}

	function enhanceLargeActions(root) {
		findAll(root, 'button.button,a.button,summary.button,input.button,input[type="submit"],input[type="button"],input[type="reset"],button[type="submit"],[data-contactpanda-action-mode="labelled"]').forEach(function (control) {
			largeActionIcon(control);
		});
	}

	/** A reusable staged tag chooser, without automatic writes to a contact. */
	function enhanceTagSelect(root) {
		if(root.dataset.tagSelectReady)return;root.dataset.tagSelectReady='1';
		var choices=[];try{choices=JSON.parse(root.dataset.choices||'[]');}catch(ignore){}
		var tokens=document.createElement('div'),input=document.createElement('input'),panel=document.createElement('div'),names=[];
		tokens.className='contactpanda-token-control';input.className='contactpanda-token-input';input.type='text';input.autocomplete='off';input.placeholder=root.dataset.placeholder;input.setAttribute('aria-label',root.dataset.placeholder);input.setAttribute('role','combobox');input.setAttribute('aria-expanded','false');panel.className='contactpanda-token-suggestions';panel.hidden=true;panel.setAttribute('role','listbox');tokens.append(input);root.append(tokens,panel);
		function add(name){name=String(name).trim();if(!name)return;if(names.some(n=>n.toLocaleLowerCase()===name.toLocaleLowerCase())){input.value='';show();return;}names.push(name);var token=document.createElement('span'),hidden=document.createElement('input'),remove=document.createElement('button');token.className='contactpanda-tag';token.append(document.createTextNode(name));hidden.type='hidden';hidden.name=root.dataset.name;hidden.value=name;remove.type='button';remove.className='button-link';remove.textContent='×';remove.setAttribute('aria-label',name+' ×');remove.onclick=function(){names=names.filter(n=>n!==name);token.remove();show();};token.append(hidden,remove);tokens.insertBefore(token,input);input.value='';show();input.focus();}
		function show(){panel.replaceChildren();var query=input.value.trim().toLocaleLowerCase();choices.filter(n=>!names.includes(n)&&n.toLocaleLowerCase().includes(query)).slice(0,40).forEach(function(name){var option=document.createElement('button');option.type='button';option.className='button-link contactpanda-token-suggestion';option.setAttribute('role','option');option.textContent=name;option.onclick=function(){add(name);};panel.append(option);});if(query&&!choices.some(n=>n.toLocaleLowerCase()===query)){var create=document.createElement('button');create.type='button';create.className='button-link contactpanda-token-suggestion';create.textContent='+ '+root.dataset.create+': '+input.value.trim();create.onclick=function(){add(input.value);};panel.append(create);}panel.hidden=false;input.setAttribute('aria-expanded','true');}
		input.onfocus=show;input.oninput=show;input.onkeydown=function(e){if(e.key==='Enter'&&input.value.trim()){e.preventDefault();add(input.value);}if(e.key==='Escape'){panel.hidden=true;input.setAttribute('aria-expanded','false');}};root.addEventListener('focusout',function(){setTimeout(function(){if(!root.contains(document.activeElement)){panel.hidden=true;input.setAttribute('aria-expanded','false');}},0);});root.closest('form')?.addEventListener('submit',function(){if(input.value.trim())add(input.value);});
	}


	function enhanceSearchableSelect(select) {
		if(select.dataset.pandaSearchReady)return;select.dataset.pandaSearchReady='1';
		var root=document.createElement('div'),button=document.createElement('button'),panel=document.createElement('div'),search=document.createElement('input'),list=document.createElement('div'),options=Array.from(select.options),active=-1,shown=[];
		root.className='contactpanda-search-select';button.type='button';button.className='contactpanda-search-select-trigger';button.setAttribute('aria-haspopup','listbox');button.setAttribute('aria-expanded','false');panel.className='contactpanda-search-select-panel';panel.hidden=true;search.type='search';search.setAttribute('aria-label',select.dataset.searchLabel||'Search');search.placeholder=select.dataset.searchLabel||'Search';list.setAttribute('role','listbox');
		function sync(){button.textContent=select.selectedOptions[0]?.textContent||'';button.disabled=select.disabled;}
		function close(){panel.hidden=true;button.setAttribute('aria-expanded','false');active=-1;}
		function draw(){list.replaceChildren();shown=options.filter(o=>!o.disabled&&o.textContent.toLocaleLowerCase().includes(search.value.toLocaleLowerCase()));shown.forEach(function(o,i){var choice=document.createElement('button');choice.type='button';choice.className='button-link';choice.setAttribute('role','option');choice.setAttribute('aria-selected',String(o.selected));choice.textContent=o.textContent;choice.onclick=function(e){e.preventDefault();select.value=o.value;select.dispatchEvent(new Event('change',{bubbles:true}));close();button.focus();};list.append(choice);});active=-1;}
		button.onclick=function(e){e.preventDefault();if(!panel.hidden){close();return;}panel.hidden=false;button.setAttribute('aria-expanded','true');search.value='';draw();search.focus();};
		panel.onkeydown=function(e){var items=Array.from(list.children);if(e.key==='Escape'){e.preventDefault();close();button.focus();}else if(e.key==='ArrowDown'||e.key==='ArrowUp'){e.preventDefault();active=Math.max(0,Math.min(items.length-1,active+(e.key==='ArrowDown'?1:-1)));items[active]?.focus();}else if(e.key==='Enter'&&e.target===search){e.preventDefault();items[0]?.click();}};
		search.oninput=draw;select.onchange=sync;select.hidden=true;select.after(root);root.append(button,panel);panel.append(search,list);sync();
		document.addEventListener('pointerdown',function(e){if(!root.contains(e.target))close();});
	}
	function enhance(root) {
		findAll(root,'[data-panda-tag-select]').forEach(enhanceTagSelect);
		findAll(root,'select[data-panda-searchable]').forEach(enhanceSearchableSelect);
		findAll(root, '.contactpanda-wrap input[type="color"],.contactpanda-business-wrap input[type="color"],.pandawoo-pro-admin input[type="color"],.pandawoo-page input[type="color"],.pandawoo-page-shell input[type="color"],.panda-seasons-dashboard input[type="color"],.panda-seasons-free-dashboard input[type="color"],.panda-seasons-templates-page input[type="color"],.panda-seasons-preview-page input[type="color"],.cpls-wrap input[type="color"]').forEach(enhanceColorPicker);
		findAll(root, '.contactpanda-image-picker-source,.contactpanda-newsletter-logo-setting').forEach(enhanceStaticImagePicker);
		enhanceLargeActions(root || document);
		enhanceListActions(root || document);
	}

	window.ContactPandaAdminUI = Object.assign(window.ContactPandaAdminUI || {}, {enhance: enhance, enhanceColorPicker: enhanceColorPicker, createImagePicker: createImagePicker, createFilePicker: function(options) { return createImagePicker(Object.assign({},options,{fileMode:true})); }, enhanceListActions: enhanceListActions, enhanceLargeActions: enhanceLargeActions, actionDefinitions: actionDefinitions});
	document.addEventListener('pointerdown', function (event) {
		document.querySelectorAll('.contactpanda-unified-color.is-open').forEach(function (picker) {
			if (picker.contains(event.target)) return;
			picker.classList.remove('is-open');
			picker.querySelectorAll('.contactpanda-unified-color-popover,.contactpanda-unified-color-palette').forEach(function (panel) { panel.hidden = true; });
		});
	});
	document.addEventListener('keydown', function (event) {
		if (event.key !== 'Escape') return;
		document.querySelectorAll('.contactpanda-unified-color.is-open').forEach(function (picker) {
			picker.classList.remove('is-open');
			picker.querySelectorAll('.contactpanda-unified-color-popover,.contactpanda-unified-color-palette').forEach(function (panel) { panel.hidden = true; });
		});
	});
	document.addEventListener('DOMContentLoaded', function () {
		enhance(document);
		var observer = new MutationObserver(function (records) { records.forEach(function (record) { record.addedNodes.forEach(function (node) { if (node.nodeType === 1) enhance(node); }); }); });
		observer.observe(document.body, {childList: true, subtree: true});
	});
}());
