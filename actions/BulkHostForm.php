<?php

namespace Modules\BulkHosts\Actions;

use API;
use CControllerResponseData;

class BulkHostForm extends BaseAction {

	protected function checkInput() {
		return true;
	}

	protected function doAction() {
		$proxies = API::Proxy()->get([
			'output' => ['proxyid', 'host']
		]);
		if (!is_array($proxies)) {
			$proxies = [];
		}

		$data = [
			'proxies' => $proxies,
			'csrf_token' => $this->getActionCsrfToken('bulkhosts'),
			'public_path' => $this->module->getAssetsUrl()
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Bulk host manager'));

		$this->setResponse($response);
	}
}
