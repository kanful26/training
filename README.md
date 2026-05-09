# 社内お知らせ研修用 最小Docker環境

AI開発研修課題「社内お知らせの検索/フィルタ機能の実装」（spec: `../docs/training/2026-04-28-information-search.md`）の動作確認用ローカル環境。本番DBに触れず、Docker内のPostgreSQLとApache+PHP 5.3で完結する。

## 要件

- Docker Desktop または Docker Engine
- Docker Compose v2（`docker compose ...` コマンド）

## 起動

初回（イメージビルド込み）：

```bash
cd training
docker compose up --build
```

2回目以降：

```bash
docker compose up
```

ブラウザで `http://localhost:8080/` を開く。社内お知らせ一覧が表示される。

停止: `Ctrl-C` で `docker compose stop`。完全削除（DBデータも消す）: `docker compose down -v`。

## 構成

```text
training/
├── docker-compose.yml        # 2サービス: web (PHP 5.3 + Apache), db (PostgreSQL 9.2)
├── docker/
│   └── php/Dockerfile        # PHP 5.3-apache + pgsql/mbstring 拡張
├── db/init/                  # PostgreSQL 起動時に自動実行されるSQL
│   ├── 01_schema.sql         # information / information_checked テーブル
│   └── 02_seed.sql           # 検索動作確認用サンプル22件
└── app/                      # Apache DocumentRoot にマウント
    ├── index.php             # エントリポイント
    ├── bootstrap.php         # $modx スタブ + クラス読み込み
    ├── snippets/
    │   └── InformationManager.class.php   # 編集対象ファイル
    ├── lib/
    │   ├── CommonKanful.class.php         # 最小スタブ基底
    │   └── ModxStub.class.php             # MODx スタブ
    ├── templates/
    │   └── info_list.tpl                  # 一覧テンプレート（編集してOK）
    └── static/style.css
```

本番の以下クラスは `keiri/assets/snippets/kanful/` から **そのまま読み込む**（重複維持なし）：

- `KanfulDB` — PostgreSQL ラッパ
- `TemplateChunkie` — チャンクテンプレ
- `PHxParser` — `[+name+]` 置換器

研修環境では `KanfulDB` を `code="UTF-8"` で初期化するため、本番のEUC-JP変換は走らない（PostgreSQLもUTF-8で構築）。

## 編集対象

参加者が編集するファイルは1つだけ：

- `app/snippets/InformationManager.class.php`

このファイルは本番（`keiri/assets/snippets/InformationManager/InformationManager.class.php`）から**`info_list_func` / `json_get_info_data_func` 周辺だけを抜き出した版**。メソッド名・変数名・SQL構造は本番と同じなので、研修終了後はこのファイルの diff をそのまま本番ファイルの該当関数に適用できる。

テンプレート（`app/templates/info_list.tpl`）も研修内で自由に編集可能。検索フォームの入力欄追加など、UI側で必要な変更はここに行う。

## 動作確認の例

```text
http://localhost:8080/                                          → 全22件（最新20件＋次ページに2件）
http://localhost:8080/?mode=info_list&search_text=朝礼          → ※検索未実装のうちは全件のまま
http://localhost:8080/?mode=info_list&search_date_from=2026-04-15
http://localhost:8080/?mode=info_list&search_date_from=2026-04-01&search_date_to=2026-04-15
http://localhost:8080/?mode=json_get_info_data                  → JSON応答
```

## DBに直接接続して確認したい場合

ホストの 5432 が開いている。`psql` でアクセス可能：

```bash
psql -h localhost -U kanful -d kanful   # password: kanful
```

または `docker exec` でコンテナ内から：

```bash
docker exec -it harvest-training-db psql -U kanful kanful
```

## 想定する躓きポイント

| 症状                                          | 対処                                                             |
| --------------------------------------------- | ---------------------------------------------------------------- |
| `docker compose` で「podman.sock」エラー      | `DOCKER_HOST` が podman 向けに設定されている。`unset DOCKER_HOST` または `DOCKER_HOST=unix:///var/run/docker.sock docker compose up` |
| 起動直後にDB接続エラー                        | DB初期化に数秒かかるためリロード。それでも続くなら `docker compose logs db` 確認 |
| 編集が反映されない                            | ブラウザキャッシュ。Ctrl-F5 で再読み込み                          |
| 日本語が文字化け                              | PHP/PG 双方UTF-8で統一済み。それでも化けるならソース文字コード確認 |
| `php -l` をコンテナ内で実行したい             | `docker exec -it harvest-training-web php -l snippets/InformationManager.class.php` |
| 検索結果のページネーションが壊れる            | `get_count` も同じ WHERE で件数を取り直しているか確認             |
| SQLインジェクションテストで全件出てしまう     | `pg_escape_string` または `KanfulDB` の `$escape=true` を活用     |

## PHP バージョンに関する注記

本番は **PHP 5.3.3** だが、研修コンテナは **PHP 7.4-apache**（公式安定）を採用。理由：

- 公式 `php:5.3-apache` / `php:5.6-apache` は古い Docker Image manifest schema が廃止／Debian Jessie/Wheezy アーカイブの依存関係破綻のため、最新の Docker engine では build できない。

そのため、**`[]` 配列リテラル、`??`、`fn()` などは PHP 7.4 上では動作してしまう**。研修課題ではこれらを使わないことが必須要件であり、検証は本リポジトリの **PostToolUse フック（`.claude/hooks`）** が PHP 5.3 互換性を静的にチェックする。AI と協業する際もそのフィードバックを必ず確認すること。

簡易的なローカル check：

```bash
docker exec harvest-training-web php -l app/snippets/InformationManager.class.php
# → PHP 5.3 以降の構文エラーは検出するが、`[]` などは PHP 7.4 上では合法と判定されるので注意
```

## 本番への移植

研修完了後、参加者の `InformationManager.class.php` の修正を本番ファイル（`keiri/assets/snippets/InformationManager/InformationManager.class.php`）の対応する `info_list_func` / `json_get_info_data_func` に手動で当てる。テンプレート側の変更は本番ではMODxチャンクとして管理されているため、別途反映が必要（指導担当が対応）。

## 課題仕様

`docs/training/2026-04-28-information-search.md` を参照。
