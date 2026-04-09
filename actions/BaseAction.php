<?php

namespace Modules\BulkHosts\Actions;

use CWebUser;
use CCsrfTokenHelper;
use CController as Action;

abstract class BaseAction extends Action {

	const GET = 'get';
	const POST = 'post';

	protected const TYPE_FORM_URLENCODED = 0;
	protected const TYPE_JSON = 1;

	/** @var \Modules\BulkHosts\Module $module */
	public $module;

	protected $post_content_type = self::TYPE_FORM_URLENCODED;

	protected $request_method = self::GET;

	public function init() {
		$this->request_method = strtolower($_SERVER['REQUEST_METHOD']);

		if ($this->request_method === self::GET) {
			$this->disableSIDvalidation();
		}

		if (version_compare(ZABBIX_VERSION, '6.0', '<')) {
			if ($this->post_content_type == self::TYPE_JSON) {
				$_REQUEST = array_merge($_REQUEST, json_decode(file_get_contents('php://input'), true) ?: []);
			}
		}
		else {
			$this->setPostContentType($this->post_content_type);
		}
	}

	protected function checkPermissions() {
		return in_array(CWebUser::getType(), [USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN], true);
	}

	public function disableSIDvalidation() {
		if (version_compare(ZABBIX_VERSION, '6.4.0', '<')) {
			return parent::disableSIDvalidation();
		}

		return parent::disableCsrfValidation();
	}

	protected function getActionCsrfToken(string $action): string {
		if (version_compare(ZABBIX_VERSION, '6.4.0', '<')) {
			return '';
		}

		return CCsrfTokenHelper::get($action);
	}
}
