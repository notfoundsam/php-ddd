#!/usr/bin/env bash

# Get the script directory (dev-tools/)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CERTS_DIR="$SCRIPT_DIR/certs"

echo "🔐 Setting up local SSL certificates..."

# Check if mkcert is installed, if not try to auto-install
if ! command -v mkcert > /dev/null; then
  echo "📦 mkcert not found. Attempting to install..."
  echo ""

  # Detect OS and package manager, then install
  if command -v brew > /dev/null; then
    echo "🍺 Installing mkcert via Homebrew..."
    brew install mkcert
  elif command -v apt-get > /dev/null; then
    echo "📦 Installing mkcert via apt..."
    sudo apt-get update && sudo apt-get install -y libnss3-tools
    curl -JLO "https://dl.filippo.io/mkcert/latest?for=linux/amd64"
    chmod +x mkcert-v*-linux-amd64
    sudo cp mkcert-v*-linux-amd64 /usr/local/bin/mkcert
    rm mkcert-v*-linux-amd64
  elif command -v yum > /dev/null; then
    echo "📦 Installing mkcert via yum..."
    sudo yum install -y nss-tools
    curl -JLO "https://dl.filippo.io/mkcert/latest?for=linux/amd64"
    chmod +x mkcert-v*-linux-amd64
    sudo cp mkcert-v*-linux-amd64 /usr/local/bin/mkcert
    rm mkcert-v*-linux-amd64
  elif command -v choco > /dev/null; then
    echo "🍫 Installing mkcert via Chocolatey..."
    choco install mkcert -y
  else
    echo "❌ Could not auto-install mkcert. No supported package manager found."
    echo ""
    echo "Please install mkcert manually:"
    echo "  📖 https://github.com/FiloSottile/mkcert#installation"
    echo ""
    echo "After installation, run: make setup-ssl"
    exit 1
  fi

  # Verify installation succeeded
  if ! command -v mkcert > /dev/null; then
    echo "❌ mkcert installation failed."
    echo "Please install manually: https://github.com/FiloSottile/mkcert#installation"
    exit 1
  fi

  echo "✅ mkcert installed successfully!"
  echo ""
fi

# Install NSS for Firefox support (certutil)
if command -v brew > /dev/null; then
  if ! command -v certutil > /dev/null 2>&1; then
    echo "🦊 Installing NSS for Firefox certificate support..."
    brew install nss
    echo "✅ NSS installed!"
    echo ""
  fi
elif command -v apt-get > /dev/null; then
  if ! command -v certutil > /dev/null 2>&1; then
    echo "🦊 Installing NSS tools for Firefox certificate support..."
    sudo apt-get install -y libnss3-tools
    echo "✅ NSS tools installed!"
    echo ""
  fi
fi

# Create certs directory if it doesn't exist
mkdir -p "$CERTS_DIR"

# Check if certificates already exist
if [ -f "$CERTS_DIR/php-ddd.test.pem" ]; then
  echo "✓ SSL certificates already exist"
else
  echo "📝 Installing local CA (may require password)..."
  mkcert -install

  echo "🔑 Generating certificates for php-ddd.test and *.php-ddd.test..."
  cd "$CERTS_DIR" && mkcert -cert-file php-ddd.test.pem -key-file php-ddd.test-key.pem \
    php-ddd.test "*.php-ddd.test" localhost 127.0.0.1

  echo "✅ SSL certificates created successfully!"
fi

echo ""
echo "🎉 SSL setup complete!"
echo "   Certificates: $CERTS_DIR/php-ddd.test.pem"
echo "   Private key:  $CERTS_DIR/php-ddd.test-key.pem"
