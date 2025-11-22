#!/usr/bin/env bash

# Define domains to map
DOMAINS=(
  "php-ddd.test"
  "images.php-ddd.test"
  "mail.php-ddd.test"
  "dashboard.php-ddd.test"
)

# Hosts file
HOSTS_FILE="/etc/hosts"

# Loop through each domain and add if not exists
for DOMAIN in "${DOMAINS[@]}"; do
  HOST_ENTRY="127.0.0.1 $DOMAIN"

  # Check if this specific domain mapping exists
  if grep -q "127.0.0.1.*\s$DOMAIN\s*$" "$HOSTS_FILE" || grep -q "127.0.0.1.*\s$DOMAIN$" "$HOSTS_FILE"; then
    echo "✓ DNS mapping already exists: $DOMAIN"
  else
    echo "Adding DNS mapping: $DOMAIN"
    # Use sudo to append to /etc/hosts
    echo "$HOST_ENTRY" | sudo tee -a "$HOSTS_FILE" > /dev/null
    echo "✓ Mapping added successfully: $DOMAIN"
  fi
done

echo ""
echo "All DNS mappings are configured!"
