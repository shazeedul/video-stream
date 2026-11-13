package pipeline_test

import (
	"context"
	"errors"
	"fmt"
	"io"
	"log/slog"
	"os"
	"strings"
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
	mu           sync.Mutex
	payloads     []callback.Payload
	err          error
	statusErrMap map[string]error
}

func (m *mockCallbackClient) SendStatus(ctx context.Context, payload callback.Payload) error {
	m.mu.Lock()
	defer m.mu.Unlock()
	m.payloads = append(m.payloads, payload)
	if m.statusErrMap != nil {
		if err, ok := m.statusErrMap[payload.Status]; ok {
			return err
		}
	}
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
	if payloads[1].DurationSeconds == nil || *payloads[1].DurationSeconds != 120 {
		t.Errorf("expected duration 120, got %v", payloads[1].DurationSeconds)
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

func TestPipeline_Process_ProbeFailure_DecisivelyReportedReturnsNil(t *testing.T) {
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

	// Because failure callback succeeds, this unrecoverable file was decisively reported to Laravel
	// so Process returns nil (allowing consumer to ACK and not loop forever)
	err := p.Process(context.Background(), job)
	if err != nil {
		t.Fatalf("expected nil when corrupt file was decisively reported, got %v", err)
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

func TestPipeline_Process_ProbeFailure_CallbackFailsReturnsError(t *testing.T) {
	tempDir := t.TempDir()
	cfg := &config.Config{
		TempDir: tempDir,
	}

	storage := &mockStorage{
		downloadFn: func(ctx context.Context, bucket, objectKey, destPath string) error {
			return os.WriteFile(destPath, []byte("corrupt"), 0644)
		},
	}

	inspector := &mockInspector{
		inspectFn: func(ctx context.Context, filePath string) (*probe.ProbeResult, error) {
			return nil, errors.New("ffprobe failure")
		},
	}

	cb := &mockCallbackClient{
		statusErrMap: map[string]error{
			callback.StatusFailed: errors.New("network timeout reaching callback endpoint"),
		},
	}
	logger := slog.New(slog.NewTextHandler(io.Discard, nil))
	p := pipeline.NewPipeline(cfg, storage, inspector, cb, logger)

	job := pipeline.MediaJob{
		JobID:        "job-cb-fail",
		VideoID:      "vid-cb-fail",
		SourceBucket: "uploads",
		SourceKey:    "raw/bad.mp4",
	}

	// When notification fails, Process MUST return error so message can be requeued!
	err := p.Process(context.Background(), job)
	if err == nil {
		t.Fatal("expected error when callback notification fails, got nil")
	}
}

func TestPipeline_Process_ErrorTruncation(t *testing.T) {
	tempDir := t.TempDir()
	cfg := &config.Config{
		TempDir: tempDir,
	}

	longErrorMessage := strings.Repeat("A", 3000)
	storage := &mockStorage{
		downloadFn: func(ctx context.Context, bucket, objectKey, destPath string) error {
			return errors.New(longErrorMessage)
		},
	}

	cb := &mockCallbackClient{}
	logger := slog.New(slog.NewTextHandler(io.Discard, nil))
	p := pipeline.NewPipeline(cfg, storage, &mockInspector{}, cb, logger)

	job := pipeline.MediaJob{
		JobID:        "job-long-err",
		VideoID:      "vid-long-err",
		SourceBucket: "uploads",
		SourceKey:    "raw/corrupted.mp4",
	}

	_ = p.Process(context.Background(), job)

	payloads := cb.Payloads()
	if len(payloads) < 2 {
		t.Fatalf("expected at least 2 callbacks, got %d", len(payloads))
	}

	failedPayload := payloads[len(payloads)-1]
	if len(failedPayload.Error) > 2000 {
		t.Errorf("expected error string to be <= 2000 characters, got %d", len(failedPayload.Error))
	}
	if !strings.HasSuffix(failedPayload.Error, " ... [truncated]") {
		t.Errorf("expected truncated error to end with ' ... [truncated]', got '%s'", failedPayload.Error[len(failedPayload.Error)-20:])
	}
}

func TestPipeline_Process_ProbingCallback_NotFoundOrUnprocessable_DiscardsJobReturnsNil(t *testing.T) {
	for _, statusCode := range []int{404, 422} {
		tempDir := t.TempDir()
		cfg := &config.Config{TempDir: tempDir}

		storageCalled := false
		storage := &mockStorage{
			downloadFn: func(ctx context.Context, bucket, objectKey, destPath string) error {
				storageCalled = true
				return nil
			},
		}

		cb := &mockCallbackClient{
			statusErrMap: map[string]error{
				callback.StatusProbing: &callback.ClientError{
					StatusCode: statusCode,
					Message:    fmt.Sprintf("HTTP %d Client Error", statusCode),
				},
			},
		}
		logger := slog.New(slog.NewTextHandler(io.Discard, nil))
		p := pipeline.NewPipeline(cfg, storage, &mockInspector{}, cb, logger)

		job := pipeline.MediaJob{
			JobID:        "job-del",
			VideoID:      "vid-del",
			SourceBucket: "uploads",
			SourceKey:    "raw/video.mp4",
		}

		// When video is deleted/aborted in panel (404/422), Process must return nil so message is ACKed and discarded
		err := p.Process(context.Background(), job)
		if err != nil {
			t.Fatalf("expected nil on status code %d, got %v", statusCode, err)
		}

		if storageCalled {
			t.Errorf("expected storage download NOT to be attempted when probing returns %d", statusCode)
		}

		// Must not attempt to send failure callback
		for _, pl := range cb.Payloads() {
			if pl.Status == callback.StatusFailed {
				t.Errorf("expected status 'failed' NOT to be sent when video is missing (%d)", statusCode)
			}
		}
	}
}

func TestPipeline_Process_ProbedCallback_NotFoundOrUnprocessable_DiscardsJobReturnsNil(t *testing.T) {
	for _, statusCode := range []int{404, 422} {
		tempDir := t.TempDir()
		cfg := &config.Config{TempDir: tempDir}

		storage := &mockStorage{
			downloadFn: func(ctx context.Context, bucket, objectKey, destPath string) error {
				return os.WriteFile(destPath, []byte("valid-data"), 0644)
			},
		}

		cb := &mockCallbackClient{
			statusErrMap: map[string]error{
				callback.StatusProbed: &callback.ClientError{
					StatusCode: statusCode,
					Message:    fmt.Sprintf("HTTP %d Client Error", statusCode),
				},
			},
		}
		logger := slog.New(slog.NewTextHandler(io.Discard, nil))
		p := pipeline.NewPipeline(cfg, storage, &mockInspector{}, cb, logger)

		job := pipeline.MediaJob{
			JobID:        "job-del-probed",
			VideoID:      "vid-del-probed",
			SourceBucket: "uploads",
			SourceKey:    "raw/video.mp4",
		}

		err := p.Process(context.Background(), job)
		if err != nil {
			t.Fatalf("expected nil when probed returns %d, got %v", statusCode, err)
		}

		// Must not attempt failure callback
		for _, pl := range cb.Payloads() {
			if pl.Status == callback.StatusFailed {
				t.Errorf("expected status 'failed' NOT to be sent when probed returns %d", statusCode)
			}
		}
	}
}

func TestPipeline_Process_FailureCallback_NotFoundOrUnprocessable_DiscardsJobReturnsNil(t *testing.T) {
	for _, statusCode := range []int{404, 422} {
		tempDir := t.TempDir()
		cfg := &config.Config{TempDir: tempDir}

		storage := &mockStorage{
			downloadFn: func(ctx context.Context, bucket, objectKey, destPath string) error {
				return errors.New("storage download error")
			},
		}

		cb := &mockCallbackClient{
			statusErrMap: map[string]error{
				callback.StatusFailed: &callback.ClientError{
					StatusCode: statusCode,
					Message:    fmt.Sprintf("HTTP %d Client Error", statusCode),
				},
			},
		}
		logger := slog.New(slog.NewTextHandler(io.Discard, nil))
		p := pipeline.NewPipeline(cfg, storage, &mockInspector{}, cb, logger)

		job := pipeline.MediaJob{
			JobID:        "job-del-fail",
			VideoID:      "vid-del-fail",
			SourceBucket: "uploads",
			SourceKey:    "raw/video.mp4",
		}

		err := p.Process(context.Background(), job)
		if err != nil {
			t.Fatalf("expected nil when failure callback returns %d, got %v", statusCode, err)
		}
	}
}

func TestPipeline_Process_ErrorTruncation_UTF8Runes(t *testing.T) {
	tempDir := t.TempDir()
	cfg := &config.Config{TempDir: tempDir}

	// 2500 multi-byte UTF-8 runes (each rune is 3 bytes)
	multiByteErr := strings.Repeat("世界", 1250)
	storage := &mockStorage{
		downloadFn: func(ctx context.Context, bucket, objectKey, destPath string) error {
			return errors.New(multiByteErr)
		},
	}

	cb := &mockCallbackClient{}
	logger := slog.New(slog.NewTextHandler(io.Discard, nil))
	p := pipeline.NewPipeline(cfg, storage, &mockInspector{}, cb, logger)

	job := pipeline.MediaJob{
		JobID:        "job-utf8",
		VideoID:      "vid-utf8",
		SourceBucket: "uploads",
		SourceKey:    "raw/video.mp4",
	}

	_ = p.Process(context.Background(), job)

	payloads := cb.Payloads()
	if len(payloads) < 2 {
		t.Fatalf("expected at least 2 callbacks, got %d", len(payloads))
	}

	failedPayload := payloads[len(payloads)-1]
	runes := []rune(failedPayload.Error)
	expectedRuneCount := 1950 + len([]rune(" ... [truncated]"))
	if len(runes) != expectedRuneCount {
		t.Errorf("expected %d runes, got %d", expectedRuneCount, len(runes))
	}
	if !strings.HasSuffix(failedPayload.Error, " ... [truncated]") {
		t.Errorf("expected suffix ' ... [truncated]'")
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
