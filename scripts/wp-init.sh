#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."

# Wait a moment for services
sleep 2

docker compose run --rm wpcli '
  wp core install --url=http://localhost:8080 --title="Agrocampo Dev" --admin_user=admin --admin_password=admin --admin_email=admin@example.com --skip-email 
  && wp plugin activate agrocampo-cotizador-pdf
  && wp rewrite structure "/%postname%/" --hard
  && wp rewrite flush --hard
'

echo "Admin: http://localhost:8080/wp-admin (admin / admin)"
echo "Form:  http://localhost:8080/agrocampo-cotizador"
