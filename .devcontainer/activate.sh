#!/usr/bin/env bash

set -eu

# Activate the plugin.
cd "/app"
echo "Activating plugin..."
if ! wp plugin is-active daggerhart-openid-connect-generic 2>/dev/null; then
	wp plugin activate daggerhart-openid-connect-generic --quiet
fi

echo "Done!"
