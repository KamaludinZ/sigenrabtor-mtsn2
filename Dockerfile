# syntax=docker/dockerfile:1.7
#
# Image produksi SIRABTOR (Laravel 13 + Livewire 4) — satu kontainer berisi nginx, PHP-FPM, pekerja antrean, dan
# penjadwal (supervisord), beserta mesin dokumen yang dipakai aplikasi:
#   - Chromium headless  → PDF set dokumen (PembuatPdfChrome, SIRABTOR_CHROME)
#   - LibreOffice nogui  → PDF RAB & Monitoring dari Excel (PengubahPdfLibreOffice, locale id_ID)
#   - poppler-utils      → gabung PDF buku (pdfunite/pdfinfo)
#   - postgresql-client-18 (PGDG) → cadangan otomatis sebelum migrasi & cadangan harian (pg_dump/pg_restore, PengamanDeploy)
# Panggilan AI (Ollama/Claude/OpenAI/Gemini) memakai HTTP keluar biasa — tidak perlu paket tambahan.
# Panduan: docs/deploy.md.

ARG PHP_VERSION=8.4
ARG NODE_VERSION=22

# ---------------------------------------------------------------------------------------------------------------------
# Dasar PHP: ekstensi yang dibutuhkan Laravel, PostgreSQL, dan PhpSpreadsheet (gd, zip, intl, …).
# ---------------------------------------------------------------------------------------------------------------------
FROM php:${PHP_VERSION}-fpm-bookworm AS dasar

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql pgsql zip gd intl bcmath opcache pcntl exif

WORKDIR /var/www/html

# ---------------------------------------------------------------------------------------------------------------------
# Dependensi Composer tanpa paket pengembangan (diperiksa terhadap ekstensi image dasar).
# ---------------------------------------------------------------------------------------------------------------------
FROM dasar AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts

# ---------------------------------------------------------------------------------------------------------------------
# Aset frontend (Vite + Tailwind). Tailwind memindai app/, resources/, dan view vendor, jadi seluruh sumber ikut.
# ---------------------------------------------------------------------------------------------------------------------
FROM node:${NODE_VERSION}-bookworm-slim AS aset

WORKDIR /app
COPY package.json package-lock.json .npmrc ./
RUN npm ci --no-audit --no-fund
COPY --from=vendor /var/www/html /app
RUN npm run build && rm -f public/hot

# ---------------------------------------------------------------------------------------------------------------------
# Runtime.
# ---------------------------------------------------------------------------------------------------------------------
FROM dasar AS produksi

ENV DEBIAN_FRONTEND=noninteractive
# Klien PostgreSQL 18 dari repositori resmi PGDG (Debian bookworm hanya punya v15, yang tidak bisa mencadangkan server 18).
RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates curl \
    && install -d /usr/share/postgresql-common/pgdg \
    && curl -fsSL -o /usr/share/postgresql-common/pgdg/apt.postgresql.org.asc https://www.postgresql.org/media/keys/ACCC4CF8.asc \
    && echo "deb [signed-by=/usr/share/postgresql-common/pgdg/apt.postgresql.org.asc] https://apt.postgresql.org/pub/repos/apt bookworm-pgdg main" \
        > /etc/apt/sources.list.d/pgdg.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        nginx supervisor locales \
        postgresql-client-18 \
        chromium \
        libreoffice-calc-nogui libreoffice-writer-nogui \
        poppler-utils \
        fontconfig fonts-liberation2 fonts-dejavu-core fonts-crosextra-carlito fonts-crosextra-caladea \
    && sed -i 's/^# *\(id_ID.UTF-8 UTF-8\)/\1/; s/^# *\(en_US.UTF-8 UTF-8\)/\1/' /etc/locale.gen \
    && locale-gen \
    && fc-cache -f \
    && rm -rf /var/lib/apt/lists/* /etc/nginx/sites-enabled/default \
    && ln -sf /dev/stdout /var/log/nginx/access.log \
    && ln -sf /dev/stderr /var/log/nginx/error.log

ENV LANG=en_US.UTF-8 \
    LC_ALL=en_US.UTF-8 \
    SIRABTOR_CHROME=/usr/bin/chromium \
    SIRABTOR_SOFFICE=/usr/bin/soffice \
    SIRABTOR_PDF_PERENDER=chrome

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-sirabtor.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-sirabtor.conf
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/sirabtor.conf
COPY docker/entrypoint.sh /usr/local/bin/sirabtor-entrypoint
COPY docker/pulihkan.sh /usr/local/bin/sirabtor-pulihkan
RUN chmod +x /usr/local/bin/sirabtor-entrypoint /usr/local/bin/sirabtor-pulihkan

# Kode milik root (hanya-baca bagi www-data); hanya storage & bootstrap/cache yang bisa ditulis aplikasi.
COPY --from=vendor /var/www/html /var/www/html
COPY --from=aset /app/public/build /var/www/html/public/build

RUN mkdir -p storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions \
        storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# Penanda rilis image: berganti setiap kode berubah → cadangan otomatis sebelum kontainer rilis baru memakai data
# (PengamanDeploy::perluCadangan). Coolify mengisi SOURCE_COMMIT; build manual boleh tanpa.
ARG SOURCE_COMMIT=""
RUN printf '%s %s\n' "$(date -u +%Y%m%dT%H%M%SZ)" "${SOURCE_COMMIT:-lokal}" > /etc/sirabtor-rilis

# Data aplikasi (berkas tiruan JSON, unggahan, arsip PDF, cadangan, log) — wajib volume persisten.
VOLUME ["/var/www/html/storage"]

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=90s --retries=3 \
    CMD curl -fsS http://127.0.0.1:8080/up || exit 1

# SIGTERM diteruskan supervisord ke tiap proses (nginx & PHP-FPM berhenti dengan anggun, antrean menuntaskan pekerjaannya).
STOPSIGNAL SIGTERM

ENTRYPOINT ["sirabtor-entrypoint"]
CMD ["supervisord", "-n", "-c", "/etc/supervisor/sirabtor.conf"]
