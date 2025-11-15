#!/bin/bash
set -e

# Build script for DigitalOcean - NO COMPOSER CALLS
# This script only builds Node.js and Python components

echo "Starting build process..."

# Build Node.js frontend if package.json exists
if [ -f "package.json" ] && [ -d "frontend-vue" ]; then
    echo "Building Vue.js frontend..."
    cd frontend-vue
    npm install
    npm run build
    cd ..
    echo "Frontend build completed!"
fi

# Install Python dependencies if requirements.txt exists
if [ -f "requirements.txt" ]; then
    echo "Installing Python dependencies..."
    pip install -r requirements.txt
    echo "Python dependencies installed!"
fi

echo "Build completed successfully - NO COMPOSER CALLED"

