-- 社内お知らせ研修用 最小スキーマ
-- 本番(PostgreSQL 9.2 / EUC-JP)の構造を踏襲しつつ、研修用は UTF-8 で構築

CREATE TABLE information (
    info_id        INTEGER       NOT NULL,
    info_date      TIMESTAMP     NOT NULL,
    info_subject   VARCHAR(200),
    info_text      TEXT,
    reg_user       VARCHAR(50),
    reg_timestamp  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    upd_user       VARCHAR(50),
    upd_timestamp  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    upd_seq        INTEGER       DEFAULT 0,
    PRIMARY KEY (info_id)
);

CREATE TABLE information_checked (
    info_id        INTEGER       NOT NULL,
    staff_id       INTEGER       NOT NULL,
    checked_date   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    upd_seq        INTEGER       DEFAULT 0,
    upd_user       VARCHAR(50),
    upd_timestamp  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    cre_user       VARCHAR(50),
    cre_timestamp  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (info_id, staff_id)
);

-- スタッフは本番では別テーブルだが、研修ではログインスタブで固定IDを返すため、テーブル不要
