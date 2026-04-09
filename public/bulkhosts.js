(function () {
	'use strict';

	function cfg() {
		var el = document.getElementById('bulkhosts-page-json');
		if (!el || !el.textContent) {
			return {};
		}
		try {
			return JSON.parse(el.textContent);
		} catch (e) {
			return {};
		}
	}

	function selectedRadioInt(name) {
		var nodes = document.getElementsByName(name);
		for (var i = 0; i < nodes.length; i++) {
			if (nodes[i].checked) {
				return parseInt(nodes[i].value, 10);
			}
		}
		return 1;
	}

	function parseExtraMacros(text) {
		var out = [];
		if (!text) {
			return out;
		}
		text.split(/\r?\n/).forEach(function (line) {
			line = line.trim();
			if (!line || line[0] === '#') {
				return;
			}
			var eq = line.indexOf('=');
			if (eq < 1) {
				return;
			}
			out.push({
				macro: line.slice(0, eq).trim(),
				value: line.slice(eq + 1).trim()
			});
		});
		return out;
	}

	/**
	 * Values from Zabbix CMultiSelect (hidden inputs sharing the field name).
	 */
	function collectMultiValues(name) {
		var nodes = document.getElementsByName(name);
		var vals = [];
		var i;
		for (i = 0; i < nodes.length; i++) {
			var n = nodes[i];
			if (n.tagName === 'INPUT' && n.type === 'hidden' && n.value) {
				vals.push(n.value);
			}
		}
		return vals.filter(function (v, i, a) {
			return a.indexOf(v) === i;
		});
	}

	function post(operation, payload) {
		var c = cfg();
		var body = {
			operation: operation,
			payload: payload
		};
		if (c.csrf_token) {
			body.csrf_token = c.csrf_token;
		}
		return fetch(c.api_url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-Requested-With': 'XMLHttpRequest'
			},
			body: JSON.stringify(body)
		}).then(function (r) {
			return r.text();
		}).then(function (t) {
			try {
				return JSON.parse(t);
			} catch (e) {
				return { ok: false, error: t || 'Invalid JSON response' };
			}
		});
	}

	function flash(ok, msg) {
		var el = document.getElementById('bh-flash');
		if (!el) {
			return;
		}
		el.className = ok ? 'msg-good' : 'msg-bad';
		el.textContent = msg || '';
		el.style.display = msg ? 'block' : 'none';
	}

	function summarize(obj) {
		if (!obj || typeof obj !== 'object') {
			return 'Unexpected response.';
		}
		if (obj.ok === false && obj.error) {
			return obj.error;
		}
		if (obj.summary) {
			return obj.summary;
		}
		if (obj.errors && obj.errors.length) {
			return obj.errors.join('; ');
		}
		if (obj.missing && obj.missing.length) {
			return 'Not found: ' + obj.missing.join(', ');
		}
		if (obj.created && obj.created.length) {
			return 'Created ' + obj.created.length + ' host(s). Open Configuration → Hosts to review.';
		}
		if (obj.deleted && obj.deleted.length) {
			return 'Deleted ' + obj.deleted.length + ' host(s).';
		}
		return obj.ok ? 'Done.' : 'Done with warnings.';
	}

	function wireFileToTextarea(fileInputId, textareaId) {
		var fi = document.getElementById(fileInputId);
		var ta = document.getElementById(textareaId);
		if (!fi || !ta) {
			return;
		}
		fi.addEventListener('change', function () {
			var f = fi.files && fi.files[0];
			if (!f) {
				return;
			}
			var reader = new FileReader();
			reader.onload = function () {
				ta.value = reader.result;
				flash(true, 'Loaded file into list.');
			};
			reader.onerror = function () {
				flash(false, 'Could not read file.');
			};
			reader.readAsText(f);
			fi.value = '';
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		wireFileToTextarea('bh-c-file', 'bh-c-hosts');
		wireFileToTextarea('bh-d-file', 'bh-d-hosts');
		wireFileToTextarea('bh-m-file', 'bh-m-hosts');

		var btnCreate = document.getElementById('bh-btn-create');
		var btnDelete = document.getElementById('bh-btn-delete');
		var btnModify = document.getElementById('bh-btn-modify');

		if (btnCreate) {
			btnCreate.addEventListener('click', function () {
				var useIp = selectedRadioInt('bh_c_useip');
				post('create', {
					hosts_text: document.getElementById('bh-c-hosts').value,
					groupids: collectMultiValues('bh_c_groups[]'),
					templateids: collectMultiValues('bh_c_templates[]'),
					proxy_hostid: document.getElementById('bh-c-proxy').value,
					interface_type: parseInt(document.getElementById('bh-c-iface-type').value, 10),
					interface_port: document.getElementById('bh-c-port').value,
					use_ip: useIp,
					visible_same_as_technical: document.getElementById('bh-c-visible').checked ? 1 : 0,
					host_enabled: document.getElementById('bh-c-enabled').checked ? 1 : 0,
					macro_name: document.getElementById('bh-c-macro').value,
					add_ip_macro: document.getElementById('bh-c-add-macro').checked ? 1 : 0,
					extra_macros: parseExtraMacros(document.getElementById('bh-c-extra-macros').value)
				}).then(function (obj) {
					flash(!!obj.ok, summarize(obj));
				});
			});
		}

		if (btnDelete) {
			btnDelete.addEventListener('click', function () {
				post('delete', {
					hosts_text: document.getElementById('bh-d-hosts').value
				}).then(function (obj) {
					flash(!!obj.ok, summarize(obj));
				});
			});
		}

		if (btnModify) {
			btnModify.addEventListener('click', function () {
				post('update', {
					hosts_text: document.getElementById('bh-m-hosts').value,
					add_groupids: collectMultiValues('bh_m_add_groups[]'),
					remove_groupids: collectMultiValues('bh_m_remove_groups[]'),
					add_templateids: collectMultiValues('bh_m_add_templates[]'),
					remove_templateids: collectMultiValues('bh_m_remove_templates[]'),
					clear_templateids: collectMultiValues('bh_m_clear_templates[]'),
					proxy_hostid: document.getElementById('bh-m-proxy').value
				}).then(function (obj) {
					flash(!!obj.ok, summarize(obj));
				});
			});
		}
	});
})();
