#!/usr/bin/env bash
#
# Create or remove a Convoro on-demand demo subdomain: an nginx vhost pointing
# at the shared Convoro app root plus a per-demo Let's Encrypt cert (HTTP-01).
# The app's HostTenant middleware resolves <slug>.convoro.co to the demo's own
# database, so every demo vhost shares one app root and differs only by cert.
#
# Invoked by the queue worker via:  sudo convoro-demo-site add|remove <slug>
# (allowed without a password by /etc/sudoers.d/convoro-demo).
#
# Install:
#   sudo install -m 0755 convoro-demo-site.sh /usr/local/bin/convoro-demo-site
#
set -euo pipefail

ACTION="${1:-}"
SLUG="${2:-}"

BASE_DOMAIN="convoro.co"
APP_ROOT="/var/www/convoro/public"
FPM_SOCK="unix:/run/php/php-fpm.sock"
CERTBOT_EMAIL="ernestdefoe@gmail.com"
AVAIL="/etc/nginx/sites-available"
ENABLED="/etc/nginx/sites-enabled"

# Hard guard: only ever act on hosts that match the demo slug shape, so this
# helper can never be coerced into touching community/marketing/mail vhosts.
if [[ ! "$SLUG" =~ ^try-[a-z0-9]+$ ]]; then
  echo "refusing bad slug: '$SLUG'" >&2
  exit 2
fi

HOST="${SLUG}.${BASE_DOMAIN}"
VHOST="${AVAIL}/demo-${SLUG}"
LINK="${ENABLED}/demo-${SLUG}"

case "$ACTION" in
  add)
    cat > "$VHOST" <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name ${HOST};
    root ${APP_ROOT};
    index index.php;
    charset utf-8;
    client_max_body_size 20M;

    location / { try_files \$uri \$uri/ /index.php?\$query_string; }
    location = /sw.js { add_header Cache-Control "no-cache"; try_files \$uri =404; }
    location /build/ { add_header Cache-Control "public, max-age=31536000, immutable"; access_log off; try_files \$uri =404; }
    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }
    error_page 404 /index.php;

    location ~ \.php\$ {
        fastcgi_pass ${FPM_SOCK};
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }
    location ~ /\.(?!well-known).* { deny all; }
}
EOF
    ln -sf "$VHOST" "$LINK"
    nginx -t
    systemctl reload nginx
    # HTTP-01 challenge; --nginx adds the 443 server + a port-80 -> 443 redirect.
    certbot --nginx -n --agree-tos -m "$CERTBOT_EMAIL" --redirect -d "$HOST"
    nginx -t
    systemctl reload nginx
    echo "added ${HOST}"
    ;;

  remove)
    certbot delete -n --cert-name "$HOST" >/dev/null 2>&1 || true
    rm -f "$LINK" "$VHOST"
    nginx -t && systemctl reload nginx || true
    echo "removed ${HOST}"
    ;;

  *)
    echo "usage: $0 add|remove <slug>" >&2
    exit 2
    ;;
esac
