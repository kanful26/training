# 社内お知らせ検索/フィルタ機能 設計ドキュメント

**日付:** 2026-05-09
**対象ファイル:** `app/snippets/InformationManager.class.php`

---

## 概要

`info_list_func` / `json_get_info_data_func` に検索・フィルタ機能を追加する。
現状はWHERE句なしで常に全件返しているため、リクエストパラメータに基づいて絞り込みを行う。

---

## 要件

| パラメータ | 対象カラム | 方式 |
|---|---|---|
| `search_text` | `info_subject`, `info_text` | ILIKE（大文字小文字区別なし）、OR検索 |
| `search_date_from` | `info_date` | `>=` 指定日 00:00:00（指定なし時は無視） |
| `search_date_to` | `info_date` | `<=` 指定日 23:59:59（指定なし時は無視） |

- 各条件は独立して動く（fromのみ、toのみでも有効）
- 複数条件はAND結合
- 条件なしの場合は全件表示（現状と同じ挙動）

---

## 設計方針: プライベートヘルパーメソッド方式

`_build_search_where()` を1つ追加し、`info_list_func` と `json_get_info_data_func` の両方から呼ぶ。

### 理由

- 両関数ともに `get_count` + `select` の2回呼び出しがあり、WHERE句の一元管理が必須
- ページネーション件数も絞り込み後の件数にする必要があるため、`get_count` にも同じWHERE句を渡す
- コード重複を避けつつ、変更箇所を最小限に抑える

---

## 実装詳細

### 新規メソッド: `_build_search_where()`

```php
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

### 既存関数の修正

`info_list_func()` と `json_get_info_data_func()` の両方で：

1. 関数冒頭に `$where = $this->_build_search_where();` を追加
2. `$this->db->get_count("information")` → `$this->db->get_count("information", $where)` に変更
3. `select(...)` の第3引数（WHERE）を `""` → `$where` に変更

---

## 制約事項

- **PHP 5.3互換必須**: `[]` リテラル、`??`、`fn()` を使わない。配列は `array()` で記述
- **SQLインジェクション対策**: ユーザー入力は必ず `$this->db->escape()` を通す
- **変更ファイル**: `app/snippets/InformationManager.class.php` のみ（テンプレートは変更不要）

---

## 動作確認ケース

```
http://localhost:8080/?mode=info_list&search_text=朝礼
http://localhost:8080/?mode=info_list&search_date_from=2026-04-15
http://localhost:8080/?mode=info_list&search_date_from=2026-04-01&search_date_to=2026-04-15
http://localhost:8080/?mode=json_get_info_data
```
