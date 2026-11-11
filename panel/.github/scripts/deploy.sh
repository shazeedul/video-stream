#!/bin/bash

#==============================================================================
# 🚀 Zero Downtime Deployment Script
#
# Simple, straightforward deployment script with beautiful output.
# Only uses functions where they add real value (validation, output helpers).
#
# Required Environment Variables:
#   - DEPLOY_PATH: Absolute path to the project directory
#   - DEPLOYMENT_BRANCH: Branch or tag to deploy
#   - RELEASE_ID: Unique identifier for this deployment
#   - SKIP_GIT_PULL: Set to "true" to skip git operations (optional)
#==============================================================================

set -e  # Exit on any error

#==============================================================================
# 🎨 Color Definitions
#==============================================================================

readonly GREEN='\033[0;32m'
readonly BLUE='\033[0;34m'
readonly CYAN='\033[0;36m'
readonly MAGENTA='\033[0;35m'
readonly RED='\033[0;31m'
readonly NC='\033[0m'
readonly BOLD='\033[1m'

#==============================================================================
# 📝 Output Helper Functions
#==============================================================================

print_section() {
    echo ""
    echo -e "${BOLD}${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BOLD}${CYAN}$1${NC}"
    echo -e "${BOLD}${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
}

print_step() {
    echo -e "${GREEN}▶${NC} $1"
}

print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

print_info() {
    echo -e "${CYAN}ℹ${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

#==============================================================================
# 🔧 Validation Function
#==============================================================================

validate_environment() {
    local missing_vars=()

    [[ -z "${DEPLOY_PATH:-}" ]] && missing_vars+=("DEPLOY_PATH")
    [[ -z "${DEPLOYMENT_BRANCH:-}" ]] && missing_vars+=("DEPLOYMENT_BRANCH")
    [[ -z "${RELEASE_ID:-}" ]] && missing_vars+=("RELEASE_ID")

    if [[ ${#missing_vars[@]} -gt 0 ]]; then
        print_error "Missing required environment variables: ${missing_vars[*]}"
        exit 1
    fi
}

#==============================================================================
# 🎯 Main Deployment Process
#==============================================================================

# Print banner
echo -e "${BOLD}${MAGENTA}"
echo "╔═══════════════════════════════════════════════════════════════╗"
echo "║                                                               ║"
echo "║            🚀 ZERO DOWNTIME DEPLOYMENT STARTED 🚀             ║"
echo "║                                                               ║"
echo "╚═══════════════════════════════════════════════════════════════╝"
echo -e "${NC}"

# Validate environment
validate_environment
print_info "Deploy Path: ${BOLD}$DEPLOY_PATH${NC}"
print_info "Branch: ${BOLD}$DEPLOYMENT_BRANCH${NC}"
print_info "Release ID: ${BOLD}$RELEASE_ID${NC}"

# ═══════════════════════════════════════════════════════════════
print_section "📂 NAVIGATE TO PROJECT DIRECTORY"
# ═══════════════════════════════════════════════════════════════
print_step "Changing directory to $DEPLOY_PATH"
cd "$DEPLOY_PATH" || {
    print_error "Failed to change directory to $DEPLOY_PATH"
    exit 1
}
print_success "Successfully changed to deployment directory"

# ═══════════════════════════════════════════════════════════════
print_section "⏸️ PAUSE BACKGROUND SERVICES"
# ═══════════════════════════════════════════════════════════════
print_step "Pausing Laravel Horizon workers..."
php artisan horizon:pause || true
print_success "Horizon paused successfully"

# ═══════════════════════════════════════════════════════════════
print_section "🔄 PULL LATEST CODE FROM REPOSITORY"
# ═══════════════════════════════════════════════════════════════
if [[ -z "${SKIP_GIT_PULL:-}" ]]; then
    print_step "Fetching all refs (branches and tags)..."
    git fetch --all --tags

    # Check if it's a branch, tag, or commit
    if git show-ref --verify --quiet "refs/heads/$DEPLOYMENT_BRANCH"; then
        print_step "Checking out branch: $DEPLOYMENT_BRANCH"
        git checkout "$DEPLOYMENT_BRANCH"
        git reset --hard "origin/$DEPLOYMENT_BRANCH"
        git pull origin "$DEPLOYMENT_BRANCH"
        print_success "Branch updated successfully"
    elif git show-ref --verify --quiet "refs/tags/$DEPLOYMENT_BRANCH"; then
        print_step "Checking out tag: $DEPLOYMENT_BRANCH"
        git checkout "tags/$DEPLOYMENT_BRANCH"
        print_success "Tag checked out successfully"
    else
        print_step "Checking out: $DEPLOYMENT_BRANCH (commit or remote ref)"
        git checkout "$DEPLOYMENT_BRANCH"
        print_success "Code updated successfully"
    fi
else
    print_info "Skipping git pull (already done by workflow)"
fi

# ═══════════════════════════════════════════════════════════════
print_section "📦 INSTALL DEPENDENCIES"
# ═══════════════════════════════════════════════════════════════
print_step "Installing PHP dependencies via Composer..."
composer install --no-dev --optimize-autoloader --no-interaction
print_success "PHP dependencies installed"

print_step "Installing Node dependencies via PNPM..."
pnpm install
print_success "Node dependencies installed"

# ═══════════════════════════════════════════════════════════════
print_section "🧹 CLEAR APPLICATION CACHE"
# ═══════════════════════════════════════════════════════════════
print_step "Clearing all optimization caches..."
php artisan optimize:clear
print_success "Cache cleared successfully"

# ═══════════════════════════════════════════════════════════════
print_section "🏗️ BUILD FRONTEND ASSETS"
# ═══════════════════════════════════════════════════════════════
print_step "Building production assets with PNPM..."
pnpm run build
print_success "Frontend assets built successfully"

# ═══════════════════════════════════════════════════════════════
print_section "🗄️ RUN DATABASE MIGRATIONS"
# ═══════════════════════════════════════════════════════════════
print_step "Running database migrations..."
php artisan migrate --force
print_success "Database migrations completed"

# ═══════════════════════════════════════════════════════════════
print_section "🔐 SYNC PERMISSIONS & ROLES"
# ═══════════════════════════════════════════════════════════════
print_step "Synchronizing permissions and roles from definition files..."
php artisan auth:permissions-sync --force --no-user
print_success "Permissions and roles synchronized"

# ═══════════════════════════════════════════════════════════════
print_section "⚡ STEP 9: OPTIMIZE APPLICATION"
# ═══════════════════════════════════════════════════════════════
print_step "Running optimization commands..."
php artisan optimize
print_success "Application optimized"

print_step "Caching configuration..."
php artisan config:cache
print_success "Configuration cached"

print_step "Caching views..."
php artisan view:cache
print_success "Views cached"

print_step "Caching events..."
php artisan event:cache
print_success "Events cached"

# ═══════════════════════════════════════════════════════════════
print_section "🔗 STEP 10: LINK STORAGE AND ASSETS"
# ═══════════════════════════════════════════════════════════════
print_step "Creating storage symlink..."
php artisan storage:link --force
print_success "Storage linked"

# ═══════════════════════════════════════════════════════════════
print_section "🔄 STOP OCTANE"
# ═══════════════════════════════════════════════════════════════
print_step "Stopping Octane workers gracefully..."
php artisan octane:stop
print_success "Octane workers stopped successfully"

# ═══════════════════════════════════════════════════════════════
print_section "▶️ RESUME BACKGROUND SERVICES"
# ═══════════════════════════════════════════════════════════════
print_step "Resuming Laravel Horizon workers..."
php artisan horizon:continue || true
print_success "Horizon resumed successfully"

# Print completion banner
echo ""
echo -e "${BOLD}${GREEN}"
echo "╔═══════════════════════════════════════════════════════════════╗"
echo "║                                                               ║"
echo "║          ✅ DEPLOYMENT COMPLETED SUCCESSFULLY! ✅             ║"
echo "║                                                               ║"
echo "╚═══════════════════════════════════════════════════════════════╝"
echo -e "${NC}"

exit 0
