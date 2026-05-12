<?php
/**
 * InformationManager（研修用切り出し版）
 *
 * 本番ファイル keiri/assets/snippets/InformationManager/InformationManager.class.php
 * から、社内お知らせ一覧（info_list）と JSON 版（json_get_info_data）に
 * 関係する部分だけを抜き出したもの。
 *
 * 研修課題：info_list_func / json_get_info_data_func の検索フィルタ実装
 *
 * 編集後の差分は、本番ファイルの info_list_func / json_get_info_data_func に
 * そのまま当てられるようにメソッド構造・変数名は本番と一致させること。
 */

class InformationManager extends CommonKanful
{
	private $disp_info_list_by_page = 20;

	function __construct()
	{
		parent::__construct(false);
		return;
	}

	function run()
	{
		parent::run();

		$result = "";
		$not_disp = false;

		switch ($this->parameters['mode']) {
			case 'info_list':
				$this->info_list_func();
				break;

			case 'json_get_info_data':
				$not_disp = true;
				$this->json_get_info_data_func();
				break;

			default:
				// 研修ではデフォルトで info_list を実行
				$this->parameters['mode'] = 'info_list';
				$this->info_list_func();
				break;
		}

		if ($not_disp != true) {
			$this->view_func();
		}

		return $this->result;
	}

	/**
	 * 検索条件からWHERE句文字列を生成する
	 */
	private function _build_info_search_where()
	{
		$conditions = array();

		if (!empty($this->parameters['search_text'])) {
			$kw = pg_escape_string($this->parameters['search_text']);
			$conditions[] = "(info_subject ILIKE '%" . $kw . "%' OR info_text ILIKE '%" . $kw . "%')";
		}

		if (!empty($this->parameters['search_date_from'])) {
			$from = pg_escape_string($this->parameters['search_date_from']);
			$conditions[] = "info_date >= '" . $from . "'";
		}

		if (!empty($this->parameters['search_date_to'])) {
			$to = pg_escape_string($this->parameters['search_date_to']);
			$conditions[] = "info_date < '" . $to . "'::date + interval '1 day'";
		}

		return implode(' AND ', $conditions);
	}

	/**
	 * JSON形式で社内お知らせデータを取得
	 */
	private function json_get_info_data_func()
	{
		$result = array(
			"total_pages"  => 0,
			"current_page" => 0,
			"total_count"  => 0,
			"data"         => array(),
			"message"      => "",
			"error"        => ""
		);
		$info_data_list = array();

		$where = $this->_build_info_search_where();
		$total_count = $this->db->get_count("information", $where);

		if (! empty($this->parameters['limit'])) {
			$this->disp_info_list_by_page = $this->parameters['limit'];
		}

		if ($total_count > 0) {
			// ページナビゲーションを作成
			$navi_data = $this->make_navigation(
				$total_count,
				$this->disp_info_list_by_page,
				$this->parameters['page']
			);
			$result['total_pages']  = $navi_data['max'];
			$result['current_page'] = $navi_data['current'];
			$result['total_count']  = $total_count;
			$offset = $navi_data['offset'];

			$staff_id = $this->get_user_id();

			if (! empty($staff_id)) {
				if ($this->db->select(
					"
						information
					",
					"
						information.info_id,
						information.info_date,
						information.info_subject,
						information.info_text,
						information.reg_user,
						information.reg_timestamp,
						information.upd_user,
						information.upd_timestamp,
						information.upd_seq
					",
					$where,
					"
						info_date DESC,
						upd_timestamp DESC,
						reg_timestamp DESC
					",
					$this->disp_info_list_by_page,
					false,
					"",
					"",
					$offset
				) > 0) {
					$get_info_id = array();
					while ($buff = $this->db->fetch_assoc()) {
						$info_data_list[$buff['info_id']] = $buff;
						$info_data_list[$buff['info_id']]['checked'] = false;
						$get_info_id[] = $buff['info_id'];
					}

					// information_checkedから確認済みかのデータ取得
					if ($this->db->select(
						"information_checked",
						"
							info_id,
							checked_date
						",
						"information_checked.staff_id = " . $staff_id . " AND info_id IN (" . implode(",", $get_info_id) . ")",
						"info_id"
					) > 0) {
						while ($buff = $this->db->fetch_assoc()) {
							$info_data_list[$buff['info_id']]['checked'] = true;
						}
					}
				}
			} else {
				$result['error'] = "ログインされていないため、データが取得できませんでした。";
				$this->result = json_encode($result);
				return;
			}
		} else {
			$result['message'] = "該当するお知らせはありませんでした。";
		}

		foreach ($info_data_list as $key => $data) {
			$info_data_list[$key]['info_text'] = str_replace("\n", "<br />", $data['info_text']);

			$user_name = $data['reg_user'];
			$res = $this->modx->db->select(
				"fullname",
				$this->modx->getFullTableName('web_users') .
					" INNER JOIN " .
					$this->modx->getFullTableName('web_user_attributes') .
					" ON " .
					$this->modx->getFullTableName('web_users') .
					".id = " .
					$this->modx->getFullTableName('web_user_attributes') .
					".id",
				"username='" . $data['reg_user'] . "'"
			);
			if ($this->modx->db->getRecordCount($res)) {
				$id = $this->modx->db->getValue($res);
				$user_name = $id;
			}
			$info_data_list[$key]['reg_user'] = $user_name;
		}

		$result['data'] = $info_data_list;

		$this->result = json_encode($result);
	}

	/**
	 * 管理画面用社内連絡一覧表示
	 */
	private function info_list_func()
	{
		$info_data_list = array();

		$where = $this->_build_info_search_where();
		$total_count = $this->db->get_count("information", $where);

		if (! empty($this->parameters['limit'])) {
			$this->disp_info_list_by_page = $this->parameters['limit'];
		}

		$page_navi = '';

		if ($total_count > 0) {
			// ページナビゲーションを作成
			$option = array(
				"sort"             => $this->parameters['sort'],
				"search_text"      => isset($this->parameters['search_text']) ? $this->parameters['search_text'] : '',
				"search_date_from" => isset($this->parameters['search_date_from']) ? $this->parameters['search_date_from'] : '',
				"search_date_to"   => isset($this->parameters['search_date_to']) ? $this->parameters['search_date_to'] : ''
			);
			$navi_data = $this->make_navigation(
				$total_count,
				$this->disp_info_list_by_page,
				$this->parameters['page'],
				$option,
				5
			);
			$this->parameters['page'] = $navi_data['current'];
			$offset = $navi_data['offset'];
			$page_navi = $navi_data['html'];

			$staff_id = $this->get_user_id();

			if (! empty($staff_id)) {
				if ($this->db->select(
					"
						information
					",
					"
						information.info_id,
						information.info_date,
						information.info_subject,
						information.info_text,
						information.reg_user,
						information.reg_timestamp,
						information.upd_user,
						information.upd_timestamp,
						information.upd_seq
					",
					$where,
					"
						info_date DESC,
						upd_timestamp DESC,
						reg_timestamp DESC
					",
					$this->disp_info_list_by_page,
					false,
					"",
					"",
					$offset
				) > 0) {
					$get_info_id = array();
					while ($buff = $this->db->fetch_assoc()) {
						$info_data_list[$buff['info_id']] = $buff;
						$info_data_list[$buff['info_id']]['checked'] = false;
						$get_info_id[] = $buff['info_id'];
					}

					// information_checkedから確認済みかのデータ取得
					if ($this->db->select(
						"information_checked",
						"
							info_id,
							checked_date
						",
						"information_checked.staff_id = " . $staff_id . " AND info_id IN (" . implode(",", $get_info_id) . ")",
						"info_id"
					) > 0) {
						while ($buff = $this->db->fetch_assoc()) {
							$info_data_list[$buff['info_id']]['checked'] = true;
						}
					}
				}
			}
		} else {
			$this->set_message_text('', '該当するお知らせはありませんでした。');
		}

		// テンプレートから出力内容を生成
		$tpl = new TemplateChunkie($this->parameters['list_tpl']);
		$template = $tpl->get_template();
		unset($tpl);

		$tpl = new TemplateChunkie('@CODE:' . $template['header']);
		$tpl->AddVar("page_navi", $page_navi);
		$tpl->AddVar("search_text", isset($this->parameters['search_text']) ? $this->parameters['search_text'] : '');
		$tpl->AddVar("search_date_from", isset($this->parameters['search_date_from']) ? $this->parameters['search_date_from'] : '');
		$tpl->AddVar("search_date_to", isset($this->parameters['search_date_to']) ? $this->parameters['search_date_to'] : '');
		$tpl_ret[] = $tpl->Render();
		unset($tpl);

		foreach ($info_data_list as $data) {
			$class = array();
			$tpl = new TemplateChunkie('@CODE:' . $template['body']);

			foreach ($data as $key => $value) {
				$tpl->AddVar($key, $value);
			}

			$tmp = explode(" ", $data['info_date']);
			$tpl->AddVar("info_day", $tmp[0]);
			$tpl->AddVar("info_text", str_replace("\n", "<br />", $data['info_text']));

			$user_name = $data['reg_user'];
			$res = $this->modx->db->select(
				"fullname",
				$this->modx->getFullTableName('web_users') .
					" INNER JOIN " .
					$this->modx->getFullTableName('web_user_attributes') .
					" ON " .
					$this->modx->getFullTableName('web_users') .
					".id = " .
					$this->modx->getFullTableName('web_user_attributes') .
					".id",
				"username='" . $data['reg_user'] . "'"
			);
			if ($this->modx->db->getRecordCount($res)) {
				$id = $this->modx->db->getValue($res);
				$user_name = $id;
			}
			$tpl->AddVar("reg_user", $user_name);

			if ($data['checked'] == true) {
				$class[] = "checked";
			}
			$tpl->AddVar("class", implode(" ", $class));
			$tpl->AddVar("sort", $this->parameters['sort']);
			$tpl->AddVar("page", $this->parameters['page']);

			$tpl_ret[] = $tpl->Render();
			unset($tpl);
		}

		$tpl = new TemplateChunkie('@CODE:' . $template['footer']);
		$tpl->AddVar("page_navi", $page_navi);
		$tpl_ret[] = $tpl->Render();
		unset($tpl);

		$this->list = implode('', $tpl_ret);
	}
}
