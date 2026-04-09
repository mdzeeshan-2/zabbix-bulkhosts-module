<?php

namespace Modules\BulkHosts\Actions;

use API;
use CCsrfTokenHelper;
use CControllerResponseData;
use CMessageHelper;

class BulkHostApi extends BaseAction {

	protected $post_content_type = self::TYPE_JSON;

	protected function checkInput() {
		if ($this->request_method !== self::POST) {
			return false;
		}

		if (!$this->validateInput([
			'operation' => 'required|string|in create,delete,update'
		])) {
			return false;
		}

		$payload = $this->getInput('payload');

		return $payload !== null && (is_array($payload) || is_object($payload));
	}

	protected function doAction() {
		if (version_compare(ZABBIX_VERSION, '6.4.0', '>=')) {
			$token = $this->getInput('csrf_token', '');
			if (!CCsrfTokenHelper::check($token, 'bulkhosts')) {
				$this->jsonResponse(['ok' => false, 'error' => _('Incorrect CSRF token.')]);
				return;
			}
		}

		$operation = $this->getInput('operation', '');
		$payload = $this->getInput('payload', []);
		if (is_object($payload)) {
			$payload = json_decode(json_encode($payload), true);
		}
		if (!is_array($payload)) {
			$payload = [];
		}

		switch ($operation) {
			case 'create':
				$this->jsonResponse($this->opCreate($payload));
				break;

			case 'delete':
				$this->jsonResponse($this->opDelete($payload));
				break;

			case 'update':
				$this->jsonResponse($this->opUpdate($payload));
				break;

			default:
				$this->jsonResponse(['ok' => false, 'error' => _('Unknown operation.')]);
		}
	}

	protected function jsonResponse(array $data): void {
		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($data)]));
	}

	protected function parseHostLines(string $text): array {
		$rows = [];
		$lines = preg_split('/\r\n|\r|\n/', $text);

		foreach ($lines as $line) {
			$line = trim($line);
			if ($line === '' || (strlen($line) > 0 && $line[0] === '#')) {
				continue;
			}

			if (strpos($line, ',') !== false) {
				$parts = str_getcsv($line);
				$parts = array_map('trim', $parts);
				if (count($parts) >= 2) {
					$rows[] = ['host' => $parts[0], 'ip' => $parts[1]];
				}
				elseif (count($parts) === 1 && $parts[0] !== '') {
					$rows[] = $this->singleCellRow($parts[0]);
				}
			}
			else {
				$rows[] = $this->singleCellRow($line);
			}
		}

		return $rows;
	}

	protected function singleCellRow(string $cell): array {
		if (filter_var($cell, FILTER_VALIDATE_IP)) {
			$host = 'host-'.str_replace(['.', ':'], '-', $cell);

			return ['host' => $host, 'ip' => $cell];
		}

		return ['host' => $cell, 'ip' => ''];
	}

	protected function findHostids(array $rows): array {
		$out = [];
		$missing = [];
		$seen = [];

		foreach ($rows as $row) {
			$host = $row['host'];
			$ip = $row['ip'];
			$hostid = null;
			$label = $host;

			if ($host !== '') {
				$byName = API::Host()->get([
					'output' => ['hostid', 'host'],
					'filter' => ['host' => $host],
					'limit' => 1
				]);

				if ($byName) {
					$hostid = $byName[0]['hostid'];
					$label = $byName[0]['host'];
				}
			}

			if ($hostid === null && $ip !== '') {
				$ifs = API::HostInterface()->get([
					'output' => ['hostid'],
					'filter' => [
						'ip' => $ip,
						'type' => INTERFACE_TYPE_AGENT
					],
					'limit' => 10
				]);

				if (count($ifs) === 1) {
					$hostid = $ifs[0]['hostid'];
					$h = API::Host()->get([
						'output' => ['host'],
						'hostids' => [$hostid],
						'limit' => 1
					]);
					$label = $h ? $h[0]['host'] : $ip;
				}
				elseif (count($ifs) > 1) {
					$missing[] = sprintf('%s / %s (%s)', $host, $ip, _('multiple hosts use this agent IP'));
					continue;
				}
			}

			if ($hostid === null && $host === '' && $ip === '') {
				continue;
			}

			if ($hostid === null && filter_var($host, FILTER_VALIDATE_IP)) {
				$ifs = API::HostInterface()->get([
					'output' => ['hostid'],
					'filter' => [
						'ip' => $host,
						'type' => INTERFACE_TYPE_AGENT
					],
					'limit' => 2
				]);

				if (count($ifs) === 1) {
					$hostid = $ifs[0]['hostid'];
					$label = $host;
				}
			}

			if ($hostid === null) {
				$missing[] = $host !== '' ? $host : $ip;
				continue;
			}

			if (isset($seen[$hostid])) {
				continue;
			}
			$seen[$hostid] = true;

			$out[] = ['hostid' => $hostid, 'label' => $label];
		}

		return ['hostids' => $out, 'missing' => $missing];
	}

	protected function opCreate(array $p): array {
		$text = isset($p['hosts_text']) ? (string) $p['hosts_text'] : '';
		$rows = $this->parseHostLines($text);

		if (!$rows) {
			return ['ok' => false, 'error' => _('No host rows parsed. Use one IP per line or CSV: hostname,ip')];
		}

		$groupids = isset($p['groupids']) && is_array($p['groupids']) ? array_values(array_unique(array_filter($p['groupids']))) : [];
		$templateids = isset($p['templateids']) && is_array($p['templateids']) ? array_values(array_filter($p['templateids'])) : [];

		if (!$groupids) {
			return ['ok' => false, 'error' => _('Select a host group.')];
		}

		$interfaceType = isset($p['interface_type']) ? (int) $p['interface_type'] : INTERFACE_TYPE_AGENT;
		$port = isset($p['interface_port']) ? (string) $p['interface_port'] : '10050';
		$useip = isset($p['use_ip']) ? (int) $p['use_ip'] : 1;
		$dns = isset($p['dns']) ? (string) $p['dns'] : '';

		$proxyHostid = isset($p['proxy_hostid']) ? (string) $p['proxy_hostid'] : '0';
		if ($proxyHostid === '' || $proxyHostid === '0') {
			$proxyHostid = null;
		}

		$macroName = isset($p['macro_name']) ? trim((string) $p['macro_name']) : '{$IPADDRESSES}';
		if ($macroName === '') {
			$macroName = '{$IPADDRESSES}';
		}

		$addIpMacro = !array_key_exists('add_ip_macro', $p) || (int) $p['add_ip_macro'] === 1;
		$visibleSameAsTechnical = !empty($p['visible_same_as_technical']);

		$groups = [];
		foreach ($groupids as $gid) {
			$groups[] = ['groupid' => (string) $gid];
		}

		$templates = [];
		foreach ($templateids as $tid) {
			$templates[] = ['templateid' => (string) $tid];
		}

		$extraMacros = [];
		if (!empty($p['extra_macros']) && is_array($p['extra_macros'])) {
			foreach ($p['extra_macros'] as $m) {
				if (empty($m['macro']) || !isset($m['value'])) {
					continue;
				}
				$extraMacros[] = [
					'macro' => (string) $m['macro'],
					'value' => (string) $m['value']
				];
			}
		}

		$created = [];
		$errors = [];

		foreach ($rows as $row) {
			$tech = $row['host'];
			$ip = $row['ip'];

			if ($tech === '') {
				$errors[] = _('Row skipped: empty host name.');
				continue;
			}

			if ($ip === '' && $useip == 1) {
				$errors[] = sprintf('%s: %s', $tech, _('IP is required for IP-based agent interfaces.'));
				continue;
			}

			$iface = [
				'type' => $interfaceType,
				'main' => 1,
				'useip' => $useip,
				'ip' => $useip ? $ip : '',
				'dns' => $useip ? $dns : $tech,
				'port' => $port
			];

			$macros = [];
			if ($addIpMacro) {
				$macros[] = ['macro' => $macroName, 'value' => $ip !== '' ? $ip : $tech];
			}
			foreach ($extraMacros as $em) {
				$val = str_replace(
					['{{ROW_IP}}', '{{ROW_HOST}}'],
					[$ip, $tech],
					$em['value']
				);
				$macros[] = ['macro' => $em['macro'], 'value' => $val];
			}

			$obj = [
				'host' => $tech,
				'interfaces' => [$iface],
				'groups' => $groups
			];

			$enabled = !array_key_exists('host_enabled', $p) || (int) $p['host_enabled'] === 1;
			$obj['status'] = $enabled ? HOST_STATUS_MONITORED : HOST_STATUS_NOT_MONITORED;

			if ($macros) {
				$obj['macros'] = $macros;
			}

			if ($visibleSameAsTechnical) {
				$obj['name'] = $tech;
			}

			if ($templates) {
				$obj['templates'] = $templates;
			}

			if ($proxyHostid !== null) {
				$obj['proxy_hostid'] = $proxyHostid;
			}

			$result = API::Host()->create([$obj]);

			if ($result) {
				$created[] = ['host' => $tech, 'hostid' => $result['hostids'][0]];
			}
			else {
				$errors[] = sprintf('%s: %s', $tech, $this->apiErrorMessage());
			}
		}

		return [
			'ok' => !count($errors),
			'created' => $created,
			'errors' => $errors,
			'summary' => sprintf(_('Created %1$d host(s), %2$d error(s).'), count($created), count($errors))
		];
	}

	protected function opDelete(array $p): array {
		$text = isset($p['hosts_text']) ? (string) $p['hosts_text'] : '';
		$rows = $this->parseHostLines($text);

		if (!$rows) {
			return ['ok' => false, 'error' => _('No rows to process.')];
		}

		$res = $this->findHostids($rows);
		$ids = array_unique(array_column($res['hostids'], 'hostid'));

		if (!$ids) {
			return [
				'ok' => false,
				'error' => _('No matching hosts.'),
				'missing' => $res['missing']
			];
		}

		$result = API::Host()->delete($ids);

		if ($result) {
			return [
				'ok' => empty($res['missing']),
				'deleted' => $ids,
				'missing' => $res['missing'],
				'summary' => sprintf(_('Deleted %d host(s).'), count($ids))
			];
		}

		return ['ok' => false, 'error' => $this->apiErrorMessage(), 'missing' => $res['missing']];
	}

	protected function opUpdate(array $p): array {
		$text = isset($p['hosts_text']) ? (string) $p['hosts_text'] : '';
		$rows = $this->parseHostLines($text);

		if (!$rows) {
			return ['ok' => false, 'error' => _('No rows to process.')];
		}

		$res = $this->findHostids($rows);
		$hostids = array_unique(array_column($res['hostids'], 'hostid'));

		if (!$hostids) {
			return [
				'ok' => false,
				'error' => _('No matching hosts.'),
				'missing' => $res['missing']
			];
		}

		$log = [];
		$errors = [];

		$hostsParam = [];
		foreach ($hostids as $hid) {
			$hostsParam[] = ['hostid' => $hid];
		}

		$addGroupids = isset($p['add_groupids']) && is_array($p['add_groupids']) ? array_filter($p['add_groupids']) : [];
		$removeGroupids = isset($p['remove_groupids']) && is_array($p['remove_groupids']) ? array_filter($p['remove_groupids']) : [];
		$addTemplateids = isset($p['add_templateids']) && is_array($p['add_templateids']) ? array_filter($p['add_templateids']) : [];
		$removeTemplateids = isset($p['remove_templateids']) && is_array($p['remove_templateids']) ? array_filter($p['remove_templateids']) : [];
		$clearTemplateids = isset($p['clear_templateids']) && is_array($p['clear_templateids']) ? array_filter($p['clear_templateids']) : [];

		$proxyHostid = array_key_exists('proxy_hostid', $p) ? (string) $p['proxy_hostid'] : null;
		$setProxy = $proxyHostid !== null && $proxyHostid !== '__skip__';

		if ($addGroupids || $addTemplateids) {
			$mass = ['hosts' => $hostsParam];

			if ($addGroupids) {
				$mass['groups'] = [];
				foreach ($addGroupids as $gid) {
					$mass['groups'][] = ['groupid' => (string) $gid];
				}
			}

			if ($addTemplateids) {
				$mass['templates'] = [];
				foreach ($addTemplateids as $tid) {
					$mass['templates'][] = ['templateid' => (string) $tid];
				}
			}

			if (!API::Host()->massadd($mass)) {
				$errors[] = _('Host massadd failed: ').$this->apiErrorMessage();
			}
			else {
				$log[] = _('Applied massadd (groups/templates).');
			}
		}

		if ($removeGroupids || $removeTemplateids || $clearTemplateids) {
			$mass = ['hostids' => $hostids];

			if ($removeGroupids) {
				$mass['groupids'] = array_values($removeGroupids);
			}

			if ($removeTemplateids) {
				$mass['templateids'] = array_values($removeTemplateids);
			}

			if ($clearTemplateids) {
				$mass['templateids_clear'] = array_values($clearTemplateids);
			}

			if (!API::Host()->massremove($mass)) {
				$errors[] = _('Host massremove failed: ').$this->apiErrorMessage();
			}
			else {
				$log[] = _('Applied massremove (unlink groups/templates).');
			}
		}

		if ($setProxy) {
			$pid = ($proxyHostid === '' || $proxyHostid === '0') ? '0' : $proxyHostid;

			foreach ($hostids as $hid) {
				if (!API::Host()->update([
					'hostid' => $hid,
					'proxy_hostid' => $pid
				])) {
					$errors[] = sprintf('hostid %s: %s', $hid, $this->apiErrorMessage());
				}
			}

			if (!$errors || count($errors) < count($hostids)) {
				$log[] = _('Updated proxy assignment.');
			}
		}

		return [
			'ok' => !count($errors),
			'affected' => count($hostids),
			'missing' => $res['missing'],
			'log' => $log,
			'errors' => $errors,
			'summary' => sprintf(_('Processed %d host(s).'), count($hostids))
		];
	}

	protected function apiErrorMessage(): string {
		$parts = [];
		foreach (CMessageHelper::getMessages() as $m) {
			if (isset($m['type'], $m['message']) && $m['type'] === CMessageHelper::MESSAGE_TYPE_ERROR) {
				$parts[] = $m['message'];
			}
		}

		return $parts ? implode('; ', $parts) : _('Zabbix API error.');
	}
}
