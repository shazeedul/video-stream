package consumer_test

import (
	"context"
	"errors"
	"io"
	"log/slog"
	"testing"

	"wiz-media-worker/internal/config"
	"wiz-media-worker/internal/consumer"
	"wiz-media-worker/internal/pipeline"
)

type mockMessage struct {
	body        []byte
	ackCount    int
	nackCount   int
	lastRequeue bool
}

func (m *mockMessage) Body() []byte {
	return m.body
}

func (m *mockMessage) Ack() error {
	m.ackCount++
	return nil
}

func (m *mockMessage) Nack(requeue bool) error {
	m.nackCount++
	m.lastRequeue = requeue
	return nil
}

type mockPipelineRunner struct {
	processedJobs []pipeline.MediaJob
	err           error
}

func (m *mockPipelineRunner) Process(ctx context.Context, job pipeline.MediaJob) error {
	m.processedJobs = append(m.processedJobs, job)
	return m.err
}

func TestConsumer_HandleMessage_Success(t *testing.T) {
	cfg := &config.Config{
		RabbitMQQueue: "media.jobs",
		Concurrency:   2,
	}
	runner := &mockPipelineRunner{}
	logger := slog.New(slog.NewTextHandler(io.Discard, nil))
	c := consumer.NewConsumer(cfg, runner, logger)

	jsonPayload := []byte(`{
		"version": "1.0",
		"job_id": "019324c4-7bf3-7521-9989-d419b4ef08f1",
		"tenant_id": "019324c3-tenant-uuid",
		"video_id": "019324c3-e836-7db0-bd1b-c40c83a5316e",
		"source_bucket": "media-storage",
		"source_key": "raw/input.mp4",
		"created_at": "2026-11-13T10:00:00Z"
	}`)

	msg := &mockMessage{body: jsonPayload}
	err := c.HandleMessage(context.Background(), msg)

	if err != nil {
		t.Fatalf("expected HandleMessage to succeed, got %v", err)
	}

	if len(runner.processedJobs) != 1 {
		t.Fatalf("expected 1 processed job, got %d", len(runner.processedJobs))
	}
	if runner.processedJobs[0].JobID != "019324c4-7bf3-7521-9989-d419b4ef08f1" {
		t.Errorf("unexpected job_id: %s", runner.processedJobs[0].JobID)
	}

	if msg.ackCount != 1 {
		t.Errorf("expected 1 ack, got %d", msg.ackCount)
	}
	if msg.nackCount != 0 {
		t.Errorf("expected 0 nack, got %d", msg.nackCount)
	}
}

func TestConsumer_HandleMessage_MalformedJSON_NacksWithoutRequeue(t *testing.T) {
	cfg := &config.Config{Concurrency: 2}
	runner := &mockPipelineRunner{}
	logger := slog.New(slog.NewTextHandler(io.Discard, nil))
	c := consumer.NewConsumer(cfg, runner, logger)

	badJSON := []byte(`{not valid json}`)
	msg := &mockMessage{body: badJSON}

	err := c.HandleMessage(context.Background(), msg)
	if err == nil {
		t.Fatal("expected error on malformed JSON, got nil")
	}

	if len(runner.processedJobs) != 0 {
		t.Errorf("expected pipeline not to be called, but got %d calls", len(runner.processedJobs))
	}
	if msg.ackCount != 0 {
		t.Errorf("expected 0 ack, got %d", msg.ackCount)
	}
	if msg.nackCount != 1 {
		t.Fatalf("expected 1 nack, got %d", msg.nackCount)
	}
	if msg.lastRequeue != false {
		t.Errorf("expected nack requeue = false for DLQ routing, got true")
	}
}

func TestConsumer_HandleMessage_InvalidSchema_NacksWithoutRequeue(t *testing.T) {
	cfg := &config.Config{Concurrency: 2}
	runner := &mockPipelineRunner{}
	logger := slog.New(slog.NewTextHandler(io.Discard, nil))
	c := consumer.NewConsumer(cfg, runner, logger)

	// Missing video_id, source_bucket, source_key
	invalidSchema := []byte(`{
		"version": "1.0",
		"job_id": "019324c4-7bf3-7521-9989-d419b4ef08f1"
	}`)
	msg := &mockMessage{body: invalidSchema}

	err := c.HandleMessage(context.Background(), msg)
	if err == nil {
		t.Fatal("expected error on invalid schema, got nil")
	}

	if len(runner.processedJobs) != 0 {
		t.Errorf("expected pipeline not to be called, got %d calls", len(runner.processedJobs))
	}
	if msg.ackCount != 0 {
		t.Errorf("expected 0 ack, got %d", msg.ackCount)
	}
	if msg.nackCount != 1 {
		t.Fatalf("expected 1 nack, got %d", msg.nackCount)
	}
	if msg.lastRequeue != false {
		t.Errorf("expected nack requeue = false for DLQ routing, got true")
	}
}

func TestConsumer_HandleMessage_PipelineError_StillAcksOnCompletion(t *testing.T) {
	cfg := &config.Config{Concurrency: 2}
	runner := &mockPipelineRunner{
		err: errors.New("ffprobe failed and failure callback dispatched"),
	}
	logger := slog.New(slog.NewTextHandler(io.Discard, nil))
	c := consumer.NewConsumer(cfg, runner, logger)

	payload := []byte(`{
		"version": "1.0",
		"job_id": "job-123",
		"video_id": "video-123",
		"source_bucket": "bucket",
		"source_key": "corrupted.mp4"
	}`)
	msg := &mockMessage{body: payload}

	err := c.HandleMessage(context.Background(), msg)
	if err != nil {
		t.Fatalf("expected nil error since message was handled and acked, got %v", err)
	}

	if len(runner.processedJobs) != 1 {
		t.Fatalf("expected 1 pipeline run, got %d", len(runner.processedJobs))
	}
	if msg.ackCount != 1 {
		t.Errorf("expected 1 ack upon pipeline completion, got %d", msg.ackCount)
	}
	if msg.nackCount != 0 {
		t.Errorf("expected 0 nack, got %d", msg.nackCount)
	}
}
