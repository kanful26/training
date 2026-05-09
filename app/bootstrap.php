<?php
/**
 * 研修環境用 ブートストラップ
 *
 * 本物の MODx CMS を使わず、$modx スタブと最小 CommonKanful を構築する。
 * 本物の KanfulDB / TemplateChunkie / PHxParser は keiri/ 配下から読み込み、
 * 文字コード変換のみ無効化（UTF-8 で動作）する。
 *
 * 読み込み順序が重要：
 *   1. PHxParser → 2. TemplateChunkie → 3. KanfulDB
 *   4. ModxStub → 5. CommonKanful → 6. InformationManager（編集対象）
 */

// PHxParser が $_SESSION を参照するため初期化
if (!isset($_SESSION)) {
	$_SESSION = array(
		'mgrInternalKey' => 0,
		'webInternalKey' => 1,
	);
}

// keiri/ 配下の本物クラスへのパス
define('KEIRI_BASE', '/var/www/keiri/');

// 1. PHx Parser（テンプレート変数置換器）
require_once KEIRI_BASE . 'assets/snippets/kanful/phx.parser.class.inc.php';

// 2. TemplateChunkie
require_once KEIRI_BASE . 'assets/snippets/kanful/chunkie.class.inc.php';

// 3. KanfulDB（PostgreSQL ラッパ）
require_once KEIRI_BASE . 'assets/snippets/kanful/kanful.db.class.php';

// 4. MODx スタブ
require_once __DIR__ . '/lib/ModxStub.class.php';

// 5. CommonKanful 最小スタブ
require_once __DIR__ . '/lib/CommonKanful.class.php';

// $modx をグローバルに用意
$GLOBALS['modx'] = new ModxStub();
$modx = $GLOBALS['modx'];

// 6. 編集対象 InformationManager（最後に読み込み）
require_once __DIR__ . '/snippets/InformationManager.class.php';
