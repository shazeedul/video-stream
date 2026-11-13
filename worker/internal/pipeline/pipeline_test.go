package pipeline_test

import (
	"context"
	"errors"
	"io"
	"log/slog"
	"os"
	"sync"
	"testing"

	"wiz-media-worker/internal/callback"
	"wiz-media-worker/internal/config"
	"wiz-media-worker/internal/pipeline"
	"wiz-media-worker/internal/probe"
)

type mockStorage struct {
	downloadFn func(ctx context.Context, bucket, objectKey, destPath string) error
}

func (m *mockStorage) DownloadSourceFile(ctx context.Context, bucket, objectKey, destPath string) error {
	if m.downloadFn != nil {
		return m.downloadFn(ctx, bucket, objectKey, destPath)
	}
	return nil
}

type mockInspector struct {
	inspectFn func(ctx context.Context, filePath string) (*probe.ProbeResult, error)
}

func (m *mockInspector) Inspect(ctx context.Context, filePath string) (*probe.ProbeResult, error) {
	if m.inspectFn != nil {
		return m.inspectFn(ctx, filePath)
	}
	return &probe.ProbeResult{DurationSeconds: 10}, nil
}

type mockCallbackClient struct {
	mu       sync.Mutex
	payloads []callback.Payload
	err      error
}

func (m *mockCallbackClient) SendStatus(ctx context.Context, payload callback.Payload) error {
	m.mu.Lock()
	defer m.mu.Unlock()
	m.payloads = append(m.payloads, payload)
	return m.err
}

func (m *mockCallbackClient) Payloads() []callback.Payload {
	m.mu.Lock()
	defer m.mu.Unlock()
	cp := make([]callback.Payload, len(m.payloads))
	copy(cp, m.payloads)
	return cp
}

func TestPipeline_Process_Success(t *testing.T) {
	tempDir := t.TempDir()
	cfg := &config.Config{
		TempDir: tempDir,
	}

	var downloadedPath string
	storage := &mockStorage{
		downloadFn: func(ctx context.Context, bucket, objectKey, destPath string) error {
			downloadedPath = destPath
			return os.WriteFile(destPath, []byte("fake-mp4-data"), 0644)
		},
	}

	inspector := &mockInspector{
		inspectFn: func(ctx context.Context, filePath string) (*probe.ProbeResult, error) {
			if _, err := os.Stat(filePath); err != nil {
				return nil, err
			}
			return &probe.ProbeResult{
				DurationSeconds: 120,
				Format:          "mp4",
				SizeBytes:       10240,
				Metadata: map[string]any{
					"duration": 120,
					"format":   "mp4",
				},
			}, nil
		},
	}

	cb := &mockCallbackClient{}
	logger := slog.New(slog.NewTextHandler(io.Discard, nil))
	p := pipeline.NewPipeline(cfg, storage, inspector, cb, logger)

	job := pipeline.MediaJob{
		Version:      "1.0",
		JobID:        "job-abc",
		TenantID:     "tenant-1",
		VideoID:      "video-xyz",
		SourceBucket: "uploads",
		SourceKey:    "raw/video.mp4",
	}

	err := p.Process(context.Background(), job)
	if err != nil {
		t.Fatalf("expected no error, got %v", err)
	}

	// Verify temp file was deleted
	if _, err := os.Stat(downloadedPath); !os.IsNotExist(err) {
		t.Errorf("expected temp file %s to be deleted, but it exists", downloadedPath)
	}

	// Verify callbacks sent
	payloads := cb.Payloads()
	if len(payloads) != 2 {
		t.Fatalf("expected 2 callbacks, got %d", len(payloads))
	}

	if payloads[0].Status != callback.StatusProbing {
		t.Errorf("expected first callback status 'probing', got '%s'", payloads[0].Status)
	}

	if payloads[1].Status != callback.StatusProbed {
		t.Errorf("expected second callback status 'probed', got '%s'", payloads[1].Status)
	}
	if payloads[1].DurationSeconds != 120 {
		t.Errorf("expected duration 120, got %d", payloads[1].DurationSeconds)
	}
	if payloads[1].Metadata["format"] != "mp4" {
		t.Errorf("expected metadata format mp4, got %v", payloads[1].Metadata["format"])
	}
}

func TestPipeline_Process_DownloadFailure(t *testing.T) {
	tempDir := t.TempDir()
	cfg := &config.Config{
		TempDir: tempDir,
	}

	storage := &mockStorage{
		downloadFn: func(ctx context.Context, bucket, objectKey, destPath string) error {
			return errors.New("minio connection timeout")
		},
	}

	inspectorCalled := false
	inspector := &mockInspector{
		inspectFn: func(ctx context.Context, filePath string) (*probe.ProbeResult, error) {
			inspectorCalled = true
			return nil, nil
		},
	}

	cb := &mockCallbackClient{}
	logger := slog.New(slog.NewTextHandler(io.Discard, nil))
	p := pipeline.NewPipeline(cfg, storage, inspector, cb, logger)

	job := pipeline.MediaJob{
		JobID:        "job-fail-dl",
		VideoID:      "video-fail-dl",
		SourceBucket: "uploads",
		SourceKey:    "raw/corrupted.mp4",
	}

	err := p.Process(context.Background(), job)
	if err == nil {
		t.Fatal("expected process to return error on download failure, got nil")
	}

	if inspectorCalled {
		t.Error("expected inspector NOT to be called after download failure")
	}

	payloads := cb.Payloads()
	if len(payloads) != 2 {
		t.Fatalf("expected 2 callbacks (probing, failed), got %d", len(payloads))
	}

	if payloads[0].Status != callback.StatusProbing {
		t.Errorf("expected status 'probing', got '%s'", payloads[0].Status)
	}
	if payloads[1].Status != callback.StatusFailed {
		t.Errorf("expected status 'failed', got '%s'", payloads[1].Status)
	}
	if payloads[1].Error == "" {
		t.Error("expected failure callback to include error string")
	}
}

func TestPipeline_Process_ProbeFailure(t *testing.T) {
	tempDir := t.TempDir()
	cfg := &config.Config{
		TempDir: tempDir,
	}

	var downloadedPath string
	storage := &mockStorage{
		downloadFn: func(ctx context.Context, bucket, objectKey, destPath string) error {
			downloadedPath = destPath
			return os.WriteFile(destPath, []byte("not-a-valid-video"), 0644)
		},
	}

	inspector := &mockInspector{
		inspectFn: func(ctx context.Context, filePath string) (*probe.ProbeResult, error) {
			return nil, errors.New("ffprobe invalid data found")
		},
	}

	cb := &mockCallbackClient{}
	logger := slog.New(slog.NewTextHandler(io.Discard, nil))
	p := pipeline.NewPipeline(cfg, storage, inspector, cb, logger)

	job := pipeline.MediaJob{
		JobID:        "job-fail-probe",
		VideoID:      "video-fail-probe",
		SourceBucket: "uploads",
		SourceKey:    "raw/bad.mp4",
	}

	err := p.Process(context.Background(), job)
	if err == nil {
		t.Fatal("expected process to return error on probe failure, got nil")
	}

	// Verify temp file was cleaned up despite failure
	if _, err := os.Stat(downloadedPath); !os.IsNotExist(err) {
		t.Errorf("expected temp file %s to be cleaned up on failure", downloadedPath)
	}

	payloads := cb.Payloads()
	if len(payloads) != 2 {
		t.Fatalf("expected 2 callbacks (probing, failed), got %d", len(payloads))
	}
	if payloads[1].Status != callback.StatusFailed {
		t.Errorf("expected status 'failed', got '%s'", payloads[1].Status)
	}
}

func TestMediaJob_Validate(t *testing.T) {
	tests := []struct {
		name    string
		job     pipeline.MediaJob
		wantErr bool
	}{
		{
			name: "valid job",
			job: pipeline.MediaJob{
				JobID:        "j-1",
				VideoID:      "v-1",
				SourceBucket: "b-1",
				SourceKey:    "k-1",
			},
			wantErr: false,
		},
		{
			name: "missing job_id",
			job: pipeline.MediaJob{
				VideoID:      "v-1",
				SourceBucket: "b-1",
				SourceKey:    "k-1",
			},
			wantErr: true,
		},
		{
			name: "missing video_id",
			job: pipeline.MediaJob{
				JobID:        "j-1",
				SourceBucket: "b-1",
				SourceKey:    "k-1",
			},
			wantErr: true,
		},
		{
			name: "missing source_bucket",
			job: pipeline.MediaJob{
				JobID:     "j-1",
				VideoID:   "v-1",
				SourceKey: "k-1",
			},
			wantErr: true,
		},
		{
			name: "missing source_key",
			job: pipeline.MediaJob{
				JobID:        "j-1",
				VideoID:      "v-1",
				SourceBucket: "b-1",
			},
			wantErr: true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			err := tt.job.Validate()
			if (err != nil) != tt.wantErr {
				t.Errorf("Validate() error = %v, wantErr = %v", err, tt.wantErr)
			}
		})
	}
}
