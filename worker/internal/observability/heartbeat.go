package observability

import (
	"bytes"
	"context"
	"encoding/json"
	"fmt"
	"log/slog"
	"net/http"
	"os"
	"time"

	"github.com/google/uuid"
)

// HeartbeatPayload represents the JSON body sent to the Laravel heartbeat endpoint.
type HeartbeatPayload struct {
	WorkerID string `json:"worker_id"`
	Hostname string `json:"hostname"`
	Status   string `json:"status"`
}

// HeartbeatOption defines a functional option to configure HeartbeatPublisher.
type HeartbeatOption func(*HeartbeatPublisher)

// WithHTTPClient overrides the HTTP client.
func WithHTTPClient(client *http.Client) HeartbeatOption {
	return func(hp *HeartbeatPublisher) {
		if client != nil {
			hp.httpClient = client
		}
	}
}

// WithInterval overrides the periodic heartbeat interval.
func WithInterval(interval time.Duration) HeartbeatOption {
	return func(hp *HeartbeatPublisher) {
		if interval > 0 {
			hp.interval = interval
		}
	}
}

// WithWorkerID overrides the auto-generated worker ID.
func WithWorkerID(workerID string) HeartbeatOption {
	return func(hp *HeartbeatPublisher) {
		if workerID != "" {
			hp.workerID = workerID
		}
	}
}

// WithHostname overrides the detected hostname.
func WithHostname(hostname string) HeartbeatOption {
	return func(hp *HeartbeatPublisher) {
		if hostname != "" {
			hp.hostname = hostname
		}
	}
}

// HeartbeatPublisher periodically publishes worker health and state to the panel.
type HeartbeatPublisher struct {
	workerID   string
	hostname   string
	url        string
	secret     string
	interval   time.Duration
	httpClient *http.Client
	logger     *slog.Logger
}

// NewHeartbeatPublisher constructs a new HeartbeatPublisher instance.
func NewHeartbeatPublisher(url, secret string, logger *slog.Logger, opts ...HeartbeatOption) *HeartbeatPublisher {
	hostname, err := os.Hostname()
	if err != nil || hostname == "" {
		hostname = "unknown"
	}

	workerID := fmt.Sprintf("worker-%s-%s", hostname, uuid.NewString()[:8])

	if logger == nil {
		logger = slog.Default()
	}

	hp := &HeartbeatPublisher{
		workerID:   workerID,
		hostname:   hostname,
		url:        url,
		secret:     secret,
		interval:   15 * time.Second,
		httpClient: &http.Client{Timeout: 5 * time.Second},
		logger:     logger,
	}

	for _, opt := range opts {
		opt(hp)
	}

	return hp
}

// WorkerID returns the unique worker ID.
func (hp *HeartbeatPublisher) WorkerID() string {
	return hp.workerID
}

// Hostname returns the worker hostname.
func (hp *HeartbeatPublisher) Hostname() string {
	return hp.hostname
}

// Send dispatches a single heartbeat request with the given status.
func (hp *HeartbeatPublisher) Send(ctx context.Context, status string) error {
	payload := HeartbeatPayload{
		WorkerID: hp.workerID,
		Hostname: hp.hostname,
		Status:   status,
	}

	body, err := json.Marshal(payload)
	if err != nil {
		return fmt.Errorf("failed to marshal heartbeat payload: %w", err)
	}

	req, err := http.NewRequestWithContext(ctx, http.MethodPost, hp.url, bytes.NewReader(body))
	if err != nil {
		return fmt.Errorf("failed to create heartbeat request: %w", err)
	}

	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("X-Internal-Secret", hp.secret)

	resp, err := hp.httpClient.Do(req)
	if err != nil {
		return fmt.Errorf("heartbeat HTTP request failed: %w", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		return fmt.Errorf("heartbeat HTTP request returned status %d", resp.StatusCode)
	}

	return nil
}

// Start runs periodic heartbeats until ctx is cancelled, then sends a final "stopped" heartbeat.
func (hp *HeartbeatPublisher) Start(ctx context.Context) {
	hp.logger.Info("starting heartbeat publisher",
		"worker_id", hp.workerID,
		"interval", hp.interval,
		"url", hp.url,
	)

	// Send initial active heartbeat immediately
	initCtx, initCancel := context.WithTimeout(context.Background(), 5*time.Second)
	if err := hp.Send(initCtx, "active"); err != nil {
		hp.logger.Warn("initial heartbeat failed", "error", err, "worker_id", hp.workerID)
	}
	initCancel()

	ticker := time.NewTicker(hp.interval)
	defer ticker.Stop()

	for {
		select {
		case <-ctx.Done():
			hp.logger.Info("context canceled, sending stopped heartbeat", "worker_id", hp.workerID)
			stopCtx, stopCancel := context.WithTimeout(context.Background(), 2*time.Second)
			if err := hp.Send(stopCtx, "stopped"); err != nil {
				hp.logger.Warn("failed to send stopped heartbeat", "error", err, "worker_id", hp.workerID)
			}
			stopCancel()
			return
		case <-ticker.C:
			tickCtx, tickCancel := context.WithTimeout(context.Background(), 5*time.Second)
			if err := hp.Send(tickCtx, "active"); err != nil {
				hp.logger.Warn("periodic heartbeat failed", "error", err, "worker_id", hp.workerID)
			}
			tickCancel()
		}
	}
}
