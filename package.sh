#!/bin/bash
# ==============================================================================
# WordPress Plugin Packaging Script for Anonymous Messages
# ==============================================================================
#
# This script prepares a clean release bundle of the plugin in a standard
# WordPress zip archive format, containing only the necessary production files.
#

# Exit immediately if a command exits with a non-zero status
set -e

echo "=== [1/5] Compiling block editor assets ==="
npm run build

echo "=== [2/5] Cleaning up existing artifacts ==="
rm -f anonymous-messages.zip
rm -rf anonymous-messages

echo "=== [3/5] Creating clean staging folder ==="
mkdir -p anonymous-messages

# Copy files and directories to the staging area
# Only include files necessary to run the plugin on a WordPress production site
cp anonymous-messages.php anonymous-messages/
cp uninstall.php anonymous-messages/
cp README.md anonymous-messages/
cp README-ar.md anonymous-messages/

# Recursively copy directories
cp -r includes anonymous-messages/
cp -r templates anonymous-messages/
cp -r assets anonymous-messages/
cp -r build anonymous-messages/
cp -r languages anonymous-messages/

# Clean up dev files from enqueued folders if any exist
find anonymous-messages -name ".DS_Store" -type f -delete
find anonymous-messages -name "*~" -type f -delete

echo "=== [4/5] Zipping the staging folder ==="
zip -r anonymous-messages.zip anonymous-messages

echo "=== [5/5] Cleaning up staging folder ==="
rm -rf anonymous-messages

echo "=============================================================================="
echo "✔ Success! The plugin has been packaged into 'anonymous-messages.zip'"
echo "  Standard WordPress folder structure maintained inside the archive."
echo "=============================================================================="
