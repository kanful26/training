# 社内お知らせ 検索/フィルタ機能 設計仕様書

作成日: 2026-05-09

## 概要

`InformationManager.class.php` の `info_list_func` および `json_get_info_data_func` に検索フィルタ機能を追加する。
WHERE句の組み立てはprivateメソッド `_build_info_search_where()` に集約し、両メソッドから呼び出す。

## 検索仕様

| パラメータ | 対象カラム | 条件 |
|---|---|---|
| `search_text` | `info_subject`, `info_text` | ILIKE による部分一致（大文字小文字区別なし） |
| `search_date_from` | `info_date` | `info_date >= '入力値'` |
| `search_date_to` | `info_date` | `info_date < '入力値'::date + interval '1 day'`（当日23:59:59まで含む） |

- 複数条件は AND で結合
- 未入力の条件は無視（条件なしの場合はWHERE句なしで全件取得）

## アーキテクチャ

### 新規追加メソッド

```
InformationManager
  └── _build_info_search_where() : string  ← 新規 private メソッド
```

#### `_build_info_search_where()` 処理詳細

1. `$conditions = array()` で初期化
2. `search_text` が空でなければ `(info_subject ILIKE '%kw%' OR info_text ILIKE '%kw%')` を追加
3. `search_date_from` が空でなければ `info_date >= 'from'` を追加
4. `search_date_to` が空でなければ `info_date < 'to'::date + interval '1 day'` を追加
5. `implode(' AND ', $conditions)` で文字列化して返す（条件なし時は `""` を返す）
6. 入力値はすべて `pg_escape_string()` でエスケープする

### 変更メソッド

#### `info_list_func()`

- `_build_info_search_where()` でWHERE句を生成
- `get_count("information", $where)` に同じWHERE句を渡す（ページネーション正確化）
- `select()` の第3引数（WHERE）に `$where` を渡す
- `make_navigation()` の `$option` に `search_text` / `search_date_from` / `search_date_to` を追加（ページリンクへ検索条件を引き継ぐ）
- `$total_count == 0` のとき `set_message_text()` で「該当するお知らせはありませんでした。」を表示

#### `json_get_info_data_func()`

- `_build_info_search_where()` でWHERE句を生成
- `get_count("information", $where)` に同じWHERE句を渡す
- `select()` の第3引数に `$where` を渡す
- `$total_count == 0` のとき `$result['message']` に「該当するお知らせはありませんでした。」をセット

### テンプレート変更

`info_list.tpl` は変更不要。検索フォームは既に3つのパラメータを送信している。

## セキュリティ

- `pg_escape_string()` で全ユーザー入力をエスケープし、SQLインジェクションを防ぐ

## 実装制約

- PHP 5.3.3 互換コードのみ使用
  - 配列は `array()` で記述（`[]` リテラル禁止）
  - `??` 演算子禁止
  - `fn()` アロー関数禁止
- メソッド名・変数名・SQL構造は本番と一致させる（研修後にdiffを本番ファイルへ当てるため）

## 動作確認

```
http://localhost:8080/?mode=info_list&search_text=朝礼
http://localhost:8080/?mode=info_list&search_date_from=2026-04-15
http://localhost:8080/?mode=info_list&search_date_from=2026-04-01&search_date_to=2026-04-15
http://localhost:8080/?mode=info_list&search_text=朝礼&search_date_from=2026-04-01&search_date_to=2026-04-30
http://localhost:8080/?mode=json_get_info_data&search_text=朝礼
```

## 変更ファイル一覧

| ファイル | 変更内容 |
|---|---|
| `app/snippets/InformationManager.class.php` | `_build_info_search_where()` 追加、`info_list_func` / `json_get_info_data_func` 修正 |
| `app/templates/info_list.tpl` | 変更なし |
