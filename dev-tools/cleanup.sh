#!/usr/bin/env bash

echo "🧹 Cleaning up local development environment..."
echo ""

# Get the script directory (dev-tools/)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CERTS_DIR="$SCRIPT_DIR/certs"

# Hosts file
HOSTS_FILE="/etc/hosts"

# List of domains to remove
DOMAINS=(
  "php-ddd.test"
  "images.php-ddd.test"
  "mail.php-ddd.test"
  "dashboard.php-ddd.test"
)

# =============================================================================
# 1. Remove DNS mappings from /etc/hosts
# =============================================================================
echo "📝 Removing DNS mappings from $HOSTS_FILE..."

ENTRIES_REMOVED=0
for DOMAIN in "${DOMAINS[@]}"; do
  # Check if entry exists (using grep -F for literal match)
  if grep -q "127.0.0.1 $DOMAIN" "$HOSTS_FILE"; then
    echo "  ✓ Removing: $DOMAIN"
    # Remove the line containing this domain (using literal match)
    sudo sed -i.backup "/127\.0\.0\.1 $DOMAIN/d" "$HOSTS_FILE"
    ENTRIES_REMOVED=$((ENTRIES_REMOVED + 1))
  fi
done

if [ $ENTRIES_REMOVED -eq 0 ]; then
  echo "  ℹ️  No DNS entries found to remove"
else
  echo "  ✅ Removed $ENTRIES_REMOVED DNS entries"
  echo "  💾 Backup saved: ${HOSTS_FILE}.backup"
fi

echo ""

# =============================================================================
# 2. Remove SSL certificates
# =============================================================================
echo "🔐 Removing SSL certificates..."

if [ -d "$CERTS_DIR" ]; then
  CERT_COUNT=$(find "$CERTS_DIR" -type f | wc -l | xargs)

  if [ "$CERT_COUNT" -gt 0 ]; then
    rm -rf "$CERTS_DIR"
    echo "  ✅ Removed $CERT_COUNT certificate file(s)"
  else
    echo "  ℹ️  No certificates found to remove"
  fi
else
  echo "  ℹ️  Certificates directory does not exist"
fi

echo ""

# =============================================================================
# 3. Optional: Uninstall mkcert CA
# =============================================================================
echo "🔒 mkcert CA Information:"
echo ""
echo "  The mkcert root CA is still installed in your system."
echo "  This CA might be used by other projects."
echo ""
echo "  To completely uninstall the mkcert CA:"
echo "    $ mkcert -uninstall"
echo ""
echo "  ⚠️  Warning: This will affect ALL projects using mkcert!"
echo ""

read -p "Do you want to uninstall the mkcert CA now? (y/N): " -n 1 -r
echo

if [[ $REPLY =~ ^[Yy]$ ]]; then
  echo "  🗑️  Uninstalling mkcert CA..."
  mkcert -uninstall
  echo "  ✅ mkcert CA uninstalled"
else
  echo "  ℹ️  Keeping mkcert CA installed"
fi

echo ""

# =============================================================================
# 4. Summary
# =============================================================================
echo "╔════════════════════════════════════════════╗"
echo "║         🎉 Cleanup Complete!               ║"
echo "╚════════════════════════════════════════════╝"
echo ""
echo "Cleaned up:"
echo "  ✅ DNS mappings removed from /etc/hosts"
echo "  ✅ SSL certificates removed"
echo ""
echo "To fully clean up Docker resources, run:"
echo "  $ make clean"
echo ""
