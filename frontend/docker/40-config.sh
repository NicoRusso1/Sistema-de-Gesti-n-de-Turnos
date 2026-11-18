#!/bin/sh
set -e

echo "window.API_URL = '${API_URL}';" > /usr/share/nginx/html/config.js
