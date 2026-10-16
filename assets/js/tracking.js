(function () {
	'use strict';

	var settings = window.mrnfTracking || {};
	if (!settings.collectAttribution && !settings.analyticsEvents) {
		return;
	}

	var storageKey = 'mrnf_first_touch_v1';
	var searchSources = [
		{ pattern: /(^|\.)google\./, source: 'google' },
		{ pattern: /(^|\.)bing\.com$/, source: 'bing' },
		{ pattern: /(^|\.)search\.yahoo\.com$/, source: 'yahoo' },
		{ pattern: /(^|\.)duckduckgo\.com$/, source: 'duckduckgo' }
	];
	var socialSources = [
		{ pattern: /(^|\.)facebook\.com$|(^|\.)fb\.com$/, source: 'facebook' },
		{ pattern: /(^|\.)instagram\.com$/, source: 'instagram' },
		{ pattern: /(^|\.)linkedin\.com$/, source: 'linkedin' },
		{ pattern: /(^|\.)twitter\.com$|(^|\.)t\.co$|(^|\.)x\.com$/, source: 'twitter' },
		{ pattern: /(^|\.)youtube\.com$|(^|\.)youtu\.be$/, source: 'youtube' }
	];

	function cleanUrl(value) {
		if (!value) {
			return '';
		}
		try {
			var url = new URL(value, window.location.href);
			if (url.protocol !== 'http:' && url.protocol !== 'https:') {
				return '';
			}
			return url.origin + url.pathname;
		} catch (error) {
			return '';
		}
	}

	function findSource(hostname, candidates) {
		for (var index = 0; index < candidates.length; index += 1) {
			if (candidates[index].pattern.test(hostname)) {
				return candidates[index].source;
			}
		}
		return '';
	}

	function freshAttribution() {
		var query = new URLSearchParams(window.location.search);
		var source = query.get('utm_source') || '';
		var medium = query.get('utm_medium') || '';
		var campaign = query.get('utm_campaign') || '';
		var referrer = cleanUrl(document.referrer);

		if (!source && query.get('gclid')) {
			source = 'google';
			medium = 'cpc';
		} else if (!source && query.get('msclkid')) {
			source = 'bing';
			medium = 'cpc';
		} else if (!source && query.get('fbclid')) {
			source = 'facebook';
			medium = 'paid_social';
		}

		if (!source && referrer) {
			try {
				var referrerHost = new URL(referrer).hostname.toLowerCase();
				if (referrerHost !== window.location.hostname.toLowerCase()) {
					source = findSource(referrerHost, searchSources);
					if (source) {
						medium = 'organic';
					} else {
						source = findSource(referrerHost, socialSources);
						if (source) {
							medium = 'social';
						} else {
							source = referrerHost.replace(/^www\./, '');
							medium = 'referral';
						}
					}
				}
			} catch (error) {
				referrer = '';
			}
		}

		if (!source) {
			source = 'direct';
			medium = '(none)';
		}

		return {
			source: source.slice(0, 190),
			medium: medium.slice(0, 100),
			campaign: campaign.slice(0, 190),
			term: (query.get('utm_term') || '').slice(0, 190),
			content: (query.get('utm_content') || '').slice(0, 190),
			landing_page: cleanUrl(window.location.href),
			referrer: referrer,
			captured_at: new Date().toISOString()
		};
	}

	function loadAttribution() {
		var saved = '';
		try {
			saved = window.sessionStorage.getItem(storageKey) || '';
			if (saved) {
				return JSON.parse(saved);
			}
		} catch (error) {
			saved = '';
		}

		var attribution = freshAttribution();
		try {
			window.sessionStorage.setItem(storageKey, JSON.stringify(attribution));
		} catch (error) {
			// Storage can be unavailable in private contexts; the in-memory value remains usable.
		}
		return attribution;
	}

	function syncForms() {
		document.querySelectorAll('input[name="_mrnf_attribution"]').forEach(function (input) {
			input.value = JSON.stringify(window.mrnfAttribution);
		});
	}

	function sendEvent(name, parameters) {
		if (!settings.analyticsEvents || typeof window.gtag !== 'function') {
			return;
		}
		window.gtag('event', name, parameters);
	}

	window.mrnfAttribution = settings.collectAttribution ? loadAttribution() : {};
	if (settings.collectAttribution) {
		syncForms();
		document.addEventListener('submit', syncForms, true);
	}

	document.addEventListener('click', function (event) {
		var link = event.target.closest ? event.target.closest('a[href]') : null;
		if (!link) {
			return;
		}

		var href = link.getAttribute('href') || '';
		var normalized = href.toLowerCase();
		var eventName = '';
		var method = '';
		if (normalized.indexOf('tel:') === 0) {
			eventName = 'phone_click';
			method = 'phone';
		} else if (
			normalized.indexOf('whatsapp:') === 0 ||
			normalized.indexOf('https://wa.me/') === 0 ||
			normalized.indexOf('http://wa.me/') === 0 ||
			normalized.indexOf('api.whatsapp.com/') !== -1 ||
			normalized.indexOf('web.whatsapp.com/') !== -1
		) {
			eventName = 'whatsapp_click';
			method = 'whatsapp';
		}

		if (!eventName) {
			return;
		}

		sendEvent(eventName, {
			contact_method: method,
			page_location: cleanUrl(window.location.href),
			transport_type: 'beacon'
		});
	}, true);

	document.addEventListener('mrnf:success', function (event) {
		var form = event.target;
		sendEvent('lead_form_submit', {
			form_id: form && form.dataset ? form.dataset.mrnfForm || '' : '',
			page_location: cleanUrl(window.location.href),
			traffic_source: window.mrnfAttribution.source || '',
			traffic_medium: window.mrnfAttribution.medium || ''
		});
	});
}());
