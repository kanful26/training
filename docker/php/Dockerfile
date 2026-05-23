# 本番は PHP 5.3.3。
# 公式 php:5.3-apache / php:5.6-apache は Manifest スキーマ廃止と Debian EOL の依存破綻で
# 安定的に build できなくなっているため、ランタイムは php:7.4-apache を採用。
# PHP 5.3 互換性は本リポジトリの PostToolUse フックが別途検証する想定。
FROM php:7.4-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libpq-dev \
    && docker-php-ext-install pgsql \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# 開発用エラー表示と日本時間。
# PHxParser が PHP4 形式コンストラクタを使うため、E_DEPRECATED は抑制
RUN { \
        echo "display_errors = On"; \
        echo "display_startup_errors = On"; \
        echo "error_reporting = E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED"; \
        echo "log_errors = On"; \
        echo "default_charset = UTF-8"; \
        echo "date.timezone = Asia/Tokyo"; \
    } > /usr/local/etc/php/conf.d/dev.ini

EXPOSE 80
