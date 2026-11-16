package observability_test

import (
	"context"
	"encoding/json"
	"io"
	"net/http"
	"net/http/httptest"
	"strings"
	"sync"
	"testing"
	"time"

	"wiz-media-worker/internal/observability"
)

func TestHeartbeatPublisher_WorkerIDGeneration(t *testing.T) {
	pub := observability.NewHeartbeatPublisher("http://localhost:8000/heartbeat", "secret", nil)
	workerID := pub.WorkerID()

	if !strings.HasPrefix(workerID, "worker-") {
		t.Fatalf("expected worker ID to start with 'worker-', got: %s", workerID)
	}

	if pub.Hostname() == "" {
		t.Fatalf("expected non-empty hostname")
	}

	if !strings.Contains(workerID, pub.Hostname()) {
		t.Fatalf("expected worker ID %s to contain hostname %s", workerID, pub.Hostname())
	}
}

func TestHeartbeatPublisher_Send(t *testing.T) {
	var receivedRequests []observability.HeartbeatPayload
	var receivedHeaders []http.Header
	var mu sync.Mutex

	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		mu.Lock()
		defer mu.Unlock()

		if r.Method != http.MethodPost {
			t.Errorf("expected POST method, got %s", r.Method)
		}
		if r.URL.Path != "/api/v1/internal/worker/heartbeat" {
			t.Errorf("expected path /api/v1/internal/worker/heartbeat, got %s", r.URL.Path)
		}

		receivedHeaders = append(receivedHeaders, r.Header.Clone())

		body, err := io.ReadAll(r.Body)
		if err != nil {
			t.Errorf("failed to read request body: %v", err)
		}

		var payload observability.HeartbeatPayload
		if err := json.Unmarshal(body, &payload); err != nil {
			t.Errorf("failed to unmarshal request body: %v", err)
		}
		receivedRequests = append(receivedRequests, payload)

		w.WriteHeader(http.StatusOK)
		_, _ = w.Write([]byte(`{"status":"recorded"}`))
	}))
	defer server.Close()

	secret := "test-secret-key-xyz"
	pub := observability.NewHeartbeatPublisher(
		server.URL+"/api/v1/internal/worker/heartbeat",
		secret,
		nil,
		observability.WithWorkerID("worker-custom-001"),
		observability.WithHostname("host-custom"),
		observability.WithHTTPClient(server.Client()),
	)

	ctx := context.Background()
	if err := pub.Send(ctx, "active"); err != nil {
		t.Fatalf("unexpected error sending active heartbeat: %v", err)
	}

	if err := pub.Send(ctx, "stopped"); err != nil {
		t.Fatalf("unexpected error sending stopped heartbeat: %v", err)
	}

	mu.Lock()
	defer mu.Unlock()

	if len(receivedRequests) != 2 {
		t.Fatalf("expected 2 received requests, got %d", len(receivedRequests))
	}

	// Verify headers
	for i, header := range receivedHeaders {
		if header.Get("X-Internal-Secret") != secret {
			t.Errorf("request %d: expected X-Internal-Secret %s, got %s", i, secret, header.Get("X-Internal-Secret"))
		}
		if header.Get("Content-Type") != "application/json" {
			t.Errorf("request %d: expected Content-Type application/json, got %s", i, header.Get("Content-Type"))
		}
	}

	// Verify active payload
	if receivedRequests[0].WorkerID != "worker-custom-001" {
		t.Errorf("expected worker_id 'worker-custom-001', got: %s", receivedRequests[0].WorkerID)
	}
	if receivedRequests[0].Hostname != "host-custom" {
		t.Errorf("expected hostname 'host-custom', got: %s", receivedRequests[0].Hostname)
	}
	if receivedRequests[0].Status != "active" {
		t.Errorf("expected status 'active', got: %s", receivedRequests[0].Status)
	}

	// Verify stopped payload
	if receivedRequests[1].Status != "stopped" {
		t.Errorf("expected status 'stopped', got: %s", receivedRequests[1].Status)
	}
}

func TestHeartbeatPublisher_StartAndStopLifecycle(t *testing.T) {
	var receivedStatuses []string
	var mu sync.Mutex

	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		mu.Lock()
		defer mu.Unlock()

		var payload observability.HeartbeatPayload
		_ = json.NewDecoder(r.Body).Decode(&payload)
		receivedStatuses = append(receivedStatuses, payload.Status)

		w.WriteHeader(http.StatusOK)
		_, _ = w.Write([]byte(`{"status":"recorded"}`))
	}))
	defer server.Close()

	pub := observability.NewHeartbeatPublisher(
		server.URL,
		"secret",
		nil,
		observability.WithInterval(25*time.Millisecond),
		observability.WithHTTPClient(server.Client()),
	)

	ctx, cancel := context.WithCancel(context.Background())

	done := make(chan struct{})
	go func() {
		pub.Start(ctx)
		close(done)
	}()

	// Allow initial and at least one ticker heartbeat
	time.Sleep(70 * time.Millisecond)

	// Cancel context to trigger shutdown
	cancel()

	select {
	case <-done:
		// Succeeded
	case <-time.After(1 * time.Second):
		t.Fatal("heartbeat publisher did not exit after context cancellation")
	}

	mu.Lock()
	defer mu.Unlock()

	if len(receivedStatuses) < 2 {
		t.Fatalf("expected at least 2 statuses (active and stopped), got %d: %v", len(receivedStatuses), receivedStatuses)
	}

	if receivedStatuses[0] != "active" {
		t.Errorf("expected first status to be 'active', got: %s", receivedStatuses[0])
	}

	lastStatus := receivedStatuses[len(receivedStatuses)-1]
	if lastStatus != "stopped" {
		t.Errorf("expected final status to be 'stopped', got: %s", lastStatus)
	}
}

func TestHeartbeatPublisher_HTTPError(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusInternalServerError)
	}))
	defer server.Close()

	pub := observability.NewHeartbeatPublisher(
		server.URL,
		"secret",
		nil,
		observability.WithHTTPClient(server.Client()),
	)

	err := pub.Send(context.Background(), "active")
	if err == nil {
		t.Fatal("expected error on 500 response, got nil")
	}
}
