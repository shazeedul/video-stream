package callback_test

import (
	"context"
	"encoding/json"
	"errors"
	"net/http"
	"net/http/httptest"
	"sync/atomic"
	"testing"
	"time"

	"wiz-media-worker/internal/callback"
)

func intPtr(i int) *int {
	return &i
}

func TestCallbackClient_SendsInternalSecretHeader(t *testing.T) {
	var receivedHeader string
	var receivedPayload callback.Payload

	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		receivedHeader = r.Header.Get("X-Internal-Secret")
		if err := json.NewDecoder(r.Body).Decode(&receivedPayload); err != nil {
			http.Error(w, err.Error(), http.StatusBadRequest)
			return
		}
		w.WriteHeader(http.StatusOK)
		_, _ = w.Write([]byte(`{"success":true}`))
	}))
	defer server.Close()

	client := callback.NewClient(server.URL, "my-secret-key")
	err := client.SendStatus(context.Background(), callback.Payload{
		JobID:           "019324c4-7bf3-7521-9989-d419b4ef08f1",
		VideoID:         "019324c3-e836-7db0-bd1b-c40c83a5316e",
		Status:          callback.StatusProbing,
		DurationSeconds: intPtr(120),
		Metadata:        map[string]any{"format": "mp4"},
	})

	if err != nil {
		t.Fatalf("expected no error, got %v", err)
	}
	if receivedHeader != "my-secret-key" {
		t.Errorf("expected header 'my-secret-key', got '%s'", receivedHeader)
	}
	if receivedPayload.JobID != "019324c4-7bf3-7521-9989-d419b4ef08f1" {
		t.Errorf("unexpected job_id in payload: %s", receivedPayload.JobID)
	}
	if receivedPayload.Status != "probing" {
		t.Errorf("unexpected status in payload: %s", receivedPayload.Status)
	}
	if receivedPayload.DurationSeconds == nil || *receivedPayload.DurationSeconds != 120 {
		t.Errorf("expected duration_seconds 120, got %v", receivedPayload.DurationSeconds)
	}
}

func TestCallbackClient_SerializesZeroDuration(t *testing.T) {
	var rawBody map[string]any

	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if err := json.NewDecoder(r.Body).Decode(&rawBody); err != nil {
			http.Error(w, err.Error(), http.StatusBadRequest)
			return
		}
		w.WriteHeader(http.StatusOK)
		_, _ = w.Write([]byte(`{"success":true}`))
	}))
	defer server.Close()

	client := callback.NewClient(server.URL, "secret")
	err := client.SendStatus(context.Background(), callback.Payload{
		JobID:           "job-0",
		VideoID:         "vid-0",
		Status:          callback.StatusProbed,
		DurationSeconds: intPtr(0),
	})

	if err != nil {
		t.Fatalf("expected no error, got %v", err)
	}
	durVal, exists := rawBody["duration_seconds"]
	if !exists {
		t.Fatal("expected duration_seconds to be present in json when set to 0, but it was omitted")
	}
	durFloat, ok := durVal.(float64)
	if !ok || durFloat != 0 {
		t.Errorf("expected duration_seconds to be 0, got %v", durVal)
	}
}

func TestCallbackClient_RetriesOn5xxAndSucceeds(t *testing.T) {
	var attempts int32

	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		count := atomic.AddInt32(&attempts, 1)
		if count < 3 {
			w.WriteHeader(http.StatusServiceUnavailable)
			_, _ = w.Write([]byte(`{"error":"temporarily unavailable"}`))
			return
		}
		w.WriteHeader(http.StatusOK)
		_, _ = w.Write([]byte(`{"success":true}`))
	}))
	defer server.Close()

	client := callback.NewClient(
		server.URL,
		"secret",
		callback.WithMaxRetries(3),
		callback.WithBaseBackoff(1*time.Millisecond),
	)

	err := client.SendStatus(context.Background(), callback.Payload{
		JobID:   "job-1",
		VideoID: "vid-1",
		Status:  callback.StatusProbing,
	})

	if err != nil {
		t.Fatalf("expected request to succeed after retries, got %v", err)
	}
	if atomic.LoadInt32(&attempts) != 3 {
		t.Errorf("expected 3 attempts, got %d", atomic.LoadInt32(&attempts))
	}
}

func TestCallbackClient_ExhaustsRetriesOn5xx(t *testing.T) {
	var attempts int32

	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		atomic.AddInt32(&attempts, 1)
		w.WriteHeader(http.StatusInternalServerError)
		_, _ = w.Write([]byte(`{"error":"server error"}`))
	}))
	defer server.Close()

	client := callback.NewClient(
		server.URL,
		"secret",
		callback.WithMaxRetries(2),
		callback.WithBaseBackoff(1*time.Millisecond),
	)

	err := client.SendStatus(context.Background(), callback.Payload{
		JobID:   "job-1",
		VideoID: "vid-1",
		Status:  callback.StatusFailed,
		Error:   "something broke",
	})

	if err == nil {
		t.Fatal("expected error after exhausting retries, got nil")
	}
	// Initial attempt + 2 retries = 3 attempts total
	if atomic.LoadInt32(&attempts) != 3 {
		t.Errorf("expected 3 total attempts, got %d", atomic.LoadInt32(&attempts))
	}
}

func TestCallbackClient_DoesNotRetryOn4xx(t *testing.T) {
	var attempts int32

	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		atomic.AddInt32(&attempts, 1)
		w.WriteHeader(http.StatusUnprocessableEntity)
		_, _ = w.Write([]byte(`{"message":"validation failed"}`))
	}))
	defer server.Close()

	client := callback.NewClient(
		server.URL,
		"secret",
		callback.WithMaxRetries(3),
		callback.WithBaseBackoff(1*time.Millisecond),
	)

	err := client.SendStatus(context.Background(), callback.Payload{
		JobID:   "invalid",
		VideoID: "invalid",
		Status:  "unknown",
	})

	if err == nil {
		t.Fatal("expected error for 422, got nil")
	}
	if atomic.LoadInt32(&attempts) != 1 {
		t.Errorf("expected exactly 1 attempt on 4xx, got %d", atomic.LoadInt32(&attempts))
	}
}

func TestCallbackClient_HonorsContextCancellation(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusBadGateway)
	}))
	defer server.Close()

	ctx, cancel := context.WithCancel(context.Background())

	client := callback.NewClient(
		server.URL,
		"secret",
		callback.WithMaxRetries(5),
		callback.WithBaseBackoff(500*time.Millisecond),
	)

	// Cancel context after a small moment
	go func() {
		time.Sleep(50 * time.Millisecond)
		cancel()
	}()

	err := client.SendStatus(ctx, callback.Payload{
		JobID:   "job-1",
		VideoID: "vid-1",
		Status:  callback.StatusProbing,
	})

	if err == nil {
		t.Fatal("expected context cancellation error, got nil")
	}
}

func TestCallbackClient_ReturnsClientErrorFor4xx(t *testing.T) {
	for _, code := range []int{http.StatusNotFound, http.StatusUnprocessableEntity, http.StatusBadRequest} {
		server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			w.WriteHeader(code)
			_, _ = w.Write([]byte(`{"message":"error"}`))
		}))

		client := callback.NewClient(server.URL, "secret", callback.WithMaxRetries(0))
		err := client.SendStatus(context.Background(), callback.Payload{
			JobID:   "job-1",
			VideoID: "vid-1",
			Status:  callback.StatusProbing,
		})

		var clientErr *callback.ClientError
		if !errors.As(err, &clientErr) {
			t.Fatalf("expected *callback.ClientError for status %d, got %v", code, err)
		}
		if clientErr.StatusCode != code {
			t.Errorf("expected status %d, got %d", code, clientErr.StatusCode)
		}
		expectedNotFoundOrUnproc := code == http.StatusNotFound || code == http.StatusUnprocessableEntity
		if clientErr.IsNotFoundOrUnprocessable() != expectedNotFoundOrUnproc {
			t.Errorf("expected IsNotFoundOrUnprocessable() == %v for code %d", expectedNotFoundOrUnproc, code)
		}

		server.Close()
	}
}
