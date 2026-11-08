(function () {
	'use strict';
	var config = window.CONTACTPANDA_NEWSLETTER || {i18n: {}};
	var i18n = config.i18n || {};
	var productFilters = config.productFilters || {categories: [], producers: [], products: []};
	var eventConfig = config.eventFilters || {events: [], categories: []};
	var eventFilters = Array.isArray(eventConfig) ? eventConfig : (eventConfig.events || []);
	var eventCategories = Array.isArray(eventConfig.categories) ? eventConfig.categories : [];
	var postFilters = config.postFilters || {types: []};
	var productPreviewCache = {};
	var productPreviewTimers = {};
	document.querySelectorAll('.contactpanda-archive-template').forEach(function (archiveForm) {
		archiveForm.addEventListener('submit', function (event) { if (!window.confirm(i18n.confirmArchive)) event.preventDefault(); });
	});
	var form = document.getElementById('contactpanda-newsletter-editor');
	if (!form) return;
	var container = document.getElementById('contactpanda-newsletter-blocks');
	var inspector = document.getElementById('contactpanda-block-inspector');
	var jsonField = document.getElementById('contactpanda-content-json');
	var emptyMessage = document.getElementById('contactpanda-no-blocks');
	var blocks = [], selectedId = '', draggedId = '', editorId = 'contactpanda-block-richtext', syncingInline = false, inspectorTab = 'content';
	try { blocks = JSON.parse(jsonField.value || '[]'); } catch (error) { blocks = []; }
	if (!Array.isArray(blocks)) blocks = [];
	function uid() { return 'block-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 8); }
	function label(type) { return i18n[type] || type; }
	function boxValues(value, defaults) {
		value = value && typeof value === 'object' ? value : {};
		return ['top', 'right', 'bottom', 'left'].reduce(function (result, side) { result[side] = Math.max(0, Math.min(200, Number(value[side] !== undefined ? value[side] : defaults[side]) || 0)); return result; }, {});
	}
	function radiusValues(value) {
		value = value && typeof value === 'object' ? value : {};
		return ['top_left', 'top_right', 'bottom_right', 'bottom_left'].reduce(function (result, corner) { result[corner] = Math.max(0, Math.min(200, Number(value[corner]) || 0)); return result; }, {});
	}
	function captionImage(image) {
		image = image && typeof image === 'object' ? image : {};
		image.caption = image.caption || '';
		image.caption_position = ['top', 'center', 'bottom', 'outside'].indexOf(image.caption_position) >= 0 ? image.caption_position : 'bottom';
		image.caption_background = image.caption_background || 'rgba(0,0,0,0.65)';
		image.caption_color = image.caption_color || '#ffffff';
		image.caption_size = Math.max(10, Math.min(36, Number(image.caption_size) || 14));
		image.caption_weight = ['400', '600', '700'].indexOf(String(image.caption_weight)) >= 0 ? String(image.caption_weight) : '600';
		image.caption_uppercase = !!image.caption_uppercase;
		image.caption_radius = Math.max(0, Math.min(40, Number(image.caption_radius) || 4));
		return image;
	}
	function contactHref(block) {
		var value = String(block.value || '').trim();
		if (block.type === 'phone_button') return 'tel:' + value.replace(/[^+0-9]/g, '');
		if (block.type === 'email_button') return 'mailto:' + value;
		var number = value.replace(/[^0-9]/g, '');
		return 'https://wa.me/' + number + (block.message ? '?text=' + encodeURIComponent(block.message) : '');
	}
	function captionMarkup(image) {
		if (!image.caption) return '';
		return '<span class="contactpanda-image-caption is-' + image.caption_position + '" style="background:' + escapeHtml(image.caption_background) + ';color:' + escapeHtml(image.caption_color) + ';font-size:' + Number(image.caption_size) + 'px;font-weight:' + escapeHtml(image.caption_weight) + ';text-transform:' + (image.caption_uppercase ? 'uppercase' : 'none') + ';border-radius:' + Number(image.caption_radius) + 'px">' + escapeHtml(image.caption) + '</span>';
	}
	function normalize(block) {
		block.id = block.id || uid();
		block.alignment = ['left', 'center', 'right'].indexOf(block.alignment) >= 0 ? block.alignment : 'left';
		if (['products', 'events', 'posts'].indexOf(block.type) >= 0) block.image_ratio = ['original', '1:1', '4:5', '4:3', '16:9'].indexOf(block.image_ratio) >= 0 ? block.image_ratio : '1:1';
		if (block.type === 'image') { block.width_unit = block.width_unit === 'px' ? 'px' : '%'; block.width = Number(block.width || (block.width_unit === 'px' ? 600 : 100)); captionImage(block); }
		if (block.type === 'hero') { block.image_url = block.image_url || ''; block.image_id = Number(block.image_id || 0); block.heading = block.heading || i18n.heroHeading; block.content = block.content || ''; block.button_label = block.button_label || ''; block.button_url = block.button_url || ''; block.overlay_color = block.overlay_color || '#182433'; block.text_color = block.text_color || '#ffffff'; block.overlay_opacity = Math.max(0, Math.min(90, Number(block.overlay_opacity || 45))); block.alignment = block.alignment || 'center'; }
		if (block.type === 'social') { block.label = block.label || ''; block.icon_color = block.icon_color || '#4253b5'; block.icon_background = block.icon_background || '#ffffff'; block.border_color = block.border_color || 'transparent'; block.border_radius = Math.max(0, Math.min(50, Number(block.border_radius) || 50)); block.links = block.links && typeof block.links === 'object' ? block.links : {}; block.alignment = block.alignment || 'center'; }
		if (block.type === 'video') { block.url = block.url || ''; block.thumbnail_url = block.thumbnail_url || ''; block.thumbnail_id = Number(block.thumbnail_id || 0); block.title = block.title || ''; block.show_button = block.show_button !== false; block.button_label = block.button_label || i18n.videoButton; block.alignment = block.alignment || 'center'; block.show_provider_badge = block.show_provider_badge !== false; block.badge_position = block.badge_position === 'top' ? 'top' : 'bottom'; }
		if (block.type === 'gallery') { block.images = Array.isArray(block.images) ? block.images.slice(0, 6).map(captionImage) : []; block.columns = Math.max(1, Math.min(3, Number(block.columns) || 3)); block.tablet_columns = Math.max(1, Math.min(3, Number(block.tablet_columns) || 2)); block.mobile_columns = Math.max(1, Math.min(3, Number(block.mobile_columns) || 1)); block.layout = block.layout === 'masonry' ? 'masonry' : 'grid'; block.image_format = block.image_format === 'original' ? 'original' : 'fixed'; block.image_ratio = ['1:1', '4:5', '4:3', '16:9'].indexOf(block.image_ratio) >= 0 ? block.image_ratio : '1:1'; block.image_fit = block.image_fit === 'contain' ? 'contain' : 'cover'; }
		if (block.type === 'columns') { block.columns = Math.max(2, Math.min(3, Number(block.columns) || 2)); block.children = Array.isArray(block.children) ? block.children.slice(0, block.columns) : []; while (block.children.length < block.columns) block.children.push([]); block.children = block.children.map(function (column) { return Array.isArray(column) ? column.filter(function (child) { return child && child.type !== 'columns'; }).map(normalize) : []; }); }
		if (['phone_button', 'email_button', 'whatsapp_button'].indexOf(block.type) >= 0) { block.label = block.label || label(block.type); block.value = block.value || ''; block.message = block.message || ''; block.button_color = block.button_color || ''; block.text_color = block.text_color || ''; block.full_width = !!block.full_width; }
		if (block.type === 'spacer') block.height = Number(block.height || 24);
		block.background_color = block.background_color || '';
		block.padding = boxValues(block.padding, {top: 0, right: 0, bottom: 0, left: 0});
		block.margin = boxValues(block.margin, {top: 0, right: 0, bottom: 20, left: 0});
		block.padding_linked = block.padding_linked !== false;
		block.margin_linked = block.margin_linked !== false;
		block.border_radius = radiusValues(block.border_radius);
		block.radius_linked = block.radius_linked !== false;
		block.radius_unit = block.radius_unit === '%' ? '%' : 'px';
		if (block.type === 'heading') { block.level = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'].indexOf(block.level) >= 0 ? block.level : 'h2'; block.text_color = block.text_color || ''; }
		if (block.type === 'divider') { block.line_style = ['solid', 'double', 'dotted'].indexOf(block.line_style) >= 0 ? block.line_style : 'solid'; block.line_width = Math.max(1, Math.min(12, Number(block.line_width) || 1)); block.line_color = block.line_color || '#d9dee4'; }
		if (block.type === 'button') { block.button_color = block.button_color || ''; block.border_color = block.border_color || ''; block.text_color = block.text_color || ''; block.full_width = !!block.full_width; block.uppercase = !!block.uppercase; }
		if (block.type === 'products') { block.source = block.source || 'latest'; block.count = Math.max(1, Math.min(6, Number(block.count) || 2)); block.columns = Math.max(1, Math.min(3, Number(block.columns) || 2)); block.product_ids = Array.isArray(block.product_ids) ? block.product_ids : []; block.show_price = block.show_price !== false; block.show_categories = !!block.show_categories; block.show_brands = !!block.show_brands; }
		if (block.type === 'events') { block.event_ids = Array.isArray(block.event_ids) ? block.event_ids : []; var legacyEventSource = block.source || ''; block.source = ['manual','upcoming','current','past','all'].indexOf(legacyEventSource) >= 0 ? legacyEventSource : (block.event_ids.length ? 'manual' : 'upcoming'); block.category_id = Number(block.category_id || 0); block.featured_only = !!block.featured_only || legacyEventSource === 'featured'; block.event_order = block.event_order === 'desc' ? 'desc' : 'asc'; block.count = Math.max(1, Math.min(6, Number(block.count) || 2)); block.columns = Math.max(1, Math.min(3, Number(block.columns) || 2)); block.show_image = block.show_image !== false; block.show_date = block.show_date !== false; block.show_location = !!block.show_location; block.text_alignment = block.text_alignment === 'center' ? 'center' : 'left'; block.image_format = block.image_format === 'original' ? 'original' : 'fixed'; block.image_fit = block.image_fit === 'contain' ? 'contain' : 'cover'; }
		if (block.type === 'posts') {
			block.post_type = block.post_type || ((postFilters.types || [])[0] || {id: 'post'}).id; var normalizedPostType = selectedPostType(block); block.post_taxonomy = block.post_taxonomy || normalizedPostType.taxonomy || ''; block.source = block.source || 'latest'; block.count = Math.max(1, Math.min(6, Number(block.count) || 2)); block.columns = Math.max(1, Math.min(3, Number(block.columns) || 2)); block.post_ids = Array.isArray(block.post_ids) ? block.post_ids : [];
			block.show_image = block.show_image !== false; block.show_title = block.show_title !== false; block.show_excerpt = block.show_excerpt !== false;
			if (!block.show_image && !block.show_title && !block.show_excerpt) block.show_title = true;
		}
		return block;
	}
	blocks = blocks.map(normalize);
	function defaultBlock(type) {
		var block = {id: uid(), type: type, alignment: 'left', padding: {top: 0, right: 0, bottom: 0, left: 0}, margin: {top: 0, right: 0, bottom: 20, left: 0}, padding_linked: true, margin_linked: true, border_radius: {top_left: 0, top_right: 0, bottom_right: 0, bottom_left: 0}, radius_linked: true, radius_unit: 'px'};
		block.background_color = '';
		if (type === 'heading') { block.content = i18n.heading; block.level = 'h2'; block.text_color = ''; }
		if (type === 'text') block.content = '<p>' + i18n.text + '</p>';
		if (type === 'image') { block.url = ''; block.alt = ''; block.attachment_id = 0; block.width = 100; block.width_unit = '%'; block.alignment = 'center'; captionImage(block); }
		if (type === 'hero') { block.image_url = ''; block.image_id = 0; block.heading = i18n.heroHeading; block.content = ''; block.button_label = ''; block.button_url = ''; block.overlay_color = '#182433'; block.text_color = '#ffffff'; block.overlay_opacity = 45; block.alignment = 'center'; }
		if (type === 'social') { block.label = ''; block.icon_color = '#4253b5'; block.icon_background = '#ffffff'; block.border_color = 'transparent'; block.border_radius = 50; block.links = {}; block.alignment = 'center'; }
		if (type === 'video') { block.url = ''; block.thumbnail_url = ''; block.thumbnail_id = 0; block.title = ''; block.show_button = true; block.button_label = i18n.videoButton; block.alignment = 'center'; block.show_provider_badge = true; block.badge_position = 'bottom'; }
		if (type === 'gallery') { block.images = []; block.columns = 3; block.tablet_columns = 2; block.mobile_columns = 1; block.layout = 'grid'; block.image_format = 'fixed'; block.image_ratio = '1:1'; block.image_fit = 'cover'; block.alignment = 'center'; }
		if (type === 'divider') { block.line_style = 'solid'; block.line_width = 1; block.line_color = '#d9dee4'; }
		if (type === 'columns') { block.columns = 2; block.children = [[], []]; }
		if (['phone_button', 'email_button', 'whatsapp_button'].indexOf(type) >= 0) { block.label = label(type); block.value = ''; block.message = ''; block.button_color = ''; block.text_color = ''; block.full_width = false; block.alignment = 'center'; }
		if (type === 'button') { block.label = i18n.buttonLabel; block.url = ''; block.button_color = ''; block.border_color = ''; block.text_color = ''; block.full_width = false; block.uppercase = false; }
		if (type === 'spacer') block.height = 24;
		if (type === 'products') { block.source = 'latest'; block.count = 2; block.columns = 2; block.image_ratio = '1:1'; block.category_id = 0; block.producer_taxonomy = ''; block.producer_term_id = 0; block.product_ids = []; block.show_price = true; block.show_categories = false; block.show_brands = false; }
		if (type === 'events') { block.source = 'upcoming'; block.event_ids = []; block.category_id = 0; block.featured_only = false; block.event_order = 'asc'; block.count = 2; block.columns = 2; block.show_image = true; block.show_date = true; block.show_location = false; block.text_alignment = 'left'; block.image_format = 'fixed'; block.image_fit = 'cover'; block.image_ratio = '1:1'; }
		if (type === 'posts') { var firstPostType = (postFilters.types || [])[0] || {id: 'post', taxonomy: ''}; block.post_type = firstPostType.id; block.post_taxonomy = firstPostType.taxonomy || ''; block.source = 'latest'; block.count = 2; block.columns = 2; block.image_ratio = '1:1'; block.category_id = 0; block.post_ids = []; block.show_image = true; block.show_title = true; block.show_excerpt = true; }
		return block;
	}
	function sync() { jsonField.value = JSON.stringify(blocks); }
	function locateBlock(id, list) {
		list = list || blocks;
		for (var index = 0; index < list.length; index += 1) {
			if (list[index].id === id) return {block: list[index], list: list, index: index};
			if (list[index].type === 'columns') for (var column = 0; column < list[index].children.length; column += 1) { var found = locateBlock(id, list[index].children[column]); if (found) return found; }
		}
		return null;
	}
	function selectedBlock() { var located = locateBlock(selectedId); return located ? located.block : null; }
	function takeBlock(id) { var located = locateBlock(id); return located ? located.list.splice(located.index, 1)[0] : null; }
	function escapeHtml(value) { var div = document.createElement('div'); div.textContent = value || ''; return div.innerHTML; }
	function hexToRgba(value, opacity) { var hex = String(value || '#182433').replace('#', ''); if (hex.length === 3) hex = hex.split('').map(function (part) { return part + part; }).join(''); var number = parseInt(hex, 16); if (!Number.isFinite(number)) number = 0x182433; return 'rgba(' + ((number >> 16) & 255) + ',' + ((number >> 8) & 255) + ',' + (number & 255) + ',' + opacity + ')'; }
	function safeRich(value) {
		var template = document.createElement('template'); template.innerHTML = value || '';
		template.content.querySelectorAll('script,style,iframe,object,embed').forEach(function (node) { node.remove(); });
		template.content.querySelectorAll('*').forEach(function (node) { Array.prototype.slice.call(node.attributes).forEach(function (attribute) { if (/^on/i.test(attribute.name)) node.removeAttribute(attribute.name); }); });
		return template.innerHTML;
	}
	function imageRatio(value) { return value === '4:5' ? '4/5' : value === '4:3' ? '4/3' : value === '16:9' ? '16/9' : value === 'original' ? 'auto' : '1/1'; }
	function youtubeThumbnail(value) { var match = String(value || '').match(/(?:youtu\.be\/|youtube(?:-nocookie)?\.com\/(?:watch\?v=|embed\/|shorts\/))([A-Za-z0-9_-]{6,})/); return match ? 'https://i.ytimg.com/vi/' + match[1] + '/hqdefault.jpg' : ''; }
	function videoProvider(value) { value = String(value || ''); if (/youtu(?:\.be|be\.com)/i.test(value)) return 'YouTube'; if (/vimeo\.com/i.test(value)) return 'Vimeo'; return ''; }
	function galleryPreview(block) {
		if (!block.images.length) return '<div class="contactpanda-image-placeholder"><span class="dashicons dashicons-format-gallery"></span>' + escapeHtml(i18n.galleryEmpty) + '</div>';
		var style = block.layout === 'masonry' ? 'columns:' + block.columns + ';column-gap:8px' : 'display:grid;grid-template-columns:repeat(' + block.columns + ',minmax(0,1fr));gap:8px';
		return '<div class="contactpanda-gallery-preview is-' + block.layout + '" style="' + style + '">' + block.images.map(function (image) { var format = block.image_format === 'fixed' ? 'aspect-ratio:' + imageRatio(block.image_ratio) + ';object-fit:' + block.image_fit + ';height:100%;' : 'height:auto;'; return '<span class="contactpanda-gallery-preview-item" style="' + (block.layout === 'masonry' ? 'margin:0 0 8px;break-inside:avoid;' : '') + '"><img src="' + escapeHtml(image.url) + '" alt="' + escapeHtml(image.alt || '') + '" style="display:block;width:100%;' + format + '">' + captionMarkup(image) + '</span>'; }).join('') + '</div>';
	}
	function blockStyle(block) {
		var padding = boxValues(block.padding, {top: 0, right: 0, bottom: 0, left: 0});
		var margin = boxValues(block.margin, {top: 0, right: 0, bottom: 20, left: 0});
		var radius = radiusValues(block.border_radius); var unit = block.radius_unit === '%' ? '%' : 'px';
		return 'text-align:' + block.alignment + ';background:' + escapeHtml(block.background_color || 'transparent') + ';padding:' + padding.top + 'px ' + padding.right + 'px ' + padding.bottom + 'px ' + padding.left + 'px;margin:' + margin.top + 'px ' + margin.right + 'px ' + margin.bottom + 'px ' + margin.left + 'px;border-radius:' + radius.top_left + unit + ' ' + radius.top_right + unit + ' ' + radius.bottom_right + unit + ' ' + radius.bottom_left + unit + ';';
	}
	function selectedPostType(block) { return (postFilters.types || []).find(function (type) { return String(type.id) === String(block.post_type); }) || (postFilters.types || [])[0] || {id: 'post', name: 'Posts', taxonomy: '', categories: [], posts: []}; }
	function productPreview(block) {
		if (!config.woocommerce) return '<div class="contactpanda-product-placeholder">' + escapeHtml(i18n.productsUnavailable) + '</div>';
		var products = productPreviewCache[block.id];
		if (!products) return '<div class="contactpanda-product-placeholder"><span class="spinner is-active"></span>' + escapeHtml(i18n.loadingProducts) + '</div>';
		if (!products.length) return '<div class="contactpanda-product-placeholder">' + escapeHtml(i18n.noProducts || 'No matching products were found.') + '</div>';
		return '<div class="contactpanda-product-preview-grid" style="grid-template-columns:repeat(' + Number(block.columns || 2) + ',minmax(0,1fr))">' + products.map(function (product) {
			var details = []; if (block.show_categories && product.categories) details.push(product.categories); if (block.show_brands && product.brands) details.push(product.brands);
			return '<div><img src="' + escapeHtml(product.image) + '" alt="" style="aspect-ratio:' + imageRatio(block.image_ratio) + ';object-fit:cover"><strong>' + escapeHtml(product.name) + '</strong>' + (details.length ? '<small>' + escapeHtml(details.join(' · ')) + '</small>' : '') + (block.show_price ? '<span>' + escapeHtml(product.price) + '</span>' : '') + '</div>';
		}).join('') + '</div>';
	}
	function postPreview(block) {
		var type = selectedPostType(block); var posts = type.posts || [];
		if (block.source === 'manual') posts = posts.filter(function (post) { return (block.post_ids || []).map(String).indexOf(String(post.id)) >= 0; });
		if (block.source === 'category' && block.category_id) posts = posts.filter(function (post) { return !post.term_ids || post.term_ids.map(String).indexOf(String(block.category_id)) >= 0; });
		posts = posts.slice(0, Number(block.count || 2));
		return '<div class="contactpanda-post-preview-grid" style="grid-template-columns:repeat(' + Number(block.columns || 2) + ',minmax(0,1fr))">' + posts.map(function (post) {
			return '<div>' + (block.show_image && post.image ? '<img src="' + escapeHtml(post.image) + '" alt="" style="aspect-ratio:' + imageRatio(block.image_ratio) + ';object-fit:cover">' : '') + (block.show_title ? '<strong>' + escapeHtml(post.name) + '</strong>' : '') + (block.show_excerpt ? '<p>' + escapeHtml(post.excerpt) + '</p>' : '') + '</div>';
		}).join('') + '</div>';
	}
	function selectedEvents(block) {
		var now = new Date(); var events = eventFilters.slice();
		if (block.source === 'manual') events = events.filter(function (event) { return (block.event_ids || []).map(String).indexOf(String(event.id)) >= 0; });
		else if (block.source === 'current') events = events.filter(function (event) { return eventDate(event.start) <= now && eventDate(event.end || event.start) >= now; });
		else if (block.source === 'past') events = events.filter(function (event) { return eventDate(event.end || event.start) < now; });
		else if (block.source === 'upcoming') events = events.filter(function (event) { return eventDate(event.start) >= now; });
		if (block.source !== 'manual' && block.category_id) events = events.filter(function (event) { return (event.term_ids || []).map(String).indexOf(String(block.category_id)) >= 0; });
		if (block.source !== 'manual' && block.featured_only) events = events.filter(function (event) { return !!event.featured; });
		events.sort(function (a, b) { var difference = eventDate(a.start) - eventDate(b.start); return block.event_order === 'asc' ? difference : -difference; });
		return events.slice(0, Number(block.count || 2));
	}
	function eventDate(value) { return new Date(String(value || '').replace(' ', 'T')); }
	function loadProductPreview(block) {
		if (!config.woocommerce || block.type !== 'products') return;
		window.clearTimeout(productPreviewTimers[block.id]);
		productPreviewTimers[block.id] = window.setTimeout(function () {
			var data = new FormData(); data.append('action', 'contactpanda_newsletter_products'); data.append('nonce', config.nonce); data.append('block', JSON.stringify(block));
			fetch(config.ajaxUrl, {method: 'POST', credentials: 'same-origin', body: data}).then(function (response) { return response.json(); }).then(function (result) {
				productPreviewCache[block.id] = result.success && result.data ? result.data.products || [] : [];
				renderCanvas();
			}).catch(function () { productPreviewCache[block.id] = []; renderCanvas(); });
		}, 250);
	}
	function preview(block) {
		if (block.type === 'heading') { var headingTag = block.level || 'h2'; return '<' + headingTag + ' style="color:' + escapeHtml(block.text_color || 'inherit') + '">' + escapeHtml(block.content || label('heading')) + '</' + headingTag + '>'; }
		if (block.type === 'text') return '<div class="contactpanda-canvas-text">' + safeRich(block.content || '<p>' + escapeHtml(label('text')) + '</p>') + '</div>';
		if (block.type === 'hero') { var overlay = Math.max(0, Math.min(90, Number(block.overlay_opacity || 45))) / 100; var overlayRgba = hexToRgba(block.overlay_color, overlay); var heroBackground = block.image_url ? 'linear-gradient(' + overlayRgba + ',' + overlayRgba + '),url(' + escapeHtml(block.image_url) + ')' : escapeHtml(block.overlay_color || '#182433'); return '<div class="contactpanda-hero-preview" style="background:' + heroBackground + ';background-size:cover;background-position:center;color:' + escapeHtml(block.text_color || '#fff') + ';text-align:' + escapeHtml(block.alignment || 'center') + '"><strong>' + escapeHtml(block.heading || i18n.heroHeading) + '</strong><div>' + safeRich(block.content || '') + '</div>' + (block.button_label ? '<span>' + escapeHtml(block.button_label) + '</span>' : '') + '</div>'; }
		if (block.type === 'social') { var socialNames = {website:'WWW',facebook:'f',instagram:'◎',linkedin:'in',x:'X',whatsapp:'WA',email:'@',youtube:'▶',tiktok:'♪'}; var socialIcons = Object.keys(socialNames).filter(function (network) { return block.links[network]; }).map(function (network) { return '<span style="color:' + escapeHtml(block.icon_color || '#4253b5') + ';background:' + escapeHtml(block.icon_background || '#ffffff') + ';border:1px solid ' + escapeHtml(block.border_color || 'transparent') + ';border-radius:' + Number(block.border_radius || 0) + '%">' + socialNames[network] + '</span>'; }).join(''); return '<div class="contactpanda-social-preview" style="text-align:' + escapeHtml(block.alignment || 'center') + '">' + (block.label ? '<p>' + escapeHtml(block.label) + '</p>' : '') + (socialIcons || '<em>' + escapeHtml(i18n.social) + '</em>') + '</div>'; }
		if (block.type === 'image') return block.url ? '<span class="contactpanda-single-image-preview" style="width:' + block.width + block.width_unit + ';max-width:100%"><img src="' + escapeHtml(block.url) + '" alt="' + escapeHtml(block.alt) + '">' + captionMarkup(block) + '</span>' : '<div class="contactpanda-image-placeholder"><span class="dashicons dashicons-format-image"></span>' + label('image') + '</div>';
		if (block.type === 'video') { var thumb = block.thumbnail_url || youtubeThumbnail(block.url); var provider = videoProvider(block.url); return '<div class="contactpanda-video-preview">' + (thumb ? '<img src="' + escapeHtml(thumb) + '" alt="' + escapeHtml(block.title || '') + '">' : '<span class="dashicons dashicons-video-alt3"></span>') + (provider && block.show_provider_badge ? '<em class="contactpanda-video-provider is-' + block.badge_position + '">' + escapeHtml(provider) + '</em>' : '') + (block.show_button ? '<strong>▶ ' + escapeHtml(block.button_label || i18n.videoButton) + '</strong>' : '') + '</div>'; }
		if (block.type === 'gallery') return galleryPreview(block);
		if (block.type === 'columns') return '<div class="contactpanda-columns-preview" style="grid-template-columns:repeat(' + block.columns + ',minmax(0,1fr))">' + block.children.map(function (children, columnIndex) { return '<div class="contactpanda-column-dropzone" data-column-index="' + columnIndex + '">' + (children.length ? children.map(function (child) { return '<div class="contactpanda-column-child' + (child.id === selectedId ? ' is-selected' : '') + '" draggable="true" data-child-id="' + escapeHtml(child.id) + '"><span class="contactpanda-column-child-label">' + escapeHtml(label(child.type)) + '</span><div class="contactpanda-column-child-content" style="' + blockStyle(child) + '">' + preview(child) + '</div><button type="button" data-child-remove="' + escapeHtml(child.id) + '" title="' + escapeHtml(i18n.remove) + '"><span class="dashicons dashicons-trash"></span></button></div>'; }).join('') : '<span class="contactpanda-column-empty">' + escapeHtml(i18n.dropBlockHere) + '</span>') + '</div>'; }).join('') + '</div>';
		if (['phone_button', 'email_button', 'whatsapp_button'].indexOf(block.type) >= 0) { var icons = {phone_button: '☎', email_button: '✉', whatsapp_button: '◉'}; return '<span class="contactpanda-canvas-button" style="background:' + escapeHtml(block.button_color || '#4253b5') + ';color:' + escapeHtml(block.text_color || '#fff') + ';display:' + (block.full_width ? 'block' : 'inline-block') + '"><span aria-hidden="true">' + icons[block.type] + '</span> ' + escapeHtml(block.label) + '</span>'; }
		if (block.type === 'button') return '<span class="contactpanda-canvas-button" style="background:' + escapeHtml(block.button_color || '#4253b5') + ';border:1px solid ' + escapeHtml(block.border_color || block.button_color || '#4253b5') + ';color:' + escapeHtml(block.text_color || '#fff') + ';display:' + (block.full_width ? 'block' : 'inline-block') + ';text-transform:' + (block.uppercase ? 'uppercase' : 'none') + '">' + escapeHtml(block.label || i18n.buttonLabel) + '</span>';
		if (block.type === 'divider') return '<hr style="border:0;border-top:' + Number(block.line_width || 1) + 'px ' + escapeHtml(block.line_style || 'solid') + ' ' + escapeHtml(block.line_color || '#d9dee4') + '">';
		if (block.type === 'spacer') return '<div class="contactpanda-canvas-spacer" style="height:' + Number(block.height || 24) + 'px"><span>' + Number(block.height || 24) + ' px</span></div>';
		if (block.type === 'products') return productPreview(block);
		if (block.type === 'events') return '<div class="contactpanda-event-block-preview" style="grid-template-columns:repeat(' + Number(block.columns || 2) + ',minmax(0,1fr))">' + selectedEvents(block).map(function (item) { var imageStyle = block.image_format === 'original' ? 'height:auto' : 'aspect-ratio:' + imageRatio(block.image_ratio) + ';object-fit:' + block.image_fit; return '<div style="text-align:' + escapeHtml(block.text_alignment || 'left') + '">' + (block.show_image && item.image ? '<img src="' + escapeHtml(item.image) + '" alt="" style="' + imageStyle + '">' : '') + '<strong>' + escapeHtml(item.name) + '</strong>' + (block.show_date !== false && item.start ? '<span>' + escapeHtml(item.start) + '</span>' : '') + (block.show_location && item.location ? '<span>' + escapeHtml(item.location) + '</span>' : '') + '</div>'; }).join('') + '</div>';
		if (block.type === 'posts') return postPreview(block);
		return '';
	}
	function renderCanvas() {
		container.replaceChildren();
		var header = document.createElement('div'); header.className = 'contactpanda-canvas-header'; header.innerHTML = config.logoUrl ? '<img src="' + escapeHtml(config.logoUrl) + '" alt="">' : '<span>' + escapeHtml(i18n.heading || 'ContactPanda') + '</span>'; container.appendChild(header);
		blocks.forEach(function (block, index) {
			var card = document.createElement('section');
			card.className = 'contactpanda-newsletter-block' + (block.id === selectedId ? ' is-selected' : '');
			card.dataset.id = block.id; card.dataset.pandaEditorElement = block.id; card.draggable = block.type !== 'text';
			var inlineTools = '';
			if (block.id === selectedId && block.type === 'text') {
				inlineTools = '<div class="contactpanda-inline-text-toolbar"><button type="button" data-command="bold" title="Bold"><strong>B</strong></button><button type="button" data-command="italic" title="Italic"><em>I</em></button><button type="button" data-command="createLink" title="' + escapeHtml(i18n.insertLink) + '"><span class="dashicons dashicons-admin-links"></span></button><button type="button" data-command="insertUnorderedList" title="List"><span class="dashicons dashicons-editor-ul"></span></button><button type="button" data-command="insertOrderedList" title="Numbered list"><span class="dashicons dashicons-editor-ol"></span></button><button type="button" data-command="justifyLeft"><span class="dashicons dashicons-editor-alignleft"></span></button><button type="button" data-command="justifyCenter"><span class="dashicons dashicons-editor-aligncenter"></span></button><button type="button" data-command="justifyRight"><span class="dashicons dashicons-editor-alignright"></span></button></div>';
			}
			if (block.id === selectedId && block.type === 'image') {
				inlineTools = '<div class="contactpanda-inline-image-toolbar"><div class="contactpanda-inline-align"><button type="button" data-image-align="left"><span class="dashicons dashicons-editor-alignleft"></span></button><button type="button" data-image-align="center"><span class="dashicons dashicons-editor-aligncenter"></span></button><button type="button" data-image-align="right"><span class="dashicons dashicons-editor-alignright"></span></button></div><label><span>' + escapeHtml(i18n.width) + ' (px oder %)</span><input type="text" data-image-width value="' + Number(block.width) + escapeHtml(block.width_unit) + '"></label></div>';
			}
			var editable = block.type === 'text' ? ' contenteditable="true" data-inline-editor="true"' : '';
			card.innerHTML = inlineTools + '<div class="contactpanda-block-grip"><span class="dashicons dashicons-move"></span></div><div class="contactpanda-block-preview"' + editable + ' style="' + blockStyle(block) + '">' + preview(block) + '</div><div class="contactpanda-block-actions"><button type="button" data-action="duplicate" title="' + escapeHtml(i18n.duplicate) + '"><span class="dashicons dashicons-admin-page"></span></button><button type="button" data-action="remove" title="' + escapeHtml(i18n.remove) + '"><span class="dashicons dashicons-trash"></span></button></div>';
			card.querySelectorAll('.contactpanda-column-child').forEach(function (childCard) {
				childCard.addEventListener('click', function (event) { event.stopPropagation(); var remove = event.target.closest('[data-child-remove]'); if (remove) { var target = locateBlock(remove.dataset.childRemove); if (target) target.list.splice(target.index, 1); selectedId = block.id; render(); return; } selectedId = childCard.dataset.childId; inspectorTab = 'content'; render(); });
				childCard.addEventListener('dragstart', function (event) { event.stopPropagation(); draggedId = childCard.dataset.childId; event.dataTransfer.effectAllowed = 'move'; event.dataTransfer.setData('text/plain', draggedId); childCard.classList.add('is-dragging'); });
				childCard.addEventListener('dragend', function (event) { event.stopPropagation(); childCard.classList.remove('is-dragging'); });
			});
			card.querySelectorAll('.contactpanda-column-dropzone').forEach(function (dropzone) {
				dropzone.addEventListener('dragover', function (event) { event.preventDefault(); event.stopPropagation(); dropzone.classList.add('is-drop-target'); });
				dropzone.addEventListener('dragleave', function (event) { event.stopPropagation(); dropzone.classList.remove('is-drop-target'); });
				dropzone.addEventListener('drop', function (event) {
					event.preventDefault(); event.stopPropagation(); dropzone.classList.remove('is-drop-target'); var columnIndex = Number(dropzone.dataset.columnIndex); var paletteType = event.dataTransfer.getData('contactpanda/type'); var added;
					if (paletteType) { if (paletteType === 'columns') return; added = defaultBlock(paletteType); }
					else { var dragged = locateBlock(draggedId); if (!dragged || dragged.block.type === 'columns') return; added = takeBlock(draggedId); }
					if (!added || added.type === 'columns') return; block.children[columnIndex].push(added); selectedId = added.id; render();
				});
			});
			var inlineEditor = card.querySelector('[data-inline-editor]');
			if (inlineEditor) {
				inlineEditor.addEventListener('focus', function () {
					if (selectedId === block.id) return;
					selectedId = block.id; inspectorTab = 'content';
					container.querySelectorAll('.contactpanda-newsletter-block.is-selected').forEach(function (item) { item.classList.remove('is-selected'); });
					card.classList.add('is-selected'); renderInspector(); sync();
				});
				inlineEditor.addEventListener('input', function () {
					var rich = inlineEditor.querySelector('.contactpanda-canvas-text'); block.content = rich ? rich.innerHTML : inlineEditor.innerHTML; sync();
					var instance = window.tinymce && tinymce.get(editorId); var plain = document.getElementById(editorId); syncingInline = true;
					if (instance && instance.getContent() !== block.content) instance.setContent(block.content);
					if (plain) plain.value = block.content; syncingInline = false;
				});
				card.querySelectorAll('[data-command]').forEach(function (button) {
					button.addEventListener('mousedown', function (event) { event.preventDefault(); });
					button.addEventListener('click', function (event) { event.stopPropagation(); inlineEditor.focus(); var value = null; if (button.dataset.command === 'createLink') { value = window.prompt(i18n.targetUrl, 'https://'); if (!value) return; } document.execCommand(button.dataset.command, false, value); inlineEditor.dispatchEvent(new Event('input')); });
				});
			}
			card.querySelectorAll('[data-image-align]').forEach(function (button) { button.addEventListener('click', function (event) { event.stopPropagation(); block.alignment = button.dataset.imageAlign; sync(); renderCanvas(); }); });
			var imageWidth = card.querySelector('[data-image-width]');
			if (imageWidth) imageWidth.addEventListener('input', function (event) { event.stopPropagation(); var match = imageWidth.value.trim().match(/^(\d+(?:\.\d+)?)\s*(px|%)$/i); if (!match) return; block.width_unit = match[2].toLowerCase(); var maximum = block.width_unit === 'px' ? 760 : 100; block.width = Math.max(1, Math.min(maximum, Number(match[1]))); sync(); var image = card.querySelector('.contactpanda-block-preview img'); if (image) image.style.width = block.width + block.width_unit; });
			card.addEventListener('click', function (event) {
				if (event.target.closest('.contactpanda-column-child, .contactpanda-column-dropzone')) return;
				var action = event.target.closest('[data-action]');
				if (action) {
					if (action.dataset.action === 'remove') { blocks.splice(index, 1); selectedId = blocks[index] ? blocks[index].id : (blocks[index - 1] ? blocks[index - 1].id : ''); }
					else { var copy = JSON.parse(JSON.stringify(blocks[index])); copy.id = uid(); blocks.splice(index + 1, 0, copy); selectedId = copy.id; }
					render(); return;
				}
				if (event.target.closest('[data-inline-editor], .contactpanda-inline-text-toolbar, .contactpanda-inline-image-toolbar')) return;
				selectedId = block.id; render();
			});
			card.addEventListener('dragstart', function (event) { if (event.target.closest('.contactpanda-column-child')) return; draggedId = block.id; event.dataTransfer.effectAllowed = 'move'; event.dataTransfer.setData('text/plain', block.id); card.classList.add('is-dragging'); });
			card.addEventListener('dragend', function () { draggedId = ''; card.classList.remove('is-dragging'); });
			card.addEventListener('dragover', function (event) { event.preventDefault(); card.classList.add('is-drop-target'); });
			card.addEventListener('dragleave', function () { card.classList.remove('is-drop-target'); });
			card.addEventListener('drop', function (event) {
				event.preventDefault(); card.classList.remove('is-drop-target');
				var rectangle = card.getBoundingClientRect();
				var insertIndex = index + (event.clientY > rectangle.top + rectangle.height / 2 ? 1 : 0);
				var paletteType = event.dataTransfer.getData('contactpanda/type');
				if (paletteType) { var added = defaultBlock(paletteType); blocks.splice(insertIndex, 0, added); selectedId = added.id; render(); return; }
				var source = blocks.findIndex(function (item) { return item.id === draggedId; }); var moved = takeBlock(draggedId);
				if (!moved) return;
				if (source >= 0 && source < insertIndex) insertIndex -= 1;
				blocks.splice(insertIndex, 0, moved); render();
			});
			container.appendChild(card);
			if (block.type === 'products' && !Object.prototype.hasOwnProperty.call(productPreviewCache, block.id)) loadProductPreview(block);
		});
		var footer = document.createElement('div'); footer.className = 'contactpanda-canvas-fixed-footer'; footer.innerHTML = safeRich(config.footerHtml || ''); container.appendChild(footer);
		emptyMessage.hidden = blocks.length > 0; sync();
	}
	function field(caption, type, value, callback, options) {
		var wrapper = document.createElement('label'); wrapper.className = 'contactpanda-inspector-field';
		var title = document.createElement('span'); title.textContent = caption; wrapper.appendChild(title);
		var input = document.createElement(type === 'textarea' ? 'textarea' : type === 'select' ? 'select' : 'input');
		if (type !== 'textarea' && type !== 'select') input.type = type;
		if (type === 'select') (options || []).forEach(function (option) { var node = document.createElement('option'); node.value = option[0]; node.textContent = option[1]; input.appendChild(node); });
		input.value = value !== undefined && value !== null ? value : '';
		input.addEventListener(type === 'select' ? 'change' : 'input', function () { callback(this.value); sync(); renderCanvas(); });
		wrapper.appendChild(input); return wrapper;
	}
	function alignmentField(block, allowFullWidth) {
		var options = [['left', i18n.left], ['center', i18n.center], ['right', i18n.right]];
		if (allowFullWidth) options.push(['full', i18n.fullWidth]);
		return field(i18n.alignment, 'select', allowFullWidth && block.full_width ? 'full' : block.alignment, function (value) {
			block.full_width = allowFullWidth && value === 'full';
			if (value !== 'full') block.alignment = value;
		}, options);
	}
	function colorField(caption, value, callback) {
		var original = value || '#ffffff';
		var wrapper = field(caption, 'color', /^#[0-9a-f]{6}$/i.test(original) ? original : '#ffffff', callback);
		wrapper.classList.add('contactpanda-color-field', 'contactpanda-email-color-field');
		var input = wrapper.querySelector('input');
		input.dataset.contactpandaColorValue = original;
		input.setAttribute('value', original);
		if (window.ContactPandaAdminUI && ContactPandaAdminUI.enhanceColorPicker) ContactPandaAdminUI.enhanceColorPicker(input);
		return wrapper;
	}
	function imagePickerField(caption, value, attachmentId, callback, options) {
		var wrapper = document.createElement('div'); wrapper.className = 'contactpanda-inspector-field contactpanda-inspector-image-field';
		var title = document.createElement('span'); title.textContent = caption; wrapper.appendChild(title);
		if (window.ContactPandaAdminUI && ContactPandaAdminUI.createImagePicker) {
			var pickerOptions = Object.assign({}, options || {}, {url: value || '', attachmentId: attachmentId || 0, title: i18n.chooseImage, buttonLabel: i18n.useImage, dropLabel: i18n.openMedia, removeLabel: i18n.remove, onChange: callback});
			wrapper.appendChild(ContactPandaAdminUI.createImagePicker(pickerOptions));
		}
		return wrapper;
	}
	function updateProductBlock(block) { delete productPreviewCache[block.id]; sync(); renderCanvas(); loadProductPreview(block); }
	function productSelect(caption, value, options, callback, multiple) {
		if (multiple) {
			var group = document.createElement('fieldset'); group.className = 'contactpanda-inspector-checklist'; var legend = document.createElement('legend'); legend.textContent = caption; group.appendChild(legend);
			options.forEach(function (option) { var item = document.createElement('label'); var checkbox = document.createElement('input'); checkbox.type = 'checkbox'; checkbox.value = option[0]; checkbox.checked = (value || []).map(String).indexOf(String(option[0])) >= 0; checkbox.addEventListener('change', function () { callback(Array.prototype.filter.call(group.querySelectorAll('input[type="checkbox"]'), function (input) { return input.checked; }).map(function (input) { return Number(input.value); })); }); item.appendChild(checkbox); item.appendChild(document.createTextNode(option[1])); group.appendChild(item); });
			return group;
		}
		var wrapper = document.createElement('label'); wrapper.className = 'contactpanda-inspector-field'; var title = document.createElement('span'); title.textContent = caption; wrapper.appendChild(title);
		var select = document.createElement('select');
		options.forEach(function (option) { var node = document.createElement('option'); node.value = option[0]; node.textContent = option[1]; node.selected = String(value || '') === String(option[0]); select.appendChild(node); });
		select.addEventListener('change', function () { callback(select.value); }); wrapper.appendChild(select); return wrapper;
	}
	function toggleField(caption, checked, callback) { var wrapper = document.createElement('label'); wrapper.className = 'contactpanda-inspector-toggle'; var input = document.createElement('input'); input.type = 'checkbox'; input.checked = !!checked; input.addEventListener('change', function () { callback(input.checked); }); wrapper.appendChild(input); wrapper.appendChild(document.createTextNode(caption)); return wrapper; }
	function pairedFields(first, second) { var row = document.createElement('div'); row.className = 'contactpanda-inspector-pair'; row.appendChild(first); row.appendChild(second); return row; }
	function captionFields(target) {
		var group = document.createElement('details'); group.className = 'contactpanda-caption-settings'; var legend = document.createElement('summary'); legend.textContent = i18n.captionSettings; group.appendChild(legend);
		group.appendChild(field(i18n.caption, 'text', target.caption, function (value) { target.caption = value; }));
		group.appendChild(field(i18n.captionPosition, 'select', target.caption_position, function (value) { target.caption_position = value; }, [['top', i18n.top], ['center', i18n.center], ['bottom', i18n.bottom], ['outside', i18n.belowImage]]));
		group.appendChild(colorField(i18n.captionBackground, target.caption_background, function (value) { target.caption_background = value; }));
		group.appendChild(colorField(i18n.textColor, target.caption_color, function (value) { target.caption_color = value; }));
		group.appendChild(field(i18n.fontSize, 'number', target.caption_size, function (value) { target.caption_size = Math.max(10, Math.min(36, Number(value) || 14)); }));
		group.appendChild(field(i18n.fontWeight, 'select', target.caption_weight, function (value) { target.caption_weight = value; }, [['400', i18n.normal], ['600', i18n.semibold], ['700', i18n.bold]]));
		group.appendChild(field(i18n.uppercase, 'select', target.caption_uppercase ? '1' : '0', function (value) { target.caption_uppercase = value === '1'; }, [['0', i18n.no], ['1', i18n.yes]]));
		group.appendChild(field(i18n.cornerRadius, 'number', target.caption_radius, function (value) { target.caption_radius = Math.max(0, Math.min(40, Number(value) || 0)); }));
		return group;
	}
	function galleryImageFields(block) {
		var list = document.createElement('div'); list.className = 'contactpanda-gallery-image-list'; var draggedImage = -1;
		function choose(index) {
			if (!window.wp || !wp.media) return;
			var frame = wp.media({title: i18n.chooseGallery, button: {text: i18n.useImage}, multiple: false});
			frame.on('select', function () { var item = frame.state().get('selection').first().toJSON(); var image = captionImage({url:item.url || '',attachment_id:item.id || 0,alt:item.alt || '',link:''}); if (index < block.images.length) block.images[index] = image; else block.images.push(image); sync(); render(); }); frame.open();
		}
		block.images.forEach(function (image, index) {
			var card = document.createElement('details'); card.className = 'contactpanda-gallery-image-card'; card.draggable = true;
			var summary = document.createElement('summary'); summary.innerHTML = '<span class="dashicons dashicons-move"></span><img src="' + escapeHtml(image.url) + '" alt=""><strong>' + escapeHtml(image.caption || image.alt || (i18n.image + ' ' + (index + 1))) + '</strong><span class="contactpanda-gallery-row-actions"><button type="button" class="is-edit" title="' + escapeHtml(i18n.edit) + '"><span class="dashicons dashicons-edit"></span></button><button type="button" class="is-remove" title="' + escapeHtml(i18n.remove) + '"><span class="dashicons dashicons-trash"></span></button></span>'; card.appendChild(summary);
			summary.querySelector('.is-edit').addEventListener('click', function (event) { event.preventDefault(); event.stopPropagation(); choose(index); }); summary.querySelector('.is-remove').addEventListener('click', function (event) { event.preventDefault(); event.stopPropagation(); block.images.splice(index, 1); sync(); render(); });
			var body = document.createElement('div'); body.className = 'contactpanda-gallery-image-fields'; body.appendChild(field(i18n.alternativeText, 'text', image.alt, function (value) { image.alt = value; })); body.appendChild(field(i18n.targetUrl, 'url', image.link, function (value) { image.link = value; })); body.appendChild(captionFields(image)); card.appendChild(body);
			card.addEventListener('dragstart', function (event) { draggedImage = index; event.stopPropagation(); event.dataTransfer.effectAllowed = 'move'; card.classList.add('is-dragging'); });
			card.addEventListener('dragend', function () { card.classList.remove('is-dragging'); });
			card.addEventListener('dragover', function (event) { event.preventDefault(); event.stopPropagation(); card.classList.add('is-drop-target'); });
			card.addEventListener('dragleave', function () { card.classList.remove('is-drop-target'); });
			card.addEventListener('drop', function (event) { event.preventDefault(); event.stopPropagation(); card.classList.remove('is-drop-target'); if (draggedImage < 0 || draggedImage === index) return; var moved = block.images.splice(draggedImage, 1)[0]; block.images.splice(index, 0, moved); sync(); render(); });
			list.appendChild(card);
		});
		if (!block.images.length) { var empty = document.createElement('button'); empty.type = 'button'; empty.className = 'contactpanda-gallery-empty-row'; empty.innerHTML = '<span class="dashicons dashicons-plus-alt2"></span><span>' + escapeHtml(i18n.chooseImage) + '</span>'; empty.addEventListener('click', function () { choose(0); }); list.appendChild(empty); }
		if (block.images.length && block.images.length < 6) { var add = document.createElement('button'); add.type = 'button'; add.className = 'button contactpanda-gallery-add-row'; add.innerHTML = '<span class="dashicons dashicons-plus-alt2"></span> ' + escapeHtml(i18n.addImage); add.addEventListener('click', function () { choose(block.images.length); }); list.appendChild(add); }
		return list;
	}
	function linkedBoxFields(caption, block, property, linkedProperty, labels) {
		var group = document.createElement('fieldset'); group.className = 'contactpanda-inspector-box-values';
		var legend = document.createElement('legend'); legend.textContent = caption; group.appendChild(legend);
		var values = block[property];
		[['top', labels[0]], ['right', labels[1]], ['bottom', labels[2]], ['left', labels[3]]].forEach(function (item) {
			var node = field(item[1], 'number', values[item[0]], function (value) {
				var number = Math.max(0, Math.min(200, Number(value) || 0));
				if (block[linkedProperty]) Object.keys(values).forEach(function (key) { values[key] = number; }); else values[item[0]] = number;
				sync(); renderCanvas(); if (block[linkedProperty]) renderInspector();
			});
			group.appendChild(node);
		});
		var linked = document.createElement('label'); linked.className = 'contactpanda-inspector-link-values'; var checkbox = document.createElement('input'); checkbox.type = 'checkbox'; checkbox.checked = !!block[linkedProperty]; checkbox.addEventListener('change', function () { block[linkedProperty] = checkbox.checked; sync(); }); linked.appendChild(checkbox); linked.appendChild(document.createTextNode(i18n.linkValues)); group.appendChild(linked);
		return group;
	}
	function radiusFields(block) {
		var group = document.createElement('fieldset'); group.className = 'contactpanda-inspector-box-values contactpanda-radius-values';
		var legend = document.createElement('legend'); legend.textContent = i18n.cornerRadius; group.appendChild(legend);
		var values = block.border_radius;
		[['top_left', i18n.topLeft], ['top_right', i18n.topRight], ['bottom_right', i18n.bottomRight], ['bottom_left', i18n.bottomLeft]].forEach(function (item) {
			var key = item[0];
			group.appendChild(field(item[1], 'number', values[key], function (value) { var number = Math.max(0, Math.min(200, Number(value) || 0)); if (block.radius_linked) Object.keys(values).forEach(function (corner) { values[corner] = number; }); else values[key] = number; sync(); renderCanvas(); if (block.radius_linked) renderInspector(); }));
		});
		group.appendChild(field(i18n.unit, 'select', block.radius_unit, function (value) { block.radius_unit = value === '%' ? '%' : 'px'; }, [['px', i18n.pixels], ['%', i18n.percent]]));
		var linked = document.createElement('label'); linked.className = 'contactpanda-inspector-link-values'; var checkbox = document.createElement('input'); checkbox.type = 'checkbox'; checkbox.checked = !!block.radius_linked; checkbox.addEventListener('change', function () { block.radius_linked = checkbox.checked; sync(); }); linked.appendChild(checkbox); linked.appendChild(document.createTextNode(i18n.linkValues)); group.appendChild(linked);
		return group;
	}
	function finishInspector(block, heading) {
		var contentNodes = []; while (heading.nextSibling) contentNodes.push(heading.nextSibling), inspector.removeChild(heading.nextSibling);
		var tabs = document.createElement('div'); tabs.className = 'contactpanda-inspector-tabs';
		[['content', i18n.contentTab], ['design', i18n.designTab]].forEach(function (item) { var button = document.createElement('button'); button.type = 'button'; button.className = 'button' + (inspectorTab === item[0] ? ' is-active' : ''); button.textContent = item[1]; button.addEventListener('click', function () { inspectorTab = item[0]; renderInspector(); }); tabs.appendChild(button); });
		var contentPane = document.createElement('div'); contentPane.className = 'contactpanda-inspector-pane'; contentPane.hidden = inspectorTab !== 'content'; contentNodes.forEach(function (node) { contentPane.appendChild(node); });
		var designPane = document.createElement('div'); designPane.className = 'contactpanda-inspector-pane'; designPane.hidden = inspectorTab !== 'design';
		designPane.appendChild(colorField(i18n.backgroundColor, block.background_color || '#ffffff', function (value) { block.background_color = value; }));
		designPane.appendChild(linkedBoxFields(i18n.padding, block, 'padding', 'padding_linked', [i18n.top, i18n.rightSide, i18n.bottom, i18n.leftSide]));
		designPane.appendChild(linkedBoxFields(i18n.margin, block, 'margin', 'margin_linked', [i18n.top, i18n.rightSide, i18n.bottom, i18n.leftSide]));
		designPane.appendChild(radiusFields(block));
		inspector.appendChild(tabs); inspector.appendChild(contentPane); inspector.appendChild(designPane);
	}
	function postVisibilityOptions(block) {
		var group = document.createElement('fieldset'); group.className = 'contactpanda-inspector-options';
		var legend = document.createElement('legend'); legend.textContent = i18n.displayedContent; group.appendChild(legend);
		var options = [['show_image', i18n.showImage], ['show_title', i18n.showTitle], ['show_excerpt', i18n.showExcerpt]];
		var active = options.filter(function (option) { return !!block[option[0]]; }).length;
		options.forEach(function (option) {
			var labelNode = document.createElement('label'); var input = document.createElement('input'); input.type = 'checkbox'; input.checked = !!block[option[0]]; input.disabled = input.checked && active === 1; input.title = input.disabled ? i18n.atLeastOneContent : '';
			input.addEventListener('change', function () { block[option[0]] = input.checked; sync(); renderCanvas(); renderInspector(); });
			labelNode.appendChild(input); labelNode.appendChild(document.createTextNode(option[1])); group.appendChild(labelNode);
		});
		return group;
	}
	function removeEditor() { if (window.wp && wp.editor && document.getElementById(editorId)) { try { wp.editor.remove(editorId); } catch (ignore) {} } }
	function renderInspector() {
		removeEditor(); inspector.replaceChildren(); var block = selectedBlock();
		if (!block) { var note = document.createElement('p'); note.className = 'contactpanda-muted'; note.textContent = i18n.selectBlock; inspector.appendChild(note); return; }
		var heading = document.createElement('h3'); heading.textContent = label(block.type); inspector.appendChild(heading);
		if (block.type === 'heading') { inspector.appendChild(field(i18n.content, 'text', block.content, function (value) { block.content = value; })); inspector.appendChild(field(i18n.headingLevel, 'select', block.level, function (value) { block.level = value; }, [['h1','H1'],['h2','H2'],['h3','H3'],['h4','H4'],['h5','H5'],['h6','H6']])); inspector.appendChild(colorField(i18n.textColor, block.text_color || '#39495a', function (value) { block.text_color = value; })); inspector.appendChild(alignmentField(block)); }
		else if (block.type === 'text') {
			var editor = document.createElement('textarea'); editor.id = editorId; editor.value = block.content || ''; inspector.appendChild(editor); inspector.appendChild(alignmentField(block));
			if (window.wp && wp.editor) window.setTimeout(function () {
				wp.editor.initialize(editorId, {tinymce: {toolbar1: 'bold italic link bullist numlist alignleft aligncenter alignright undo redo', menubar: false}, quicktags: true});
				var instance = window.tinymce && tinymce.get(editorId);
				if (instance) instance.on('change keyup undo redo', function () { if (syncingInline) return; block.content = instance.getContent(); sync(); renderCanvas(); });
				var plain = document.getElementById(editorId); if (plain) plain.addEventListener('input', function () { block.content = plain.value; sync(); renderCanvas(); });
			}, 0);
		} else if (block.type === 'image') {
			inspector.appendChild(imagePickerField(i18n.image, block.url, block.attachment_id, function (image) { block.url = image ? image.url : ''; block.attachment_id = image ? image.id : 0; if (image && !block.alt) block.alt = image.alt || ''; render(); })); inspector.appendChild(field(i18n.alternativeText, 'text', block.alt, function (value) { block.alt = value; })); inspector.appendChild(alignmentField(block)); inspector.appendChild(field(i18n.width, 'text', String(block.width) + block.width_unit, function (value) { var match = String(value).trim().match(/^(\d+(?:\.\d+)?)\s*(%|px)$/i); if (!match) return; block.width_unit = match[2].toLowerCase(); block.width = Math.max(1, Math.min(block.width_unit === 'px' ? 760 : 100, Number(match[1]))); })); inspector.appendChild(captionFields(block));
		} else if (block.type === 'hero') {
			inspector.appendChild(imagePickerField(i18n.image, block.image_url, block.image_id, function (image) { block.image_url = image ? image.url : ''; block.image_id = image ? image.id : 0; render(); })); inspector.appendChild(field(i18n.heroHeading, 'text', block.heading, function (value) { block.heading = value; })); inspector.appendChild(field(i18n.heroText, 'textarea', block.content, function (value) { block.content = value; })); inspector.appendChild(field(i18n.buttonLabel, 'text', block.button_label, function (value) { block.button_label = value; })); inspector.appendChild(field(i18n.targetUrl, 'url', block.button_url, function (value) { block.button_url = value; })); inspector.appendChild(colorField(i18n.overlayColor, block.overlay_color, function (value) { block.overlay_color = value; })); inspector.appendChild(field(i18n.overlayOpacity, 'number', block.overlay_opacity, function (value) { block.overlay_opacity = Math.max(0, Math.min(90, Number(value || 0))); })); inspector.appendChild(colorField(i18n.textColor, block.text_color, function (value) { block.text_color = value; })); inspector.appendChild(alignmentField(block));
		} else if (block.type === 'social') {
			inspector.appendChild(field(i18n.socialLabel, 'text', block.label, function (value) { block.label = value; })); ['website','facebook','instagram','linkedin','x','whatsapp','email','youtube','tiktok'].forEach(function (network) { inspector.appendChild(field(i18n[network], network === 'email' ? 'email' : 'url', block.links[network] || '', function (value) { block.links[network] = value; })); }); inspector.appendChild(colorField(i18n.iconColor, block.icon_color, function (value) { block.icon_color = value; })); inspector.appendChild(colorField(i18n.iconBackground, block.icon_background, function (value) { block.icon_background = value; })); inspector.appendChild(colorField(i18n.borderColor, block.border_color, function (value) { block.border_color = value; })); inspector.appendChild(field(i18n.cornerRadius, 'number', block.border_radius, function (value) { block.border_radius = Math.max(0, Math.min(50, Number(value) || 0)); })); inspector.appendChild(alignmentField(block));
		} else if (block.type === 'video') {
			inspector.appendChild(imagePickerField(i18n.videoThumbnail, block.thumbnail_url, block.thumbnail_id, function (image) { block.thumbnail_url = image ? image.url : ''; block.thumbnail_id = image ? image.id : 0; render(); })); inspector.appendChild(alignmentField(block)); inspector.appendChild(field(i18n.videoUrl, 'url', block.url, function (value) { block.url = value; if (!block.thumbnail_url) block.thumbnail_url = youtubeThumbnail(value); })); var thumbnail = field(i18n.videoThumbnail, 'url', block.thumbnail_url, function (value) { block.thumbnail_url = value; }); inspector.appendChild(thumbnail); inspector.appendChild(field(i18n.videoTitle, 'text', block.title, function (value) { block.title = value; })); inspector.appendChild(toggleField(i18n.showButton, block.show_button, function (checked) { block.show_button = checked; render(); })); if (block.show_button) inspector.appendChild(field(i18n.videoButton, 'text', block.button_label, function (value) { block.button_label = value; })); inspector.appendChild(toggleField(i18n.providerBadge, block.show_provider_badge, function (checked) { block.show_provider_badge = checked; render(); })); if (block.show_provider_badge) inspector.appendChild(field(i18n.badgePosition, 'select', block.badge_position, function (value) { block.badge_position = value === 'top' ? 'top' : 'bottom'; }, [['top', i18n.top], ['bottom', i18n.bottom]]));
		} else if (block.type === 'gallery') {
			inspector.appendChild(galleryImageFields(block)); inspector.appendChild(pairedFields(field(i18n.desktopColumns, 'select', String(block.columns), function (value) { block.columns = Number(value); }, [['1','1'],['2','2'],['3','3']]), field(i18n.tabletColumns, 'select', String(block.tablet_columns), function (value) { block.tablet_columns = Number(value); }, [['1','1'],['2','2'],['3','3']]))); inspector.appendChild(field(i18n.mobileColumns, 'select', String(block.mobile_columns), function (value) { block.mobile_columns = Number(value); }, [['1','1'],['2','2'],['3','3']])); inspector.appendChild(field(i18n.galleryLayout, 'select', block.layout, function (value) { block.layout = value === 'masonry' ? 'masonry' : 'grid'; }, [['grid',i18n.grid],['masonry',i18n.masonry]])); inspector.appendChild(field(i18n.imageFormat, 'select', block.image_format, function (value) { block.image_format = value === 'original' ? 'original' : 'fixed'; renderInspector(); }, [['fixed',i18n.fixedFormat],['original',i18n.originalFormat]])); if (block.image_format === 'fixed') { inspector.appendChild(field(i18n.imageRatio, 'select', block.image_ratio, function (value) { block.image_ratio = value; }, [['1:1',i18n.ratioSquare],['4:5',i18n.ratioPortrait],['4:3',i18n.ratioLandscape],['16:9','16:9']])); inspector.appendChild(field(i18n.imageFit, 'select', block.image_fit, function (value) { block.image_fit = value === 'contain' ? 'contain' : 'cover'; }, [['cover',i18n.cover],['contain',i18n.contain]])); }
		} else if (block.type === 'columns') {
			inspector.appendChild(field(i18n.columns, 'select', String(block.columns), function (value) { var count = Number(value); while (block.children.length < count) block.children.push([]); if (block.children.length > count && block.children.slice(count).some(function (column) { return column.length; }) && !window.confirm(i18n.removeColumnContent)) { renderInspector(); return; } block.columns = count; block.children = block.children.slice(0, count); render(); }, [['2','2'],['3','3']]));
			var columnsNote = document.createElement('p'); columnsNote.className = 'description'; columnsNote.textContent = i18n.columnsHint; inspector.appendChild(columnsNote);
		} else if (['phone_button', 'email_button', 'whatsapp_button'].indexOf(block.type) >= 0) {
			inspector.appendChild(field(i18n.buttonLabel, 'text', block.label, function (value) { block.label = value; })); inspector.appendChild(field(block.type === 'email_button' ? i18n.emailAddress : i18n.phoneNumber, 'text', block.value, function (value) { block.value = value; })); if (block.type === 'whatsapp_button') inspector.appendChild(field(i18n.prefilledMessage, 'textarea', block.message, function (value) { block.message = value; })); inspector.appendChild(colorField(i18n.backgroundColor, block.button_color || '#4253b5', function (value) { block.button_color = value; })); inspector.appendChild(colorField(i18n.textColor, block.text_color || '#ffffff', function (value) { block.text_color = value; })); inspector.appendChild(alignmentField(block, true));
		} else if (block.type === 'button') { inspector.appendChild(field(i18n.buttonLabel, 'text', block.label, function (value) { block.label = value; })); inspector.appendChild(field(i18n.targetUrl, 'url', block.url, function (value) { block.url = value; })); inspector.appendChild(colorField(i18n.backgroundColor, block.button_color || '#4253b5', function (value) { block.button_color = value; })); inspector.appendChild(colorField(i18n.borderColor, block.border_color || '#4253b5', function (value) { block.border_color = value; })); inspector.appendChild(colorField(i18n.textColor, block.text_color || '#ffffff', function (value) { block.text_color = value; })); inspector.appendChild(field(i18n.uppercase, 'select', block.uppercase ? '1' : '0', function (value) { block.uppercase = value === '1'; }, [['0',i18n.no],['1',i18n.yes]])); inspector.appendChild(alignmentField(block, true)); }
		else if (block.type === 'divider') { inspector.appendChild(field(i18n.lineStyle, 'select', block.line_style, function (value) { block.line_style = value; }, [['solid',i18n.solid],['double',i18n.double],['dotted',i18n.dotted]])); inspector.appendChild(field(i18n.lineWidth, 'number', block.line_width, function (value) { block.line_width = Math.max(1, Math.min(12, Number(value) || 1)); })); inspector.appendChild(colorField(i18n.lineColor, block.line_color, function (value) { block.line_color = value; })); }
		else if (block.type === 'spacer') inspector.appendChild(field(i18n.spacerHeight, 'number', block.height, function (value) { block.height = Math.max(8, Math.min(120, Number(value || 24))); }));
		else if (block.type === 'products') {
			if (!config.woocommerce) { var unavailable = document.createElement('p'); unavailable.className = 'notice notice-warning inline'; unavailable.textContent = i18n.productsUnavailable; inspector.appendChild(unavailable); finishInspector(block, heading); return; }
			var sources = [['latest', i18n.latestProducts], ['category', i18n.category], ['producer', i18n.producer], ['featured', i18n.featured], ['sale', i18n.onSale], ['manual', i18n.manual]];
			inspector.appendChild(productSelect(i18n.productSource, block.source, sources, function (value) { block.source = value; updateProductBlock(block); renderInspector(); }));
			inspector.appendChild(pairedFields(productSelect(i18n.productCount, block.count, [[1,'1'],[2,'2'],[3,'3'],[4,'4'],[5,'5'],[6,'6']], function (value) { block.count = Number(value); updateProductBlock(block); }), productSelect(i18n.columns, block.columns, [[1,'1'],[2,'2'],[3,'3']], function (value) { block.columns = Number(value); updateProductBlock(block); })));
			inspector.appendChild(productSelect(i18n.imageRatio, block.image_ratio, [['original',i18n.originalFormat],['1:1',i18n.ratioSquare],['4:5',i18n.ratioPortrait],['4:3',i18n.ratioLandscape],['16:9','16:9']], function (value) { block.image_ratio = value; updateProductBlock(block); }));
			inspector.appendChild(toggleField(i18n.showPrice, block.show_price, function (checked) { block.show_price = checked; updateProductBlock(block); }));
			inspector.appendChild(toggleField(i18n.showCategories, block.show_categories, function (checked) { block.show_categories = checked; updateProductBlock(block); }));
			inspector.appendChild(toggleField(i18n.showBrands, block.show_brands, function (checked) { block.show_brands = checked; updateProductBlock(block); }));
			if (block.source === 'category') {
				if (!block.category_id && productFilters.categories.length) { block.category_id = Number(productFilters.categories[0].id); updateProductBlock(block); }
				inspector.appendChild(productSelect(i18n.productCategory, block.category_id, productFilters.categories.map(function (item) { return [item.id, item.name]; }), function (value) { block.category_id = Number(value); updateProductBlock(block); }));
			}
			if (block.source === 'producer') {
				if (!block.producer_taxonomy && productFilters.producers.length) block.producer_taxonomy = productFilters.producers[0].taxonomy;
				inspector.appendChild(productSelect(i18n.producerType, block.producer_taxonomy, productFilters.producers.map(function (item) { return [item.taxonomy, item.label]; }), function (value) { block.producer_taxonomy = value; block.producer_term_id = 0; updateProductBlock(block); renderInspector(); }));
				var producer = productFilters.producers.find(function (item) { return item.taxonomy === block.producer_taxonomy; });
				if (producer && !block.producer_term_id && producer.terms.length) { block.producer_term_id = Number(producer.terms[0].id); updateProductBlock(block); }
				if (producer) inspector.appendChild(productSelect(i18n.producerValue, block.producer_term_id, producer.terms.map(function (item) { return [item.id, item.name]; }), function (value) { block.producer_term_id = Number(value); updateProductBlock(block); }));
			}
			if (block.source === 'manual') inspector.appendChild(productSelect(i18n.manualProducts, block.product_ids, productFilters.products.map(function (item) { return [item.id, item.name]; }), function (values) { block.product_ids = values.slice(0, block.count); updateProductBlock(block); renderInspector(); }, true));
		} else if (block.type === 'events') {
			inspector.appendChild(productSelect(i18n.eventSource, block.source, [['upcoming',i18n.upcomingEvents],['current',i18n.currentEvents],['past',i18n.pastEvents],['all',i18n.allEvents],['manual',i18n.manual]], function (value) { block.source = value; sync(); render(); }));
			if (block.source === 'manual') inspector.appendChild(productSelect(i18n.selectEvents, block.event_ids, eventFilters.map(function (item) { return [item.id, item.name]; }), function (values) { block.event_ids = values.slice(0, 6); sync(); renderCanvas(); }, true));
			if (block.source !== 'manual') { inspector.appendChild(productSelect(i18n.eventCategory, block.category_id, [['0',i18n.all]].concat(eventCategories.map(function (item) { return [item.id,item.name]; })), function (value) { block.category_id = Number(value); sync(); renderCanvas(); })); inspector.appendChild(productSelect(i18n.onlyFeatured, block.featured_only ? '1' : '0', [['0',i18n.no],['1',i18n.yes]], function (value) { block.featured_only = value === '1'; sync(); renderCanvas(); })); }
			inspector.appendChild(pairedFields(productSelect(i18n.eventCount, block.count, [[1,'1'],[2,'2'],[3,'3'],[4,'4'],[5,'5'],[6,'6']], function (value) { block.count = Number(value); sync(); renderCanvas(); }), productSelect(i18n.columns, block.columns, [[1,'1'],[2,'2'],[3,'3']], function (value) { block.columns = Number(value); sync(); renderCanvas(); })));
			inspector.appendChild(productSelect(i18n.eventOrder, block.event_order, [['asc',i18n.eventOldest],['desc',i18n.eventNewest]], function (value) { block.event_order = value; sync(); renderCanvas(); }));
			inspector.appendChild(toggleField(i18n.showEventImage, block.show_image, function (checked) { block.show_image = checked; sync(); renderInspector(); renderCanvas(); })); inspector.appendChild(toggleField(i18n.showEventDate, block.show_date !== false, function (checked) { block.show_date = checked; sync(); renderCanvas(); })); inspector.appendChild(toggleField(i18n.showEventLocation, !!block.show_location, function (checked) { block.show_location = checked; sync(); renderCanvas(); })); inspector.appendChild(productSelect(i18n.textAlignment, block.text_alignment || 'left', [['left',i18n.left],['center',i18n.center]], function (value) { block.text_alignment = value; sync(); renderCanvas(); }));
			if (block.show_image) {
				inspector.appendChild(productSelect(i18n.imageFormat, block.image_format, [['fixed',i18n.fixedFormat],['original',i18n.originalFormat]], function (value) { block.image_format = value; sync(); renderInspector(); renderCanvas(); }));
				if (block.image_format === 'fixed') { inspector.appendChild(productSelect(i18n.imageRatio, block.image_ratio, [['1:1',i18n.ratioSquare],['4:5',i18n.ratioPortrait],['4:3',i18n.ratioLandscape],['16:9','16:9']], function (value) { block.image_ratio = value; sync(); renderCanvas(); })); inspector.appendChild(productSelect(i18n.eventImageMode, block.image_fit, [['cover',i18n.cover],['contain',i18n.contain]], function (value) { block.image_fit = value; sync(); renderCanvas(); })); }
			}
		} else if (block.type === 'posts') {
			var postType = selectedPostType(block);
			inspector.appendChild(productSelect(i18n.contentType, block.post_type, (postFilters.types || []).map(function (type) { return [type.id, type.name]; }), function (value) { block.post_type = value; var selectedType = selectedPostType(block); block.post_taxonomy = selectedType.taxonomy || ''; block.category_id = 0; block.post_ids = []; if (block.source === 'category' && !(selectedType.categories || []).length) block.source = 'latest'; sync(); render(); }));
			var postSources = [['latest',i18n.latestPosts],['manual',i18n.manualPostsChoice]]; if ((postType.categories || []).length) postSources.splice(1, 0, ['category',i18n.category]);
			inspector.appendChild(productSelect(i18n.postSource, block.source, postSources, function (value) { block.source = value; sync(); render(); }));
			inspector.appendChild(pairedFields(productSelect(i18n.postCount, block.count, [[1,'1'],[2,'2'],[3,'3'],[4,'4'],[5,'5'],[6,'6']], function (value) { block.count = Number(value); sync(); renderCanvas(); }), productSelect(i18n.columns, block.columns, [[1,'1'],[2,'2'],[3,'3']], function (value) { block.columns = Number(value); sync(); renderCanvas(); })));
			inspector.appendChild(productSelect(i18n.imageRatio, block.image_ratio, [['original',i18n.originalFormat],['1:1',i18n.ratioSquare],['4:5',i18n.ratioPortrait],['4:3',i18n.ratioLandscape],['16:9','16:9']], function (value) { block.image_ratio = value; sync(); renderCanvas(); }));
			inspector.appendChild(postVisibilityOptions(block));
			if (block.source === 'category') inspector.appendChild(productSelect(i18n.postCategory, block.category_id, (postType.categories || []).map(function (item) { return [item.id,item.name]; }), function (value) { block.category_id = Number(value); block.post_taxonomy = postType.taxonomy || ''; sync(); renderCanvas(); }));
			if (block.source === 'manual') inspector.appendChild(productSelect(i18n.manualPosts, block.post_ids, (postType.posts || []).map(function (item) { return [item.id,item.name]; }), function (values) { block.post_ids = values.slice(0, block.count); sync(); renderCanvas(); }, true));
		}
		finishInspector(block, heading);
	}
	function render() { renderCanvas(); renderInspector(); }
	document.querySelectorAll('.contactpanda-add-block').forEach(function (button) {
		button.addEventListener('click', function () { document.querySelectorAll('.contactpanda-add-block').forEach(function (item) { item.classList.remove('is-selected'); }); this.classList.add('is-selected'); });
		button.addEventListener('dblclick', function () { var block = defaultBlock(this.dataset.type); blocks.push(block); selectedId = block.id; inspectorTab = 'content'; render(); });
		button.addEventListener('dragstart', function (event) { event.dataTransfer.effectAllowed = 'copy'; event.dataTransfer.setData('contactpanda/type', this.dataset.type); });
	});
	container.addEventListener('dragover', function (event) { event.preventDefault(); });
	container.addEventListener('drop', function (event) { if (event.target.closest('.contactpanda-newsletter-block')) return; var type = event.dataTransfer.getData('contactpanda/type'); if (!type) return; var block = defaultBlock(type); blocks.push(block); selectedId = block.id; render(); });
	function request(action, extra) {
		var data = new FormData(); data.append('action', action); data.append('nonce', config.nonce); data.append('content_json', JSON.stringify(blocks));
		['subject', 'preview_text', 'from_name', 'reply_to', 'email_background', 'accent_color', 'content_background', 'text_color', 'font_family', 'content_width'].forEach(function (name) { data.append(name, form.elements[name] ? form.elements[name].value : ''); });
		Object.keys(extra || {}).forEach(function (key) { data.append(key, extra[key]); });
		return fetch(config.ajaxUrl, {method: 'POST', credentials: 'same-origin', body: data}).then(function (response) { return response.json().catch(function () { throw new Error('invalid-response'); }); });
	}
	var modal = document.getElementById('contactpanda-newsletter-preview-modal'); var frame = document.getElementById('contactpanda-newsletter-preview-frame');
	document.getElementById('contactpanda-preview-template').addEventListener('click', function () { request('contactpanda_newsletter_preview').then(function (result) { if (!result.success) throw new Error((result.data && result.data.message) || i18n.previewFailed); frame.srcdoc = result.data.html; frame.style.width = (Math.max(480, Math.min(760, Number(form.elements.content_width.value) || 600)) + 48) + 'px'; modal.hidden = false; document.body.classList.add('contactpanda-modal-open'); }).catch(function () { window.alert(i18n.previewFailed); }); });
	document.getElementById('contactpanda-close-newsletter-preview').addEventListener('click', function () { modal.hidden = true; document.body.classList.remove('contactpanda-modal-open'); });
	document.querySelectorAll('[data-preview-width]').forEach(function (button) { button.addEventListener('click', function () { document.querySelectorAll('[data-preview-width]').forEach(function (item) { item.classList.remove('is-active'); }); this.classList.add('is-active'); frame.style.width = this.dataset.previewWidth === 'email' ? (Math.max(480, Math.min(760, Number(form.elements.content_width.value) || 600)) + 48) + 'px' : this.dataset.previewWidth; }); });
	document.getElementById('contactpanda-send-test').addEventListener('click', function () {
		var button = this, message = document.getElementById('contactpanda-test-message'); button.disabled = true; message.className = 'contactpanda-newsletter-message'; message.textContent = i18n.sending;
		request('contactpanda_newsletter_test', {recipient: document.getElementById('contactpanda-test-email').value}).then(function (result) { if (!result.success) throw new Error((result.data && result.data.message) || i18n.testFailed); message.classList.add('is-success'); message.textContent = result.data.message; }).catch(function (error) { message.classList.add('is-error'); message.textContent = error.message === 'invalid-response' ? i18n.testFailed : error.message; }).finally(function () { button.disabled = false; });
	});
	form.addEventListener('submit', function () { var instance = window.tinymce && tinymce.get(editorId), block = selectedBlock(); if (instance && block && block.type === 'text') block.content = instance.getContent(); sync(); });
	render();
}());
