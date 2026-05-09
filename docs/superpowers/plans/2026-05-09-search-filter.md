# 社内お知らせ 検索/フィルタ機能 実装プラン

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `InformationManager` の `info_list_func` / `json_get_info_data_func` に検索・フィルタ機能を追加し、キーワード・日付範囲で絞り込めるようにする。

**Architecture:** `_build_search_where()` プライベートメソッドを1つ追加してWHERE句を一元生成し、両関数の `get_count` と `select` に渡す。`info_list_func` はさらにページネーションURLにも検索条件を引き継ぐ。

**Tech Stack:** PHP 7.4（PHP 5.3互換で記述）、PostgreSQL 9.2、Docker Compose

---

## ファイルマップ

| 操作 | ファイル | 内容 |
|---|---|---|
| 新規作成 | `app/test_search.php` | 動作確認用テストスクリプト |
| 修正 | `app/snippets/InformationManager.class.php` | `_build_search_where()` 追加、既存2関数修正 |

---

## シードデータ早見表（テスト期待値の根拠）

| 検索条件 | 期待ヒット件名（抜粋） | 期待ゼロ件名（抜粋） |
|---|---|---|
| `search_text=朝礼` | 「新年のご挨拶」(本文)、「本日の朝礼」(件名) | 「節分イベント」 |
| `search_text=研修` | 「春の研修について」、「システム研修のご案内」 | 「在庫棚卸し」 |
| `search_date_from=2026-04-15` | 「新メーカー取り扱い開始」(4/15) | 「新年のご挨拶」(1/15) |
| `from=2026-04-01 to=2026-04-15` | 「新年度開始」(4/1)、「新メーカー取り扱い開始」(4/15) | 「節分イベント」、「社員旅行」 |

---

## Task 1: テストスクリプトの作成（先に失敗を確認する）

**Files:**
- Create: `app/test_search.php`

- [ ] **Step 1: テストスクリプトを作成する**

`app/test_search.php` を以下の内容で作成：

```php
<?php
// 動作確認用テストスクリプト
// 実行: docker exec harvest-training-web php test_search.php
chdir('/var/www/html');
require_once __DIR__ . '/bootstrap.php';

function run_test($label, $params, $must_contain, $must_not_contain = array())
{
    $im = new InformationManager();
    foreach ($params as $k => $v) {
        $im->set_param($k, $v);
    }
    $im->set_param('list_tpl', '@FILE:templates/info_list.tpl');
    $im->set_param('page', 1);
    $im->set_param('sort', '');
    $result = $im->run();

    $pass = true;
    foreach ($must_contain as $text) {
        if (strpos($result, $text) === false) {
            echo "FAIL [$label]: 「{$text}」が出力に見つかりません\n";
            $pass = false;
        }
    }
    foreach ($must_not_contain as $text) {
        if (strpos($result, $text) !== false) {
            echo "FAIL [$label]: 「{$text}」が出力に含まれてはいけません\n";
            $pass = false;
        }
    }
    if ($pass) {
        echo "PASS [$label]\n";
    }
}

// 1. 全件表示（条件なし）
run_test(
    '全件表示',
    array('mode' => 'info_list'),
    array('新年のご挨拶', 'お疲れ様でした')
);

// 2. search_text=朝礼（件名・本文ILIKE）
run_test(
    'search_text=朝礼',
    array('mode' => 'info_list', 'search_text' => '朝礼'),
    array('本日の朝礼', '新年のご挨拶'),
    array('節分イベント')
);

// 3. search_date_from のみ（2026-04-15以降）
run_test(
    'search_date_from=2026-04-15',
    array('mode' => 'info_list', 'search_date_from' => '2026-04-15'),
    array('新メーカー取り扱い開始'),
    array('新年のご挨拶', '節分イベント')
);

// 4. 日付範囲（2026-04-01〜2026-04-15）
run_test(
    'date_from=2026-04-01 to=2026-04-15',
    array('mode' => 'info_list', 'search_date_from' => '2026-04-01', 'search_date_to' => '2026-04-15'),
    array('新年度開始', '新メーカー取り扱い開始'),
    array('新年のご挨拶', '社員旅行')
);

// 5. search_date_to のみ（2026-02-28以前）
run_test(
    'search_date_to=2026-02-28',
    array('mode' => 'info_list', 'search_date_to' => '2026-02-28'),
    array('新年のご挨拶', '節分イベント'),
    array('新年度開始', '春の研修')
);

echo "\n--- JSON endpoint ---\n";
$im = new InformationManager();
$im->set_param('mode', 'json_get_info_data');
$im->set_param('search_text', '研修');
$im->set_param('page', 1);
$json = $im->run();
$data = json_decode($json, true);
$subjects = array();
foreach ($data['data'] as $row) {
    $subjects[] = $row['info_subject'];
}
$expected = array('春の研修について', 'システム研修のご案内');
foreach ($expected as $s) {
    if (in_array($s, $subjects)) {
        echo "PASS [JSON search_text=研修]: 「{$s}」を確認\n";
    } else {
        echo "FAIL [JSON search_text=研修]: 「{$s}」が見つかりません\n";
    }
}
if (in_array('在庫棚卸しのお知らせ', $subjects)) {
    echo "FAIL [JSON search_text=研修]: 「在庫棚卸しのお知らせ」が含まれてはいけません\n";
} else {
    echo "PASS [JSON search_text=研修]: 「在庫棚卸し」が除外されていることを確認\n";
}
```

- [ ] **Step 2: テストを実行して現状が失敗することを確認する**

```bash
docker exec harvest-training-web php test_search.php
```

期待出力（現状は検索未実装なので `search_text=朝礼` 等が FAIL になる）：
```
PASS [全件表示]
FAIL [search_text=朝礼]: 「節分イベント」が出力に含まれてはいけません
...
```

---

## Task 2: `_build_search_where()` メソッドを追加する

**Files:**
- Modify: `app/snippets/InformationManager.class.php`

- [ ] **Step 1: `_build_search_where()` メソッドを追加する**

`InformationManager.class.php` の `json_get_info_data_func()` の直前（58行目付近）に以下を挿入：

```php
    /**
     * リクエストパラメータからWHERE句文字列を組み立てる
     */
    private function _build_search_where()
    {
        $conditions = array();

        if (!empty($this->parameters['search_text'])) {
            $keyword = $this->db->escape($this->parameters['search_text']);
            $conditions[] = "(info_subject ILIKE '%" . $keyword . "%'"
                          . " OR info_text ILIKE '%" . $keyword . "%')";
        }

        if (!empty($this->parameters['search_date_from'])) {
            $from = $this->db->escape($this->parameters['search_date_from']);
            $conditions[] = "info_date >= '" . $from . " 00:00:00'";
        }

        if (!empty($this->parameters['search_date_to'])) {
            $to = $this->db->escape($this->parameters['search_date_to']);
            $conditions[] = "info_date <= '" . $to . " 23:59:59'";
        }

        return empty($conditions) ? "" : implode(" AND ", $conditions);
    }
```

- [ ] **Step 2: 構文チェック**

```bash
docker exec harvest-training-web php -l /var/www/html/snippets/InformationManager.class.php
```

期待出力：
```
No syntax errors detected in /var/www/html/snippets/InformationManager.class.php
```

---

## Task 3: `info_list_func()` を修正する

**Files:**
- Modify: `app/snippets/InformationManager.class.php`

- [ ] **Step 1: `info_list_func()` 冒頭にWHERE句の生成を追加する**

`info_list_func()` 内の以下の行を：

```php
        $total_count = $this->db->get_count("information");
```

以下に変更：

```php
        $where = $this->_build_search_where();
        $total_count = $this->db->get_count("information", $where);
```

- [ ] **Step 2: `$option` に検索条件を追加する**

`info_list_func()` 内の以下の箇所を：

```php
			$option = array(
				"sort" => $this->parameters['sort']
			);
```

以下に変更：

```php
			$option = array(
				"sort"             => $this->parameters['sort'],
				"search_text"      => isset($this->parameters['search_text']) ? $this->parameters['search_text'] : '',
				"search_date_from" => isset($this->parameters['search_date_from']) ? $this->parameters['search_date_from'] : '',
				"search_date_to"   => isset($this->parameters['search_date_to']) ? $this->parameters['search_date_to'] : '',
			);
```

- [ ] **Step 3: `select()` のWHERE句を `$where` に変更する**

`info_list_func()` 内に `select()` 呼び出しが2か所ある。**1つ目**（`information` テーブルへの検索）のみWHERE句を変更する。2つ目（`information_checked` テーブル）はすでに `staff_id` 条件があるため変更しない。

変更前（`information` テーブルへの `select` の第3引数）：
```php
				if ($this->db->select(
					"
						information
					",
					"
						information.info_id,
```

この `select` 呼び出し内の第3引数（ORDER句の前の `""`）を変更：

```php
					"",
					"
						info_date DESC,
```

変更後：
```php
					$where,
					"
						info_date DESC,
```

- [ ] **Step 4: 構文チェック**

```bash
docker exec harvest-training-web php -l /var/www/html/snippets/InformationManager.class.php
```

期待出力：
```
No syntax errors detected in /var/www/html/snippets/InformationManager.class.php
```

- [ ] **Step 5: テストを実行して `info_list_func` のケースが通ることを確認する**

```bash
docker exec harvest-training-web php test_search.php
```

期待出力（info_list関連のテストがすべてPASSになる）：
```
PASS [全件表示]
PASS [search_text=朝礼]
PASS [search_date_from=2026-04-15]
PASS [date_from=2026-04-01 to=2026-04-15]
PASS [search_date_to=2026-02-28]
```

- [ ] **Step 6: コミット**

```bash
git add app/snippets/InformationManager.class.php app/test_search.php
git commit -m "feat: info_list_funcに検索/フィルタ機能を追加"
```

---

## Task 4: `json_get_info_data_func()` を修正する

**Files:**
- Modify: `app/snippets/InformationManager.class.php`

- [ ] **Step 1: `json_get_info_data_func()` 冒頭にWHERE句の生成を追加する**

`json_get_info_data_func()` 内の以下の行を：

```php
        $total_count = $this->db->get_count("information");
```

以下に変更：

```php
        $where = $this->_build_search_where();
        $total_count = $this->db->get_count("information", $where);
```

- [ ] **Step 2: `select()` のWHERE句を `$where` に変更する**

`json_get_info_data_func()` 内にも `select()` が2か所ある。**1つ目**（`information` テーブルへの検索）のみ変更する。2つ目（`information_checked` テーブル）は変更しない。

変更前（`information` テーブルへの `select` の第3引数）：
```php
				if ($this->db->select(
					"
						information
					",
					"
						information.info_id,
```

この `select` 内の第3引数（ORDER句の前の `""`）を変更：

```php
					"",
					"
						info_date DESC,
						upd_timestamp DESC,
						reg_timestamp DESC
					",
					$this->disp_info_list_by_page,
```

変更後：
```php
					$where,
					"
						info_date DESC,
						upd_timestamp DESC,
						reg_timestamp DESC
					",
					$this->disp_info_list_by_page,
```

- [ ] **Step 3: 構文チェック**

```bash
docker exec harvest-training-web php -l /var/www/html/snippets/InformationManager.class.php
```

期待出力：
```
No syntax errors detected in /var/www/html/snippets/InformationManager.class.php
```

- [ ] **Step 4: すべてのテストが通ることを確認する**

```bash
docker exec harvest-training-web php test_search.php
```

期待出力（全テストPASS）：
```
PASS [全件表示]
PASS [search_text=朝礼]
PASS [search_date_from=2026-04-15]
PASS [date_from=2026-04-01 to=2026-04-15]
PASS [search_date_to=2026-02-28]

--- JSON endpoint ---
PASS [JSON search_text=研修]: 「春の研修について」を確認
PASS [JSON search_text=研修]: 「システム研修のご案内」を確認
PASS [JSON search_text=研修]: 「在庫棚卸し」が除外されていることを確認
```

- [ ] **Step 5: コミット**

```bash
git add app/snippets/InformationManager.class.php
git commit -m "feat: json_get_info_data_funcに検索/フィルタ機能を追加"
```

---

## Task 5: ブラウザ動作確認

- [ ] **Step 1: 全件表示を確認する**

`http://localhost:8080/` を開いて22件中20件が表示され、ページ2に残り2件があることを確認する。

- [ ] **Step 2: キーワード検索を確認する**

`http://localhost:8080/?mode=info_list&search_text=研修` を開いて「春の研修について」「システム研修のご案内」「システムメンテナンスのお知らせ」が表示され、無関係のお知らせが含まれないことを確認する。

- [ ] **Step 3: 日付FROM検索を確認する**

`http://localhost:8080/?mode=info_list&search_date_from=2026-04-15` を開いて2026-04-15以降のお知らせのみが表示されることを確認する。

- [ ] **Step 4: 日付範囲検索を確認する**

`http://localhost:8080/?mode=info_list&search_date_from=2026-04-01&search_date_to=2026-04-15` を開いて2026-04-01〜2026-04-15の5件のみが表示されることを確認する。

- [ ] **Step 5: ページネーションに検索条件が引き継がれることを確認する**

`http://localhost:8080/?mode=info_list&search_text=の` を開いて、2ページ目リンクのURLに `search_text=%E3%81%AE` が含まれていることをブラウザのリンクにカーソルを当てて確認する。2ページ目をクリックしても検索条件が維持されることを確認する。

- [ ] **Step 6: JSON エンドポイントを確認する**

`http://localhost:8080/?mode=json_get_info_data&search_text=朝礼` にアクセスして、レスポンスJSONの `data` に「本日の朝礼」「新年のご挨拶」が含まれ、件数が全22件でないことを確認する。
