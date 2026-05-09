<?php
/**
 * 研修環境用 CommonKanful 最小スタブ
 *
 * 本番の CommonKanful は 6800 行以上あり、FTP/Logger/SystemConfigUtil/
 * staff/keep_place/sale_shop/buy_shop の事前ロードなど多くを行うが、
 * 研修ではそれらは使わないため、info_list_func / json_get_info_data_func
 * が直接呼び出すメソッドだけを最小実装する。
 *
 * 本番に挙動を合わせるため、メソッド名・戻り値構造は本番と一致させること。
 */

class CommonKanful
{
	public $parameters = array();
	public $db;
	public $modx;
	public $userT;
	public $userInfo;
	protected $list = '';
	protected $result = '';
	protected $_message_data = array(
		'message' => array(),
		'error'   => array(),
		'warning' => array(),
	);

	public function __construct($debug = false)
	{
		global $modx;

		$this->modx = $modx;

		// 環境変数からDB接続情報を取得し、UTF-8 で接続
		// （本番は EUC-JP だが、研修では encode 変換をスキップして UTF-8 のまま）
		$host     = getenv('DB_HOST') ? getenv('DB_HOST') : 'db';
		$user     = getenv('DB_USER') ? getenv('DB_USER') : 'kanful';
		$password = getenv('DB_PASSWORD') ? getenv('DB_PASSWORD') : 'kanful';
		$dbname   = getenv('DB_NAME') ? getenv('DB_NAME') : 'kanful';

		$this->db = new KanfulDB($host, $user, $password, $dbname, 'UTF-8', $debug);

		$this->userT    = $modx->userLoggedIn();
		$this->userInfo = $modx->getWebUserInfo($this->userT['id']);
	}

	/**
	 * MODxのスニペット側で値をセットするメソッド
	 */
	public function set_param($field, $value)
	{
		$this->parameters[$field] = $value;
	}

	/**
	 * 全リクエストパラメータをまとめて反映
	 */
	public function set_param_by_all_request()
	{
		foreach ($_REQUEST as $k => $v) {
			$this->parameters[$k] = $v;
		}
	}

	public function run()
	{
		// 本番の run() は時間計測等を行うが、研修では何もしない
	}

	/**
	 * ログイン中スタッフID。研修では固定で1を返す
	 */
	public function get_user_id()
	{
		return 1;
	}

	/**
	 * メッセージ・エラー・警告セット（テンプレート結合用）
	 */
	public function set_message_text($title, $text, $title_color = '', $title_font_size = 0)
	{
		$this->_message_data['message'][] = array('title' => $title, 'text' => $text);
	}

	public function set_error_text($text, $text_color = '', $text_font_size = 0)
	{
		$this->_message_data['error'][] = array('text' => $text);
	}

	public function set_warning_text($text, $text_color = '', $text_font_size = 0)
	{
		$this->_message_data['warning'][] = array('text' => $text);
	}

	/**
	 * システム設定取得スタブ
	 */
	public function get_system_config($key)
	{
		return '';
	}

	/**
	 * ページネーション。本番の make_navigation と同じ戻り値構造
	 *
	 * @param int   $total            総件数
	 * @param int   $disp_by_page     1ページあたりの件数
	 * @param int   $current_page     現在ページ
	 * @param array $option           リンクに付加するクエリ文字列の連想配列
	 * @param int   $window           前後何ページ分表示するか
	 * @return array  array('max'=>x, 'current'=>x, 'offset'=>x, 'html'=>x)
	 */
	public function make_navigation($total, $disp_by_page, $current_page = 1, $option = array(), $window = 5)
	{
		if ($disp_by_page <= 0) {
			$disp_by_page = 20;
		}
		$max = (int) ceil($total / $disp_by_page);
		if ($max < 1) {
			$max = 1;
		}
		if (!is_numeric($current_page) || $current_page < 1) {
			$current_page = 1;
		}
		if ($current_page > $max) {
			$current_page = $max;
		}
		$offset = ($current_page - 1) * $disp_by_page;

		// クエリ文字列の組み立て
		$query_parts = array();
		if (is_array($option)) {
			foreach ($option as $k => $v) {
				if ($v === '' || $v === null) {
					continue;
				}
				$query_parts[] = urlencode($k) . '=' . urlencode($v);
			}
		}
		$base_url = '?';
		if (!empty($query_parts)) {
			$base_url .= implode('&', $query_parts) . '&';
		}

		// 前後 $window ページ分のリンクを生成
		$start = max(1, $current_page - $window);
		$end   = min($max, $current_page + $window);
		$html  = '<div class="page-navi">';
		if ($current_page > 1) {
			$html .= '<a href="' . htmlspecialchars($base_url . 'page=' . ($current_page - 1)) . '">&laquo;前へ</a> ';
		}
		for ($i = $start; $i <= $end; $i++) {
			if ($i == $current_page) {
				$html .= '<strong>' . $i . '</strong> ';
			} else {
				$html .= '<a href="' . htmlspecialchars($base_url . 'page=' . $i) . '">' . $i . '</a> ';
			}
		}
		if ($current_page < $max) {
			$html .= '<a href="' . htmlspecialchars($base_url . 'page=' . ($current_page + 1)) . '">次へ&raquo;</a>';
		}
		$html .= '</div>';

		return array(
			'max'     => $max,
			'current' => $current_page,
			'offset'  => $offset,
			'html'    => $html,
		);
	}

	/**
	 * 出力結合。$this->list に積まれた一覧と、メッセージ等をまとめて
	 * $this->result に格納する
	 */
	protected function view_func()
	{
		$out = '';
		foreach (array('error', 'warning', 'message') as $level) {
			foreach ($this->_message_data[$level] as $msg) {
				$style = 'padding:8px;margin:4px 0;border-radius:4px;';
				if ($level === 'error') {
					$style .= 'background:#fdd;color:#900;border:1px solid #c66;';
				} elseif ($level === 'warning') {
					$style .= 'background:#ffd;color:#960;border:1px solid #cc6;';
				} else {
					$style .= 'background:#dfd;color:#060;border:1px solid #6c6;';
				}
				$title = isset($msg['title']) ? '<strong>' . htmlspecialchars($msg['title']) . '</strong>: ' : '';
				$out .= '<div style="' . $style . '">' . $title . htmlspecialchars($msg['text']) . '</div>';
			}
		}
		$out .= $this->list;
		$this->result = $out;
	}
}
