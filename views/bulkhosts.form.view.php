<?php

use Modules\BulkHosts\Helpers\Html\JsonDataTag;
use Modules\BulkHosts\Helpers\Html\ScriptTag;
use Modules\BulkHosts\Helpers\Html\StyleTag;

/**
 * CTabView reads the global cookie "tab". If it is out of range for this widget (3 tabs),
 * no panel matches and every tab body stays display:none — the page looks empty.
 */
if (isset($_COOKIE['tab'])) {
	$__bh_tab = (int) $_COOKIE['tab'];
	if ($__bh_tab < 0 || $__bh_tab > 2) {
		$_COOKIE['tab'] = '0';
	}
}
unset($__bh_tab);

$token_name = '';
if (version_compare(ZABBIX_VERSION, '6.4.0', '>=')) {
	$token_name = CCsrfTokenHelper::CSRF_TOKEN_NAME;
}

$pageJson = [
	'csrf_token' => $data['csrf_token'],
	'csrf_name' => $token_name,
	'api_url' => (new CUrl('zabbix.php'))->setArgument('action', 'bulkhosts.api')->getUrl()
];

$widget = (new CWidget())->setTitle(_('Bulk host manager'));

$hostListUrl = (new CUrl('zabbix.php'))->setArgument('action', 'host.list');

$flash = (new CDiv())
	->setId('bh-flash')
	->addClass(ZBX_STYLE_DISPLAY_NONE);

if (defined('ZBX_TEXT_BOX_SMALL_WIDTH')) {
	$portWidth = (int) (8 * constant('ZBX_TEXT_BOX_SMALL_WIDTH'));
}
elseif (defined('ZBX_TEXT_BOX_STANDARD_WIDTH')) {
	$portWidth = (int) (4 * constant('ZBX_TEXT_BOX_STANDARD_WIDTH'));
}
else {
	$portWidth = 240;
}

$proxies = $data['proxies'];

$msWidth = defined('ZBX_TEXTAREA_STANDARD_WIDTH') ? (int) constant('ZBX_TEXTAREA_STANDARD_WIDTH') : 460;
$textareaBig = defined('ZBX_TEXTAREA_BIG_WIDTH') ? (int) constant('ZBX_TEXTAREA_BIG_WIDTH') : 450;
$textareaStd = defined('ZBX_TEXTAREA_STANDARD_WIDTH') ? (int) constant('ZBX_TEXTAREA_STANDARD_WIDTH') : 300;

$bulkFormName = 'bulkhosts_form';

/**
 * Native Zabbix multiselect for host groups (search + Select popup).
 */
function bh_multiselect_host_groups(string $fieldName, string $dstfldPrefix, array $data, string $formName, int $width) {
	$params = [
		'name' => $fieldName,
		'object_name' => 'hostGroup',
		'data' => $data,
		'popup' => [
			'parameters' => [
				'srctbl' => 'host_groups',
				'srcfld1' => 'groupid',
				'dstfrm' => $formName,
				'dstfld1' => $dstfldPrefix,
				'editable' => true
			]
		]
	];

	if (class_exists('CWebUser') && isset(CWebUser::$data['type'])) {
		$params['add_new'] = (CWebUser::$data['type'] == USER_TYPE_SUPER_ADMIN);
	}

	return (new CMultiSelect($params))->setWidth($width);
}

/**
 * Native Zabbix multiselect for templates (search + Select popup).
 */
function bh_multiselect_templates(string $fieldName, string $dstfldPrefix, array $data, string $formName, int $width) {
	return (new CMultiSelect([
		'name' => $fieldName,
		'object_name' => 'templates',
		'data' => $data,
		'popup' => [
			'parameters' => [
				'srctbl' => 'templates',
				'srcfld1' => 'hostid',
				'srcfld2' => 'host',
				'dstfrm' => $formName,
				'dstfld1' => $dstfldPrefix
			]
		]
	]))->setWidth($width);
}

$ifaceTypeSelect = (new CSelect('bh_iface_type_unused'))
	->setId('bh-c-iface-type');
foreach ([
	INTERFACE_TYPE_AGENT => _('Zabbix agent'),
	INTERFACE_TYPE_SNMP => _('SNMP'),
	INTERFACE_TYPE_JMX => _('JMX'),
	INTERFACE_TYPE_IPMI => _('IPMI')
] as $tid => $tlabel) {
	$ifaceTypeSelect->addOption(new CSelectOption((string) $tid, $tlabel));
}

$proxyCreate = (new CSelect('bh_unused_proxy_c'))->setId('bh-c-proxy');
$proxyCreate->addOption(new CSelectOption('0', _('(no proxy)')));
foreach ($proxies as $px) {
	if (isset($px['proxyid'], $px['host'])) {
		$proxyCreate->addOption(new CSelectOption((string) $px['proxyid'], $px['host']));
	}
}

$proxyModify = (new CSelect('bh_unused_proxy_m'))->setId('bh-m-proxy');
$proxyModify->addOption(new CSelectOption('__skip__', _('Do not change')));
$proxyModify->addOption(new CSelectOption('0', _('Monitored by server')));
foreach ($proxies as $px) {
	if (isset($px['proxyid'], $px['host'])) {
		$proxyModify->addOption(new CSelectOption((string) $px['proxyid'], $px['host']));
	}
}

$fileInputCreate = (new CTag('input', false))
	->setAttribute('type', 'file')
	->setAttribute('accept', '.csv,.txt,text/csv')
	->setId('bh-c-file')
	->setAttribute('title', _('Load list from file'));

$fileInputDelete = (new CTag('input', false))
	->setAttribute('type', 'file')
	->setAttribute('accept', '.csv,.txt,text/csv')
	->setId('bh-d-file')
	->setAttribute('title', _('Load list from file'));

$fileInputModify = (new CTag('input', false))
	->setAttribute('type', 'file')
	->setAttribute('accept', '.csv,.txt,text/csv')
	->setId('bh-m-file')
	->setAttribute('title', _('Load list from file'));

$hostFields = new CFormList();
$hostFields->addRow(
	new CLabel(_('Host list'), 'bh-c-hosts'),
	(new CDiv([
		(new CTextArea('bh_c_hosts', ''))
			->setId('bh-c-hosts')
			->setWidth($textareaBig)
			->setRows(8)
			->setAttribute('placeholder', "192.0.2.10\nweb-01,192.0.2.11"),
		(new CDiv([
			(new CLabel(_('Load from file'), 'bh-c-file'))->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			$fileInputCreate
		]))->addClass('bulkhosts-file-row')
	]))
);
$hostFields->addRow(
	new CLabel(_('Templates'), 'bh_c_templates__ms'),
	bh_multiselect_templates('bh_c_templates[]', 'bh_c_templates_', [], $bulkFormName, $msWidth)
);
$hostFields->addRow(
	(new CLabel(_('Groups'), 'bh_c_groups__ms'))->setAsteriskMark(),
	bh_multiselect_host_groups('bh_c_groups[]', 'bh_c_groups_', [], $bulkFormName, $msWidth)
);
$hostFields->addRow(new CLabel(_('Monitored by proxy'), 'bh-c-proxy'), $proxyCreate);
$hostFields->addRow(new CLabel(_('Interface type'), 'bh-c-iface-type'), $ifaceTypeSelect);
$hostFields->addRow(
	new CLabel(_('Connect via'), 'bh-c-useip'),
	(new CRadioButtonList('bh_c_useip', 1))
		->addValue(_('IP'), 1)
		->addValue(_('DNS name'), 0)
);
$hostFields->addRow(
	new CLabel(_('Agent port'), 'bh-c-port'),
	(new CTextBox('bh_c_port', '10050'))->setWidth($portWidth)->setId('bh-c-port')
);
$hostFields->addRow(
	new CLabel(_('Visible name equals host name'), 'bh-c-visible'),
	(new CCheckBox('bh_c_visible'))->setAttribute('id', 'bh-c-visible')->setChecked(true)
);
$hostFields->addRow(
	new CLabel(_('Enabled'), 'bh-c-enabled'),
	(new CCheckBox('bh_c_enabled'))->setAttribute('id', 'bh-c-enabled')->setChecked(true)
);

$macrosFields = new CFormList();
$macrosFields->addRow(
	new CLabel(_('Host macro for IP'), 'bh-c-macro'),
	(new CTextBox('bh_c_macro', '{$IPADDRESSES}'))->setWidth($textareaStd)->setId('bh-c-macro')
);
$macrosFields->addRow(
	new CLabel(_('Set macro to interface IP'), 'bh-c-add-macro'),
	(new CCheckBox('bh_c_add_macro'))->setAttribute('id', 'bh-c-add-macro')->setChecked(true)
);
$macrosFields->addRow(
	new CLabel(_('Extra macros'), 'bh-c-extra-macros'),
	(new CTextArea('bh_c_extra', ''))
		->setId('bh-c-extra-macros')
		->setWidth($textareaBig)
		->setRows(5)
		->setAttribute('placeholder', '{$SITE}=DC1\n{$ROLE}=web')
);

// Same footer pattern as core Zabbix forms (makeFormFooter): primary + Cancel (outline).
$footerCreate = makeFormFooter(
	(new CButton('bh_btn_create', _('Add')))
		->setId('bh-btn-create')
		->setAttribute('type', 'button'),
	[(new CRedirectButton(_('Cancel'), $hostListUrl))]
);

$footerDelete = makeFormFooter(
	(new CButton('bh_btn_delete', _('Delete')))
		->setId('bh-btn-delete')
		->setAttribute('type', 'button'),
	[(new CRedirectButton(_('Cancel'), $hostListUrl))]
);

$footerModify = makeFormFooter(
	(new CButton('bh_btn_modify', _('Update')))
		->setId('bh-btn-modify')
		->setAttribute('type', 'button'),
	[(new CRedirectButton(_('Cancel'), $hostListUrl))]
);

// Nested CTabView shares the same global "tab" cookie as the main tabs and can hide both inner panels.
$createWrap = (new CDiv([
	(new CTag('h4', true, _('Host'))),
	$hostFields,
	(new CTag('h4', true, _('Macros')))->addClass('bulkhosts-subhead'),
	$macrosFields,
	(new CDiv($footerCreate))->addClass('bulkhosts-form-footer')
]))->addClass('bulkhosts-create-sections');

$deleteList = new CFormList();
$deleteList->addRow(
	new CLabel(_('Hosts'), 'bh-d-hosts'),
	(new CDiv([
		(new CTextArea('bh_d_hosts', ''))
			->setId('bh-d-hosts')
			->setWidth($textareaBig)
			->setRows(8),
		(new CDiv([
			(new CLabel(_('Load from file'), 'bh-d-file'))->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			$fileInputDelete
		]))->addClass('bulkhosts-file-row')
	]))
);
$deleteWrap = (new CDiv([
	$deleteList,
	(new CDiv($footerDelete))->addClass('bulkhosts-form-footer')
]));

$modifyList = new CFormList();
$modifyList->addRow(
	new CLabel(_('Hosts'), 'bh-m-hosts'),
	(new CDiv([
		(new CTextArea('bh_m_hosts', ''))
			->setId('bh-m-hosts')
			->setWidth($textareaBig)
			->setRows(8),
		(new CDiv([
			(new CLabel(_('Load from file'), 'bh-m-file'))->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			$fileInputModify
		]))->addClass('bulkhosts-file-row')
	]))
);
$modifyList->addRow(
	new CLabel(_('Add to group(s)'), 'bh_m_add_groups__ms'),
	bh_multiselect_host_groups('bh_m_add_groups[]', 'bh_m_add_groups_', [], $bulkFormName, $msWidth)
);
$modifyList->addRow(
	new CLabel(_('Remove from group(s)'), 'bh_m_remove_groups__ms'),
	bh_multiselect_host_groups('bh_m_remove_groups[]', 'bh_m_remove_groups_', [], $bulkFormName, $msWidth)
);
$modifyList->addRow(
	new CLabel(_('Link template(s)'), 'bh_m_add_templates__ms'),
	bh_multiselect_templates('bh_m_add_templates[]', 'bh_m_add_templates_', [], $bulkFormName, $msWidth)
);
$modifyList->addRow(
	new CLabel(_('Unlink template(s)'), 'bh_m_remove_templates__ms'),
	bh_multiselect_templates('bh_m_remove_templates[]', 'bh_m_remove_templates_', [], $bulkFormName, $msWidth)
);
$modifyList->addRow(
	new CLabel(_('Unlink and clear template(s)'), 'bh_m_clear_templates__ms'),
	bh_multiselect_templates('bh_m_clear_templates[]', 'bh_m_clear_templates_', [], $bulkFormName, $msWidth)
);
$modifyList->addRow(new CLabel(_('Proxy'), 'bh-m-proxy'), $proxyModify);

$modifyWrap = (new CDiv([
	$modifyList,
	(new CDiv($footerModify))->addClass('bulkhosts-form-footer')
]));

$tabs = (new CTabView(['id' => 'bulkhosts_tabs', 'selected' => 0]))
	->addTab('bulkhosts_tab_create', _('Create hosts'), $createWrap)
	->addTab('bulkhosts_tab_delete', _('Delete hosts'), $deleteWrap)
	->addTab('bulkhosts_tab_modify', _('Modify hosts'), $modifyWrap);

$styles = <<<'CSS'
.bulkhosts-create-sections { margin-top: 4px; }
.bulkhosts-create-sections h4 { margin: 16px 0 8px 0; font-size: 1em; font-weight: bold; }
.bulkhosts-create-sections h4:first-child { margin-top: 0; }
.bulkhosts-subhead { margin-top: 20px; }
.bulkhosts-file-row { margin-top: 8px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.bulkhosts-form-footer { margin-top: 16px; }
.bulkhosts-form-footer .tfoot-buttons { display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end; align-items: center; }
#bh-flash { margin: 0 0 12px 0; padding: 8px 12px; border-radius: 2px; }
#bh-flash.msg-good { background: var(--severity-color-na-bg, #e8f5e9); border: 1px solid #a5d6a7; }
#bh-flash.msg-bad { background: var(--severity-color-high-bg, #ffebee); border: 1px solid #ef9a9a; }
CSS;

$mainForm = (new CForm())
	->setName($bulkFormName)
	->setId('bulkhosts-main-form')
	->setAttribute('onsubmit', 'return false;')
	->addItem($flash)
	->addItem(new JsonDataTag('bulkhosts-page-json', $pageJson))
	->addItem($tabs);

$widget
	->addItem(new StyleTag($styles))
	->addItem($mainForm)
	->addItem((new ScriptTag())->setAttribute('src', $data['public_path'].'bulkhosts.js'))
	->show();
