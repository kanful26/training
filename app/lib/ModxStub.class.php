<?php
/**
 * 研修環境用 MODx スタブ
 *
 * 本物の MODx CMS を起動せず、InformationManager / TemplateChunkie / PHxParser
 * が必要とする最小インタフェースだけ提供する。
 */

class ModxStub
{
	public $config = array();
	public $db;
	public $documentOutput = '';
	public $placeholders = array();

	public function __construct()
	{
		$this->config = array(
			'base_path'        => '/var/www/html/',
			'site_url'         => '/',
			'etomite_charset'  => 'UTF-8',
		);
		$this->db = new ModxDbStub();
	}

	/**
	 * MODxのチャンク取得スタブ。
	 * 研修環境ではDBチャンクは使わないので常に空文字を返す
	 * （TemplateChunkie が @FILE: にフォールバックする）
	 */
	public function getChunk($name)
	{
		return '';
	}

	/**
	 * 本番では web_users / web_user_attributes など prefix 付きテーブル名を返す。
	 * 研修ではダミー名を返すだけ
	 */
	public function getFullTableName($table)
	{
		return 'modx_' . $table;
	}

	public function userLoggedIn()
	{
		return array(
			'id'       => 1,
			'username' => 'tanaka',
		);
	}

	public function getWebUserInfo($id)
	{
		return array(
			'id'       => 1,
			'username' => 'tanaka',
			'fullname' => '田中 太郎',
		);
	}

	public function getUserInfo($id)
	{
		return $this->getWebUserInfo($id);
	}

	public function getUserData()
	{
		return array();
	}

	// ----- PHxParser が呼び出すメソッド群（ほぼno-op）-----

	// PHxParser は SetPlaceholder と setPlaceholder の両方を呼ぶが、
	// PHPメソッド名は大小区別なしのため一方の定義で兼用される
	public function setPlaceholder($name, $value)
	{
		$this->placeholders[$name] = $value;
	}

	public function getPlaceholder($name)
	{
		return isset($this->placeholders[$name]) ? $this->placeholders[$name] : '';
	}

	/**
	 * MODxチャンク埋め込み。研修環境では使わないのでそのまま返す
	 */
	public function mergeChunkContent($str)
	{
		return $str;
	}

	/**
	 * MODxスニペット埋め込み。研修環境では使わないのでそのまま返す
	 */
	public function evalSnippets($str)
	{
		return $str;
	}

	/**
	 * MODxリソースフィールド埋め込み。研修環境では使わないのでそのまま返す
	 */
	public function mergeDocumentContent($str)
	{
		return $str;
	}

	/**
	 * MODxシステム設定埋め込み。研修環境では使わないのでそのまま返す
	 */
	public function mergeSettingsContent($str)
	{
		return $str;
	}

	public function logEvent($evtid, $type, $msg, $source = '')
	{
		// no-op
	}

	public function dbQuery($sql)
	{
		return null;
	}

	public function recordCount($res)
	{
		return 0;
	}

	public function fetchRow($res)
	{
		return null;
	}

	public function getRecord($res)
	{
		return null;
	}
}

/**
 * MODx の DB ラッパスタブ。
 * info_list_func が reg_user → fullname 変換のためだけに呼ぶので、
 * 固定マップを返すだけの最小実装にする。
 */
class ModxDbStub
{
	private $name_map = array(
		'yamada' => '山田 花子',
		'sato'   => '佐藤 次郎',
		'tanaka' => '田中 太郎',
	);

	private $last_username = '';

	public function select($cols, $from, $where = '')
	{
		// 期待される where: "username='xxx'"
		$matches = array();
		if (preg_match("/username='([^']+)'/", $where, $matches)) {
			$this->last_username = $matches[1];
		} else {
			$this->last_username = '';
		}
		return $this->last_username;
	}

	public function getRecordCount($res)
	{
		if (empty($res)) {
			return 0;
		}
		return isset($this->name_map[$res]) ? 1 : 0;
	}

	public function getValue($res)
	{
		if (isset($this->name_map[$res])) {
			return $this->name_map[$res];
		}
		return '';
	}

	public function getColumn($col, $sql)
	{
		return array();
	}
}
