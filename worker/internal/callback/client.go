package callback

import (
	"bytes"
	"context"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"time"
)

// Status constants for media processing callback.
const (
	StatusProbing = "probing"
	StatusProbed  = "probed"
	StatusFailed  = "failed"
)

// Payload represents the request body sent to the Laravel callback endpoint.
type Payload struct {
	JobID           string         `json:"job_id"`
	VideoID         string         `json:"video_id"`
	Status          string         `json:"status"`
	DurationSeconds int            `json:"duration_seconds,omitempty"`
	Metadata        map[string]any `json:"metadata,omitempty"`
	Error           string         `json:"error,omitempty"`
}

// ClientOption defines a functional option for configuring the Client.
type ClientOption func(*Client)

// WithHTTPClient overrides the default HTTP client.
func WithHTTPClient(httpClient *http.Client) ClientOption {
	return func(c *Client) {
		if httpClient != nil {
			c.httpClient = httpClient
		}
	}
}

// WithMaxRetries configures the maximum number of retry attempts for 5xx/network errors.
func WithMaxRetries(maxRetries int) ClientOption {
	return func(c *Client) {
		if maxRetries >= 0 {
			c.maxRetries = maxRetries
		}
	}
}

// WithBaseBackoff configures the initial backoff duration between retries.
func WithBaseBackoff(baseBackoff time.Duration) ClientOption {
	return func(c *Client) {
		if baseBackoff > 0 {
			c.baseBackoff = baseBackoff
		}
	}
}

// Client manages HTTP callbacks to the platform API.
type Client struct {
	callbackURL    string
	internalSecret string
	httpClient     *http.Client
	maxRetries     int
	baseBackoff    time.Duration
}

// NewClient creates a new callback client.
func NewClient(callbackURL, internalSecret string, opts ...ClientOption) *Client {
	c := &Client{
		callbackURL:    callbackURL,
		internalSecret: internalSecret,
		httpClient: &http.Client{
			Timeout: 10 * time.Second,
		},
		maxRetries:  3,
		baseBackoff: 250 * time.Millisecond,
	}

	for _, opt := range opts {
		opt(c)
	}

	return c
}

// SendStatus dispatches an HTTP POST request to the callback URL with the payload and internal secret header.
// It retries on network failures and 5xx server responses using exponential backoff.
func (c *Client) SendStatus(ctx context.Context, payload Payload) error {
	bodyBytes, err := json.Marshal(payload)
	if err != nil {
		return fmt.Errorf("failed to marshal callback payload: %w", err)
	}

	var lastErr error

	for attempt := 0; attempt <= c.maxRetries; attempt++ {
		if attempt > 0 {
			backoff := c.baseBackoff * (1 << (attempt - 1))
			select {
			case <-ctx.Done():
				return ctx.Err()
			case <-time.After(backoff):
			}
		}

		req, err := http.NewRequestWithContext(ctx, http.MethodPost, c.callbackURL, bytes.NewReader(bodyBytes))
		if err != nil {
			return fmt.Errorf("failed to create http request: %w", err)
		}

		req.Header.Set("Content-Type", "application/json")
		req.Header.Set("Accept", "application/json")
		req.Header.Set("X-Internal-Secret", c.internalSecret)

		resp, err := c.httpClient.Do(req)
		if err != nil {
			lastErr = fmt.Errorf("http request failed: %w", err)
			continue
		}

		// Drain and close response body
		_, _ = io.Copy(io.Discard, resp.Body)
		_ = resp.Body.Close()

		if resp.StatusCode >= 200 && resp.StatusCode < 300 {
			return nil
		}

		if resp.StatusCode >= 500 {
			lastErr = fmt.Errorf("callback server returned 5xx status %d", resp.StatusCode)
			continue
		}

		// Client errors (4xx) or unexpected status codes should not be retried
		return fmt.Errorf("callback server returned status %d", resp.StatusCode)
	}

	return fmt.Errorf("callback failed after %d retries: %w", c.maxRetries, lastErr)
}
