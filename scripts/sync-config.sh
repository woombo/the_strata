#!/bin/bash

# Sync configuration from config/sync to profile config directories
# This script loads all .yml files from config/sync, removes uuid and _core,
# and replaces matching files found in web/profiles/custom/the_strata/*/config/*

set -e

# Get the project root directory (parent of scripts directory)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

CONFIG_SYNC_DIR="$PROJECT_ROOT/config/sync"
PROFILE_BASE_DIR="$PROJECT_ROOT/web/profiles/custom/the_strata"
TEMP_DIR=$(mktemp -d)

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Counters
updated=0
skipped=0

# Cleanup temp directory on exit
trap "rm -rf $TEMP_DIR" EXIT

# Function to remove uuid and _core from yml file
clean_yml() {
    local input_file="$1"
    local output_file="$2"

    # Use awk to remove uuid line and _core block
    awk '
    BEGIN { skip_core = 0 }
    /^uuid:/ { next }
    /^_core:$/ { skip_core = 1; next }
    skip_core && /^  / { next }
    skip_core && !/^  / { skip_core = 0 }
    { print }
    ' "$input_file" > "$output_file"
}

echo "Syncing configuration files..."
echo "Source: $CONFIG_SYNC_DIR"
echo "Target: $PROFILE_BASE_DIR/*/config/*"
echo "(Removing uuid and _core from source files)"
echo ""

# Check if config/sync directory exists
if [ ! -d "$CONFIG_SYNC_DIR" ]; then
    echo -e "${RED}Error: config/sync directory not found at $CONFIG_SYNC_DIR${NC}"
    exit 1
fi

# Find all .yml files in config/sync
for sync_file in "$CONFIG_SYNC_DIR"/*.yml; do
    # Skip if no files found
    [ -e "$sync_file" ] || continue

    # Get just the filename
    filename=$(basename "$sync_file")

    # Create cleaned version of sync file
    cleaned_file="$TEMP_DIR/$filename"
    clean_yml "$sync_file" "$cleaned_file"

    # Search for matching files in profile config directories
    # This includes:
    # - web/profiles/custom/the_strata/config/install/
    # - web/profiles/custom/the_strata/modules/*/config/install/
    # - web/profiles/custom/the_strata/modules/*/config/optional/
    while IFS= read -r -d '' target_file; do
        if [ -f "$target_file" ]; then
            # Compare cleaned sync file with target
            if ! cmp -s "$cleaned_file" "$target_file"; then
                cp "$cleaned_file" "$target_file"
                echo -e "${GREEN}Updated:${NC} $target_file"
                ((updated++))
            else
                echo -e "${YELLOW}Skipped (identical):${NC} $filename"
                ((skipped++))
            fi
        fi
    done < <(find "$PROFILE_BASE_DIR" -path "*/config/*" -name "$filename" -print0 2>/dev/null)
done

echo ""
echo "================================"
echo -e "Files updated: ${GREEN}$updated${NC}"
echo -e "Files skipped (identical): ${YELLOW}$skipped${NC}"
echo "================================"
