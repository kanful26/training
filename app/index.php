<?php
/**
 * 研修環境エントリーポイント
 *
 * 本番では MODx CMS のリソースから [!InformationManager?...!] が呼ばれる。
 * 研修ではこのファイルがその呼び出しを再現する。
 *
 * 使い方:
 *   /                          → デフォルトの一覧表示（mode=info_list）
 *   /?mode=info_list           → 一覧 HTML
 *   /?mode=json_get_info_data  → JSON 取得
 *   /?mode=info_list&search_text=朝礼&search_date_from=2026-04-01&search_date_to=2026-04-30
 */

require_once __DIR__ . '/bootstrap.php';

// パラメータ収集
$im = new InformationManager();
foreach ($_REQUEST as $k => $v) {
	$im->set_param($k, $v);
}

// デフォルト
if (empty($im->parameters['mode'])) {
	$im->set_param('mode', 'info_list');
}
if (empty($im->parameters['list_tpl'])) {
	$im->set_param('list_tpl', '@FILE:templates/info_list.tpl');
}
if (empty($im->parameters['page'])) {
	$im->set_param('page', 1);
}
if (!isset($im->parameters['sort'])) {
	$im->set_param('sort', '');
}

// JSON モードは Content-Type を変える
if ($im->parameters['mode'] === 'json_get_info_data') {
	header('Content-Type: application/json; charset=utf-8');
}

echo $im->run();
