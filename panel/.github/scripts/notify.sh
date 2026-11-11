#!/bin/bash

#==============================================================================
# 🚀 Laravel Octane Deployment Notification Script with Release Notes
#
# Sends deployment notifications to various platforms (Slack, Discord, Teams, etc.)
# Now includes GitHub release notes/description in notifications
#==============================================================================

set -e  # Exit on any error, but allow controlled error handling

# Colors for console output
readonly GREEN='\033[0;32m' YELLOW='\033[1;33m' RED='\033[0;31m' NC='\033[0m'

#==============================================================================
# 🐙 GitHub API Functions
#==============================================================================

fetch_release_notes() {
    local release_tag="$1"
    local repo="$2"

    echo -e "${GREEN}📥 Fetching release notes for $release_tag...${NC}" >&2

    # GitHub API URL for the specific release
    local api_url="https://api.github.com/repos/$repo/releases/tags/$release_tag"

    # Build curl headers with authentication if available
    local curl_headers=(
        "-H" "Accept: application/vnd.github.v3+json"
        "-H" "User-Agent: GitHub-Actions-Deploy-Bot"
    )

    # Add authorization header if GitHub token is available
    if [[ -n "${GITHUB_TOKEN:-}" ]]; then
        curl_headers+=("-H" "Authorization: token $GITHUB_TOKEN")
        echo -e "${GREEN}🔑 Using authenticated GitHub API request${NC}" >&2
    else
        echo -e "${YELLOW}⚠️ Using unauthenticated GitHub API request (rate limited)${NC}" >&2
    fi    # Fetch release data from GitHub API with better error handling
    local release_data
    local http_code

    # Use temporary files for better error handling
    local response_file=$(mktemp)
    local error_file=$(mktemp)

    if http_code=$(curl -s -w "%{http_code}" \
                        "${curl_headers[@]}" \
                        "$api_url" \
                        -o "$response_file" \
                        2>"$error_file"); then

        # Check HTTP status code
        case "$http_code" in
            "200")
                release_data=$(cat "$response_file")
                echo -e "${GREEN}✅ GitHub API request successful (HTTP $http_code)${NC}" >&2
                ;;
            "404")
                echo -e "${YELLOW}⚠️ Release $release_tag not found (HTTP 404)${NC}" >&2
                rm -f "$response_file" "$error_file"
                return 1
                ;;
            "403")
                echo -e "${RED}❌ GitHub API rate limit exceeded or access denied (HTTP 403)${NC}" >&2
                echo -e "${YELLOW}💡 Consider adding GITHUB_TOKEN for higher rate limits${NC}" >&2
                rm -f "$response_file" "$error_file"
                return 1
                ;;
            "401")
                echo -e "${RED}❌ GitHub API authentication failed (HTTP 401)${NC}" >&2
                rm -f "$response_file" "$error_file"
                return 1
                ;;
            *)
                echo -e "${RED}❌ GitHub API request failed with HTTP $http_code${NC}" >&2
                if [[ -s "$error_file" ]]; then
                    echo -e "${RED}Error details: $(cat "$error_file")${NC}" >&2
                fi
                rm -f "$response_file" "$error_file"
                return 1
                ;;
        esac
    else
        echo -e "${RED}❌ Failed to make GitHub API request${NC}" >&2
        if [[ -s "$error_file" ]]; then
            echo -e "${RED}cURL error: $(cat "$error_file")${NC}" >&2
        fi
        rm -f "$response_file" "$error_file"
        return 1
    fi

    # Clean up temporary files
    rm -f "$error_file"

    # Extract release body/notes using improved JSON parsing
    local release_notes

    # First, try to extract the body field more robustly
    # Handle multi-line content and escaped characters better
    release_notes=$(echo "$release_data" | \
        python3 -c "
import sys, json
try:
    data = json.load(sys.stdin)
    body = data.get('body', '')
    if body and body.strip():
        print(body)
    else:
        sys.exit(1)
except:
    sys.exit(1)
" 2>/dev/null)

    # Fallback to sed-based parsing if python3 is not available
    if [[ $? -ne 0 ]] || [[ -z "$release_notes" ]]; then
        echo -e "${YELLOW}⚠️ Python JSON parsing failed, trying sed fallback...${NC}" >&2

        # Improved sed-based JSON extraction
        release_notes=$(echo "$release_data" | \
            sed -n 's/.*"body"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' | \
            head -1 | \
            sed 's/\\n/\n/g' | \
            sed 's/\\r//g' | \
            sed 's/\\t/    /g' | \
            sed 's/\\"/"/g' | \
            sed 's/\\\\/\\/g')
    fi

    # Clean up response file
    rm -f "$response_file"

    if [[ -n "$release_notes" && "$release_notes" != "null" && "$release_notes" != "" ]]; then
        # Remove leading/trailing whitespace
        release_notes=$(echo "$release_notes" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')

        # Adjust length for Slack API limits (4,000 chars optimal, 40,000 max)
        # Using 3,800 to leave room for other message content
        if [[ ${#release_notes} -gt 3800 ]]; then
            # Try to truncate at a word boundary
            local truncated="${release_notes:0:3797}"
            local last_space=$(echo "$truncated" | grep -o '.*[[:space:]]' | tail -c 4)
            if [[ -n "$last_space" ]]; then
                release_notes="${truncated%[[:space:]]*}..."
            else
                release_notes="${truncated}..."
            fi
            echo -e "${YELLOW}⚠️ Release notes truncated to fit Slack API limits${NC}" >&2
        fi

        echo "$release_notes"
        echo -e "${GREEN}✅ Release notes fetched successfully (${#release_notes} characters)${NC}" >&2
        return 0
    else
        echo -e "${YELLOW}⚠️ No release notes found for $release_tag${NC}" >&2
        return 1
    fi
}

#==============================================================================
# 🔗 Deployment Status Configuration
#==============================================================================

get_status_config() {
    local status="$1"

    case "$status" in
        "success")
            echo "✅" "good" "#28a745" "SUCCESS"
            ;;
        "failure")
            echo "❌" "danger" "#dc3545" "FAILED"
            ;;
        "cancelled")
            echo "⏹️" "warning" "#ffc107" "CANCELLED"
            ;;
        "skipped")
            echo "⏭️" "warning" "#6c757d" "SKIPPED"
            ;;
        *)
            echo "🔄" "warning" "#6c757d" "UNKNOWN"
            ;;
    esac
}

#==============================================================================
# 🔗 Slack Notifications
#==============================================================================

send_slack_notification() {
    local webhook_url="$1"
    local status="$2"
    local release_notes="$3"

    echo -e "${GREEN}📤 Sending Slack notification...${NC}"

    # Get status configuration
    local status_config=($(get_status_config "$status"))
    local emoji="${status_config[0]}"
    local color="${status_config[1]}"
    local status_text="${status_config[3]}"

    # Build deployment fields
    local fields=""
    if [ "$GITHUB_EVENT_NAME" = "release" ] && [ -n "${RELEASE_TAG:-}" ]; then
        fields='"fields": [
            {"title": "Release Tag", "value": "'"$RELEASE_TAG"'", "short": true},
            {"title": "Release Name", "value": "'"${RELEASE_NAME:-$RELEASE_TAG}"'", "short": true},
            {"title": "Environment", "value": "'"$ENVIRONMENT"'", "short": true},
            {"title": "Release ID", "value": "'"$RELEASE_ID"'", "short": true},
            {"title": "Branch", "value": "'"$GITHUB_REF_NAME"'", "short": true},
            {"title": "Triggered By", "value": "'"$GITHUB_ACTOR"'", "short": true}
        ],'
    else
        fields='"fields": [
            {"title": "Environment", "value": "'"$ENVIRONMENT"'", "short": true},
            {"title": "Release ID", "value": "'"$RELEASE_ID"'", "short": true},
            {"title": "Branch", "value": "'"$GITHUB_REF_NAME"'", "short": true},
            {"title": "Trigger", "value": "'"$GITHUB_EVENT_NAME"'", "short": true},
            {"title": "Triggered By", "value": "'"$GITHUB_ACTOR"'", "short": true},
            {"title": "Commit", "value": "'"${GITHUB_SHA:0:8}"'", "short": true}
        ],'
    fi

    # Add release notes section if available
    local release_notes_section=""
    if [[ -n "$release_notes" ]]; then
        # Escape quotes and newlines for JSON
        local escaped_notes=$(echo "$release_notes" | sed 's/"/\\"/g' | sed ':a;N;$!ba;s/\n/\\n/g')

        # Choose appropriate styling based on deployment status
        local notes_title="📔 Release Notes"
        local notes_color="$color"

        if [[ "$status" != "success" ]]; then
            notes_title="📔 Release Notes (Deployment $status_text)"
            notes_color="#ffc107"  # Warning color for non-successful deployments
        fi

        release_notes_section=', {
            "title": "'"$notes_title"'",
            "text": "'"$escaped_notes"'",
            "color": "'"$notes_color"'"
        }'
    fi

    # Create Slack payload
    local payload='{
        "attachments": [{
            "color": "'"$color"'",
            "title": "'"$emoji Laravel Octane Deployment $status_text"'",
            "title_link": "'"$GITHUB_SERVER_URL/$GITHUB_REPOSITORY/actions/runs/$GITHUB_RUN_ID"'",
            '"$fields"'
            "footer": "GitHub Actions • '"$GITHUB_REPOSITORY"'",
            "footer_icon": "https://github.githubassets.com/images/modules/logos_page/GitHub-Mark.png",
            "ts": '"$(date +%s)"'
        }'"$release_notes_section"']
    }'

    # Send notification
    echo -e "${GREEN}🔍 Sending Slack payload...${NC}" >&2

    if curl -f -s -X POST "$webhook_url" \
        -H "Content-Type: application/json" \
        -d "$payload" \
        -w "HTTP Status: %{http_code}\n" >&2; then
        echo -e "${GREEN}✅ Slack notification sent successfully${NC}"
        return 0
    else
        local curl_exit_code=$?
        echo -e "${RED}❌ Failed to send Slack notification (curl exit code: $curl_exit_code)${NC}"
        echo -e "${YELLOW}💡 Debug: Payload length: ${#payload} characters${NC}" >&2
        echo -e "${YELLOW}💡 Debug: First 300 chars of payload: ${payload:0:300}...${NC}" >&2

        # Try to send a simple test payload to validate webhook URL
        local test_payload='{"text":"Test notification from deployment script"}'
        echo -e "${YELLOW}🧪 Testing webhook with simple payload...${NC}" >&2
        if curl -f -s -X POST "$webhook_url" \
            -H "Content-Type: application/json" \
            -d "$test_payload" >/dev/null 2>&1; then
            echo -e "${YELLOW}⚠️ Webhook URL is valid, but main payload failed${NC}" >&2
        else
            echo -e "${RED}❌ Webhook URL test also failed - check the URL${NC}" >&2
        fi

        return 1
    fi
}

#==============================================================================
# 📤 Discord Notifications
#==============================================================================

send_discord_notification() {
    local webhook_url="$1"
    local status="$2"
    local release_notes="$3"

    echo -e "${GREEN}📤 Sending Discord notification...${NC}"

    # Get status configuration
    local status_config=($(get_status_config "$status"))
    local emoji="${status_config[0]}"
    local color_hex="${status_config[2]}"
    local status_text="${status_config[3]}"

    # Convert hex color to decimal
    local color_decimal=$((16#${color_hex#\#}))

    # Build embed fields
    local fields=""
    if [ "$GITHUB_EVENT_NAME" = "release" ] && [ -n "${RELEASE_TAG:-}" ]; then
        fields='"fields": [
            {"name": "Release Tag", "value": "'"$RELEASE_TAG"'", "inline": true},
            {"name": "Environment", "value": "'"$ENVIRONMENT"'", "inline": true},
            {"name": "Branch", "value": "'"$GITHUB_REF_NAME"'", "inline": true},
            {"name": "Release ID", "value": "`'"$RELEASE_ID"'`", "inline": false},
            {"name": "Triggered By", "value": "'"$GITHUB_ACTOR"'", "inline": true}'
    else
        fields='"fields": [
            {"name": "Environment", "value": "'"$ENVIRONMENT"'", "inline": true},
            {"name": "Branch", "value": "'"$GITHUB_REF_NAME"'", "inline": true},
            {"name": "Trigger", "value": "'"$GITHUB_EVENT_NAME"'", "inline": true},
            {"name": "Release ID", "value": "`'"$RELEASE_ID"'`", "inline": false},
            {"name": "Commit", "value": "`'"${GITHUB_SHA:0:8}"'`", "inline": true},
            {"name": "Triggered By", "value": "'"$GITHUB_ACTOR"'", "inline": true}'
    fi

    # Add release notes field if available
    if [[ -n "$release_notes" ]]; then
        # Escape quotes for JSON and limit length for Discord (max 1024 chars per embed field)
        local escaped_notes=$(echo "$release_notes" | sed 's/"/\\"/g')
        if [[ ${#escaped_notes} -gt 1020 ]]; then
            escaped_notes="${escaped_notes:0:1017}..."
        fi

        # Choose appropriate title based on deployment status
        local notes_title="📔 Release Notes"
        if [[ "$status" != "success" ]]; then
            notes_title="📔 Release Notes (Deployment $status_text)"
        fi

        fields="$fields"', {"name": "'"$notes_title"'", "value": "'"$escaped_notes"'", "inline": false}'
    fi

    fields="$fields"'],'

    # Create Discord payload
    local payload='{
        "embeds": [{
            "title": "'"$emoji Laravel Octane Deployment $status_text"'",
            "url": "'"$GITHUB_SERVER_URL/$GITHUB_REPOSITORY/actions/runs/$GITHUB_RUN_ID"'",
            "color": '"$color_decimal"',
            '"$fields"'
            "footer": {
                "text": "GitHub Actions • '"$GITHUB_REPOSITORY"'",
                "icon_url": "https://github.githubassets.com/images/modules/logos_page/GitHub-Mark.png"
            },
            "timestamp": "'"$(date -u +%Y-%m-%dT%H:%M:%SZ)"'"
        }]
    }'

    # Send notification
    if curl -f -s -X POST "$webhook_url" \
        -H "Content-Type: application/json" \
        -d "$payload" > /dev/null; then
        echo -e "${GREEN}✅ Discord notification sent successfully${NC}"
        return 0
    else
        echo -e "${RED}❌ Failed to send Discord notification${NC}"
        return 1
    fi
}

#==============================================================================
# 📧 Microsoft Teams Notifications
#==============================================================================

send_teams_notification() {
    local webhook_url="$1"
    local status="$2"
    local release_notes="$3"

    echo -e "${GREEN}📤 Sending Teams notification...${NC}"

    # Get status configuration
    local status_config=($(get_status_config "$status"))
    local emoji="${status_config[0]}"
    local color_hex="${status_config[2]}"
    local status_text="${status_config[3]}"

    # Build facts array
    local facts=""
    if [ "$GITHUB_EVENT_NAME" = "release" ] && [ -n "${RELEASE_TAG:-}" ]; then
        facts='"facts": [
            {"name": "Release Tag", "value": "'"$RELEASE_TAG"'"},
            {"name": "Environment", "value": "'"$ENVIRONMENT"'"},
            {"name": "Branch", "value": "'"$GITHUB_REF_NAME"'"},
            {"name": "Release ID", "value": "'"$RELEASE_ID"'"},
            {"name": "Triggered By", "value": "'"$GITHUB_ACTOR"'"}
        ],'
    else
        facts='"facts": [
            {"name": "Environment", "value": "'"$ENVIRONMENT"'"},
            {"name": "Branch", "value": "'"$GITHUB_REF_NAME"'"},
            {"name": "Trigger", "value": "'"$GITHUB_EVENT_NAME"'"},
            {"name": "Release ID", "value": "'"$RELEASE_ID"'"},
            {"name": "Commit", "value": "'"${GITHUB_SHA:0:8}"'"},
            {"name": "Triggered By", "value": "'"$GITHUB_ACTOR"'"}
        ],'
    fi

    # Prepare release notes section
    local release_notes_section=""
    if [[ -n "$release_notes" ]]; then
        # Escape quotes and format for Teams
        local escaped_notes=$(echo "$release_notes" | sed 's/"/\\"/g')

        # Choose appropriate title based on deployment status
        local notes_title="📔 Release Notes"
        if [[ "$status" != "success" ]]; then
            notes_title="📔 Release Notes (Deployment $status_text)"
        fi

        release_notes_section=', {
            "activityTitle": "'"$notes_title"'",
            "activityText": "'"$escaped_notes"'",
            "markdown": true
        }'
    fi

    # Create Teams payload
    local payload='{
        "@type": "MessageCard",
        "@context": "https://schema.org/extensions",
        "summary": "Laravel Octane Deployment '"$status_text"'",
        "themeColor": "'"${color_hex#\#}"'",
        "sections": [{
            "activityTitle": "'"$emoji Laravel Octane Deployment $status_text"'",
            "activitySubtitle": "'"$GITHUB_REPOSITORY"'",
            '"$facts"'
            "markdown": true
        }'"$release_notes_section"'],
        "potentialAction": [{
            "@type": "OpenUri",
            "name": "View Workflow Run",
            "targets": [{
                "os": "default",
                "uri": "'"$GITHUB_SERVER_URL/$GITHUB_REPOSITORY/actions/runs/$GITHUB_RUN_ID"'"
            }]
        }]
    }'

    # Send notification
    if curl -f -s -X POST "$webhook_url" \
        -H "Content-Type: application/json" \
        -d "$payload" > /dev/null; then
        echo -e "${GREEN}✅ Teams notification sent successfully${NC}"
        return 0
    else
        echo -e "${RED}❌ Failed to send Teams notification${NC}"
        return 1
    fi
}

#==============================================================================
# 🌐 Generic Webhook Notifications
#==============================================================================

send_generic_notification() {
    local webhook_url="$1"
    local status="$2"
    local release_notes="$3"

    echo -e "${GREEN}📤 Sending generic webhook notification...${NC}"

    # Build payload with conditional release information
    local payload=""
    if [ "$GITHUB_EVENT_NAME" = "release" ] && [ -n "${RELEASE_TAG:-}" ]; then
        # Escape quotes for JSON
        local escaped_notes=""
        if [[ -n "$release_notes" ]]; then
            escaped_notes=$(echo "$release_notes" | sed 's/"/\\"/g' | sed ':a;N;$!ba;s/\n/\\n/g')
        fi

        payload='{
            "event": "deployment",
            "status": "'"$status"'",
            "environment": "'"$ENVIRONMENT"'",
            "release_id": "'"$RELEASE_ID"'",
            "trigger": "release",
            "release": {
                "tag": "'"$RELEASE_TAG"'",
                "name": "'"${RELEASE_NAME:-$RELEASE_TAG}"'",
                "notes": "'"$escaped_notes"'"
            },
            "repository": {
                "name": "'"$GITHUB_REPOSITORY"'",
                "branch": "'"$GITHUB_REF_NAME"'",
                "commit": "'"$GITHUB_SHA"'"
            },
            "workflow": {
                "run_id": "'"$GITHUB_RUN_ID"'",
                "url": "'"$GITHUB_SERVER_URL/$GITHUB_REPOSITORY/actions/runs/$GITHUB_RUN_ID"'",
                "actor": "'"$GITHUB_ACTOR"'"
            },
            "timestamp": "'"$(date -u +%Y-%m-%dT%H:%M:%SZ)"'"
        }'
    else
        payload='{
            "event": "deployment",
            "status": "'"$status"'",
            "environment": "'"$ENVIRONMENT"'",
            "release_id": "'"$RELEASE_ID"'",
            "trigger": "'"$GITHUB_EVENT_NAME"'",
            "repository": {
                "name": "'"$GITHUB_REPOSITORY"'",
                "branch": "'"$GITHUB_REF_NAME"'",
                "commit": "'"$GITHUB_SHA"'"
            },
            "workflow": {
                "run_id": "'"$GITHUB_RUN_ID"'",
                "url": "'"$GITHUB_SERVER_URL/$GITHUB_REPOSITORY/actions/runs/$GITHUB_RUN_ID"'",
                "actor": "'"$GITHUB_ACTOR"'"
            },
            "timestamp": "'"$(date -u +%Y-%m-%dT%H:%M:%SZ)"'"
        }'
    fi

    # Send notification
    if curl -f -s -X POST "$webhook_url" \
        -H "Content-Type: application/json" \
        -d "$payload" > /dev/null; then
        echo -e "${GREEN}✅ Generic webhook notification sent successfully${NC}"
        return 0
    else
        echo -e "${RED}❌ Failed to send generic webhook notification${NC}"
        return 1
    fi
}

#==============================================================================
# 📊 Summary Generation
#==============================================================================

generate_summary() {
    local status="$1"
    local release_notes="$2"

    # Check if GITHUB_STEP_SUMMARY is available and writable
    if [[ -z "${GITHUB_STEP_SUMMARY:-}" ]]; then
        echo -e "${YELLOW}⚠️ GitHub Step Summary not available, skipping summary generation${NC}"
        return 0
    fi

    # Test if we can write to the summary file
    if ! touch "$GITHUB_STEP_SUMMARY" 2>/dev/null; then
        echo -e "${YELLOW}⚠️ Cannot write to GitHub Step Summary file, skipping summary generation${NC}"
        return 0
    fi

    echo -e "${GREEN}📝 Generating GitHub Step Summary...${NC}"

    # Create summary content in a more robust way
    {
        echo "## 🚀 Deployment Notification Summary"
        echo ""

        # Status badge
        case "$status" in
            "success") echo "![Status](https://img.shields.io/badge/Status-SUCCESS-green)" ;;
            "failure") echo "![Status](https://img.shields.io/badge/Status-FAILED-red)" ;;
            "cancelled") echo "![Status](https://img.shields.io/badge/Status-CANCELLED-yellow)" ;;
            *) echo "![Status](https://img.shields.io/badge/Status-UNKNOWN-grey)" ;;
        esac

        echo ""
        echo "### Deployment Details"
        echo "| Field | Value |"
        echo "|-------|-------|"
        echo "| **Environment** | \`${ENVIRONMENT:-unknown}\` |"
        echo "| **Release ID** | \`${RELEASE_ID:-unknown}\` |"
        echo "| **Status** | $status |"
        echo "| **Trigger** | ${GITHUB_EVENT_NAME:-unknown} |"

        if [ "${GITHUB_EVENT_NAME:-}" = "release" ] && [ -n "${RELEASE_TAG:-}" ]; then
            echo "| **Release Tag** | \`$RELEASE_TAG\` |"
            echo "| **Release Name** | ${RELEASE_NAME:-$RELEASE_TAG} |"
        fi

        echo "| **Branch** | \`${GITHUB_REF_NAME:-unknown}\` |"
        echo "| **Commit** | \`${GITHUB_SHA:0:8}\` |"
        echo "| **Actor** | ${GITHUB_ACTOR:-unknown} |"
        echo ""

        # Add release notes if available
        if [[ -n "$release_notes" ]]; then
            if [[ "$status" == "success" ]]; then
                echo "### 📔 Release Notes"
            else
                echo "### 📔 Release Notes (Deployment $status)"
            fi
            echo ""
            echo "$release_notes"
            echo ""
        fi

        # Notification status - only show configured webhooks
        local summary_webhook_count=0
        local webhook_table=""

        if [ -n "${SLACK_WEBHOOK_URL:-}" ]; then
            webhook_table+="| Slack | ✅ Configured |\n"
            summary_webhook_count=$((summary_webhook_count + 1))
        fi

        if [ -n "${DISCORD_WEBHOOK_URL:-}" ]; then
            webhook_table+="| Discord | ✅ Configured |\n"
            summary_webhook_count=$((summary_webhook_count + 1))
        fi

        if [ -n "${TEAMS_WEBHOOK_URL:-}" ]; then
            webhook_table+="| Teams | ✅ Configured |\n"
            summary_webhook_count=$((summary_webhook_count + 1))
        fi

        if [ -n "${GENERIC_WEBHOOK_URL:-}" ]; then
            webhook_table+="| Generic Webhook | ✅ Configured |\n"
            summary_webhook_count=$((summary_webhook_count + 1))
        fi

        if [ $summary_webhook_count -gt 0 ]; then
            echo "### Notification Status"
            echo "| Platform | Status |"
            echo "|----------|--------|"
            echo -e "$webhook_table"
        else
            echo "### Notification Status"
            echo "No notification webhooks configured."
            echo ""
        fi

    } >> "$GITHUB_STEP_SUMMARY" 2>/dev/null

    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✅ GitHub Step Summary generated successfully${NC}"
        return 0
    else
        echo -e "${YELLOW}⚠️ Failed to write GitHub Step Summary${NC}"
        return 0  # Don't fail the entire script for summary issues
    fi
}

#==============================================================================
# 🎯 Main Notification Process
#==============================================================================

main() {
    echo -e "${GREEN}📤 Starting deployment notifications with release notes...${NC}"

    # Validate required environment variables
    local required_vars=(
        "DEPLOYMENT_STATUS" "RELEASE_ID" "ENVIRONMENT"
        "GITHUB_REPOSITORY" "GITHUB_RUN_ID" "GITHUB_SERVER_URL"
        "GITHUB_ACTOR" "GITHUB_EVENT_NAME" "GITHUB_REF_NAME" "GITHUB_SHA"
    )

    local missing_vars=()
    for var in "${required_vars[@]}"; do
        if [ -z "${!var:-}" ]; then
            missing_vars+=("$var")
        fi
    done

    if [ ${#missing_vars[@]} -gt 0 ]; then
        echo -e "${RED}❌ Missing required environment variables: ${missing_vars[*]}${NC}"
        exit 1
    fi

    echo -e "${GREEN}✅ All required environment variables are present${NC}"

    local status="${DEPLOYMENT_STATUS:-unknown}"
    local notification_count=0
    local failed_notifications=0
    local release_notes=""

    echo -e "${GREEN}📊 Deployment Status: $status${NC}"
    echo -e "${GREEN}🎯 Environment: $ENVIRONMENT${NC}"
    echo -e "${GREEN}🏷️ Release ID: $RELEASE_ID${NC}"


    # Fetch release notes for release deployments (regardless of deployment status)
    # This way we can show what was supposed to be deployed even if deployment failed
    if [ "$GITHUB_EVENT_NAME" = "release" ] && [ -n "${RELEASE_TAG:-}" ]; then
        echo -e "${GREEN}📤 Attempting to fetch release notes for release deployment...${NC}"
        if release_notes=$(fetch_release_notes "$RELEASE_TAG" "$GITHUB_REPOSITORY"); then
            echo -e "${GREEN}✅ Release notes fetched and will be included in notifications${NC}"
            echo -e "${GREEN}📝 Preview: ${release_notes:0:100}...${NC}"
        else
            echo -e "${YELLOW}⚠️ Could not fetch release notes, continuing without them${NC}"
            release_notes=""
        fi
    # Also try to fetch release notes for manual deployments if a release tag is specified
    elif [ "$GITHUB_EVENT_NAME" = "workflow_dispatch" ] && [[ "${RELEASE_ID:-}" =~ ^v?[0-9]+\.[0-9]+\.[0-9]+ ]]; then
        echo -e "${GREEN}📤 Manual deployment detected with version-like release ID, attempting to fetch release notes...${NC}"
        if release_notes=$(fetch_release_notes "$RELEASE_ID" "$GITHUB_REPOSITORY"); then
            echo -e "${GREEN}✅ Release notes fetched for manual deployment${NC}"
            echo -e "${GREEN}📝 Preview: ${release_notes:0:100}...${NC}"
        else
            echo -e "${YELLOW}⚠️ Could not fetch release notes for manual deployment, continuing without them${NC}"
            release_notes=""
        fi
    else
        echo -e "${GREEN}📤 Skipping release notes fetch (not a release deployment)${NC}"
        if [ "$GITHUB_EVENT_NAME" = "workflow_dispatch" ]; then
            echo -e "${GREEN}💡 Tip: Use a release tag (e.g., v1.0.0) as REPO_BRANCH to include release notes${NC}"
        fi
    fi

    # Debug: Show configured webhooks
    echo -e "${GREEN}🔧 Configured webhooks:${NC}"
    local webhook_count=0

    if [ -n "${SLACK_WEBHOOK_URL:-}" ]; then
        echo -e "${GREEN}  - Slack: ✅${NC}"
        webhook_count=$((webhook_count + 1))
    fi

    if [ -n "${DISCORD_WEBHOOK_URL:-}" ]; then
        echo -e "${GREEN}  - Discord: ✅${NC}"
        webhook_count=$((webhook_count + 1))
    fi

    if [ -n "${TEAMS_WEBHOOK_URL:-}" ]; then
        echo -e "${GREEN}  - Teams: ✅${NC}"
        webhook_count=$((webhook_count + 1))
    fi

    if [ -n "${GENERIC_WEBHOOK_URL:-}" ]; then
        echo -e "${GREEN}  - Generic Webhook: ✅${NC}"
        webhook_count=$((webhook_count + 1))
    fi

    if [ $webhook_count -eq 0 ]; then
        echo -e "${YELLOW}  - No webhooks configured${NC}"
    else
        echo -e "${GREEN}  Total configured: $webhook_count${NC}"
    fi

    # Send notifications to configured platforms
    echo -e "${GREEN}📤 Starting notification sending process...${NC}"

    # Disable exit on error temporarily for notification sending
    set +e

    if [ -n "${SLACK_WEBHOOK_URL:-}" ]; then
        echo -e "${GREEN}📤 Attempting Slack notification...${NC}"
        if send_slack_notification "$SLACK_WEBHOOK_URL" "$status" "$release_notes"; then
            notification_count=$((notification_count + 1))
            echo -e "${GREEN}✅ Slack notification succeeded${NC}"
        else
            failed_notifications=$((failed_notifications + 1))
            echo -e "${RED}❌ Slack notification failed${NC}"
        fi
    fi

    if [ -n "${DISCORD_WEBHOOK_URL:-}" ]; then
        if send_discord_notification "$DISCORD_WEBHOOK_URL" "$status" "$release_notes"; then
            notification_count=$((notification_count + 1))
        else
            failed_notifications=$((failed_notifications + 1))
        fi
    fi

    if [ -n "${TEAMS_WEBHOOK_URL:-}" ]; then
        if send_teams_notification "$TEAMS_WEBHOOK_URL" "$status" "$release_notes"; then
            notification_count=$((notification_count + 1))
        else
            failed_notifications=$((failed_notifications + 1))
        fi
    fi

    if [ -n "${GENERIC_WEBHOOK_URL:-}" ]; then
        if send_generic_notification "$GENERIC_WEBHOOK_URL" "$status" "$release_notes"; then
            notification_count=$((notification_count + 1))
        else
            failed_notifications=$((failed_notifications + 1))
        fi
    fi

    # Re-enable exit on error
    set -e

    echo -e "${GREEN}📧 Notification sending completed. Sent: $notification_count, Failed: $failed_notifications${NC}"

    # Generate summary (don't fail if this fails)
    echo -e "${GREEN}📝 Generating summary...${NC}"
    generate_summary "$status" || echo -e "${YELLOW}⚠️ Summary generation had issues, but continuing...${NC}"
    echo -e "${GREEN}📝 Summary generation completed${NC}"

    # Final status - more forgiving for development/testing
    if [ $notification_count -eq 0 ] && [ $failed_notifications -eq 0 ]; then
        echo -e "${YELLOW}⚠️ No notifications sent - no webhooks configured${NC}"
        echo -e "${GREEN}💡 Configure SLACK_WEBHOOK_URL, DISCORD_WEBHOOK_URL, TEAMS_WEBHOOK_URL, or GENERIC_WEBHOOK_URL to enable notifications${NC}"
        exit 0  # Not an error if no webhooks are configured
    elif [ $failed_notifications -eq 0 ]; then
        echo -e "${GREEN}✅ All notifications sent successfully ($notification_count)${NC}"
        exit 0  # Success
    else
        echo -e "${YELLOW}⚠️ Sent $notification_count notifications, $failed_notifications failed${NC}"
        # For development: Don't fail deployment if notifications fail
        # In production, you might want to change this behavior
        if [ $notification_count -gt 0 ]; then
            echo -e "${GREEN}✅ At least one notification was sent successfully${NC}"
            exit 0
        else
            echo -e "${YELLOW}⚠️ All notification attempts failed, but continuing deployment${NC}"
            echo -e "${GREEN}💡 Check webhook URLs and network connectivity for future deployments${NC}"
            exit 0  # Changed from exit 1 to exit 0 for more forgiving behavior
        fi
    fi
}

# Execute main notification process
main "$@"
