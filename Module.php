<?php

namespace Modules\BulkHosts;

if (version_compare(ZABBIX_VERSION, '6.4.0', '>')) {
	class_exists('\Core\CModule', false) or class_alias('\Zabbix\Core\CModule', '\Core\CModule');
	class_exists('\CWidget', false) or class_alias('\CHtmlPage', '\CWidget');
}

use APP;
use CMenu;
use CWebUser;
use Core\CModule as CModule;
use CController as CAction;
use CMenuItem;
use Modules\BulkHosts\Actions\BaseAction;

class Module extends CModule {

	public function init(): void {
		// Zabbix 6.0 uses CHtmlPage; CWidget exists from 6.4+ in many builds. SqlExplorer only aliases for >6.4.
		if (!class_exists('\CWidget', false) && class_exists('\CHtmlPage', false)) {
			class_alias('\CHtmlPage', '\CWidget');
		}

		$this->registerMenuEntry();
	}

	public function onBeforeAction(CAction $action): void {
		if (is_a($action, BaseAction::class)) {
			$action->module = $this;
		}
	}

	public function onTerminate(CAction $action): void {
	}

	public function getAssetsUrl(): string {
		return version_compare(ZABBIX_VERSION, '6.4', '>=')
			? $this->getRelativePath().'/public/'
			: 'modules/'.basename($this->getDir()).'/public/';
	}

	protected function registerMenuEntry(): void {
		if (!in_array(CWebUser::getType(), [USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN], true)) {
			return;
		}

		/** @var CMenu $menu */
		$menu = APP::Component()->get('menu.main');
		$entry = (new CMenuItem(_('Bulk host manager')))->setAction('bulkhosts.form');

		$parent = $menu->find(_('Configuration'));
		if ($parent !== null) {
			$parent->getSubMenu()->add($entry);

			return;
		}

		$parent = $menu->find(_('Administration'));
		if ($parent !== null) {
			$parent->getSubMenu()->add($entry);
		}
	}
}
