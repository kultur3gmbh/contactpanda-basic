(function () {
	'use strict';

	var VERSION = '1.0.0';
	var existing = window.PandaEditorUI || {};
	var registry = existing.registry || {};

	function registerBlock(type, definition) {
		if (!type || !definition) return;
		registry[type] = Object.assign({}, registry[type] || {}, definition, {type: type});
	}

	function registerBlocks(definitions) {
		Object.keys(definitions || {}).forEach(function (type) { registerBlock(type, definitions[type]); });
	}

	function blocks(context) {
		return Object.keys(registry).map(function (type) { return registry[type]; }).filter(function (definition) {
			return !definition.contexts || !definition.contexts.length || definition.contexts.indexOf(context) >= 0;
		});
	}

	function switchPanel(editor, target) {
		var canvas = editor.querySelector('[data-panda-editor-canvas]');
		var canvasScroll = canvas ? canvas.scrollTop : 0;
		var pageScroll = window.scrollY;
		editor.querySelectorAll('[data-panda-editor-tab]').forEach(function (tab) {
			var active = tab.dataset.pandaEditorTab === target;
			tab.classList.toggle('is-active', active);
			tab.setAttribute('aria-selected', active ? 'true' : 'false');
		});
		editor.querySelectorAll('[data-panda-editor-panel]').forEach(function (panel) {
			panel.hidden = panel.dataset.pandaEditorPanel !== target;
		});
		window.requestAnimationFrame(function () {
			if (canvas) canvas.scrollTop = canvasScroll;
			window.scrollTo(window.scrollX, pageScroll);
		});
	}

	function enhance(editor) {
		if (!editor || editor.dataset.pandaEditorReady === '1') return;
		editor.dataset.pandaEditorReady = '1';
		editor.querySelectorAll('[data-panda-editor-tab]').forEach(function (tab) {
			tab.addEventListener('click', function () { switchPanel(editor, tab.dataset.pandaEditorTab); });
		});
		editor.addEventListener('click', function (event) {
			if (!event.target.closest('[data-panda-editor-element], .contactpanda-flow-node, .pandawoo-layout-block, .panda-seasons-editor-element')) return;
			switchPanel(editor, 'edit');
		});
		var initial = editor.querySelector('[data-panda-editor-tab].is-active');
		switchPanel(editor, initial ? initial.dataset.pandaEditorTab : 'elements');
	}

	function enhanceAll(root) {
		(root || document).querySelectorAll('[data-panda-editor]').forEach(enhance);
	}

	registerBlocks({
		text: {label: 'Text', icon: 'editor-alignleft', group: 'content'},
		heading: {label: 'Heading', icon: 'heading', group: 'content'},
		image: {label: 'Image', icon: 'format-image', group: 'media'},
		button: {label: 'Button', icon: 'button', group: 'content'},
		divider: {label: 'Divider', icon: 'minus', group: 'layout'},
		spacer: {label: 'Spacer', icon: 'image-flip-vertical', group: 'layout'},
		columns: {label: 'Columns', icon: 'columns', group: 'layout'},
		container: {label: 'Container', icon: 'layout', group: 'layout'}
	});

	window.PandaEditorUI = Object.assign(existing, {
		version: VERSION,
		registry: registry,
		registerBlock: registerBlock,
		registerBlocks: registerBlocks,
		blocks: blocks,
		enhance: enhance,
		enhanceAll: enhanceAll,
		switchPanel: switchPanel
	});

	document.addEventListener('DOMContentLoaded', function () {
		enhanceAll(document);
		new MutationObserver(function (records) {
			records.forEach(function (record) { record.addedNodes.forEach(function (node) { if (node.nodeType === 1) enhanceAll(node); }); });
		}).observe(document.body, {childList: true, subtree: true});
	});
}());
