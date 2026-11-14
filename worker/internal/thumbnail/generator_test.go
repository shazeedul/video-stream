package thumbnail_test

import (
	"context"
	"os"
	"path/filepath"
	"strings"
	"testing"
	"time"
	"wiz-media-worker/internal/thumbnail"
)

func TestCalculateTimestamp_Clamping(t *testing.T) {
	// Standard video: 100 seconds -> 10% is 10s (capped at 5s)
	ts1 := thumbnail.CalculateTimestamp(100.0)
	if ts1 < 1.0 || ts1 > 5.0 {
		t.Errorf("expected timestamp between 1s and 5s for 100s video, got %f", ts1)
	}
	if ts1 != 5.0 {
		t.Errorf("expected 5.0s for 100s video, got %f", ts1)
	}

	// Standard video: 30 seconds -> 10% is 3s
	ts30 := thumbnail.CalculateTimestamp(30.0)
	if ts30 != 3.0 {
		t.Errorf("expected 3.0s for 30s video, got %f", ts30)
	}

	// Short video: 1.5 seconds -> clamped safely before EOF (1.0s)
	ts2 := thumbnail.CalculateTimestamp(1.5)
	if ts2 <= 0.0 || ts2 >= 1.5 {
		t.Errorf("timestamp must be strictly between 0 and 1.5s for 1.5s video, got %f", ts2)
	}
	if ts2 != 1.0 {
		t.Errorf("expected 1.0s for 1.5s video, got %f", ts2)
	}

	// Exactly 1.0 second -> 0.5s
	ts1Sec := thumbnail.CalculateTimestamp(1.0)
	if ts1Sec != 0.5 {
		t.Errorf("expected 0.5s for 1.0s video, got %f", ts1Sec)
	}

	// Very short video: 0.5 seconds -> 0.25s
	ts3 := thumbnail.CalculateTimestamp(0.5)
	if ts3 <= 0.0 || ts3 >= 0.5 {
		t.Errorf("timestamp must be strictly between 0 and 0.5s for 0.5s video, got %f", ts3)
	}
	if ts3 != 0.25 {
		t.Errorf("expected 0.25s for 0.5s video, got %f", ts3)
	}

	// Zero or negative duration
	tsZero := thumbnail.CalculateTimestamp(0.0)
	if tsZero != 0.0 {
		t.Errorf("expected 0.0s for 0s duration, got %f", tsZero)
	}

	tsNeg := thumbnail.CalculateTimestamp(-5.0)
	if tsNeg != 0.0 {
		t.Errorf("expected 0.0s for negative duration, got %f", tsNeg)
	}
}

func TestExtractFrame_MissingFileReturnsError(t *testing.T) {
	err := thumbnail.ExtractFrame(context.Background(), "/nonexistent/video.mp4", "/tmp/thumb.jpg", 10.0)
	if err == nil {
		t.Fatalf("expected error on nonexistent source, got nil")
	}
}

func TestExtractFrame_InvalidBinaryReturnsError(t *testing.T) {
	gen := thumbnail.NewGenerator("nonexistent-ffmpeg-bin-12345")
	err := gen.ExtractFrame(context.Background(), "some-video.mp4", "/tmp/thumb.jpg", 10.0)
	if err == nil {
		t.Fatalf("expected error with invalid binary path, got nil")
	}
}

func TestExtractFrame_EmptyPathsReturnError(t *testing.T) {
	ctx := context.Background()
	gen := thumbnail.NewGenerator("")

	if err := gen.ExtractFrame(ctx, "", "/tmp/thumb.jpg", 10.0); err == nil {
		t.Errorf("expected error for empty source path")
	}

	if err := gen.ExtractFrame(ctx, "/tmp/video.mp4", "", 10.0); err == nil {
		t.Errorf("expected error for empty destination path")
	}
}

func TestExtractFrame_CreatesDestDirectory(t *testing.T) {
	tempDir := t.TempDir()
	nestedDest := filepath.Join(tempDir, "nested", "poster", "thumb.jpg")

	// Use "true" binary which exits 0 on Linux
	gen := thumbnail.NewGenerator("true")
	ctx := context.Background()

	err := gen.ExtractFrame(ctx, "/tmp/source.mp4", nestedDest, 10.0)
	if err != nil {
		t.Fatalf("expected success with 'true' binary, got: %v", err)
	}

	parentDir := filepath.Dir(nestedDest)
	info, err := os.Stat(parentDir)
	if err != nil {
		t.Fatalf("expected destination parent dir to exist: %v", err)
	}
	if !info.IsDir() {
		t.Fatalf("expected %s to be a directory", parentDir)
	}
}

func TestExtractFrame_CapturesStderrDiagnostics(t *testing.T) {
	tempDir := t.TempDir()
	scriptPath := filepath.Join(tempDir, "fake_fail.sh")
	scriptContent := "#!/bin/sh\necho 'Error: Invalid poster frame timestamp or corrupt source' >&2\nexit 1\n"
	if err := os.WriteFile(scriptPath, []byte(scriptContent), 0755); err != nil {
		t.Fatalf("failed to write dummy script: %v", err)
	}

	gen := thumbnail.NewGenerator(scriptPath)
	destPath := filepath.Join(tempDir, "out.jpg")
	err := gen.ExtractFrame(context.Background(), "/tmp/source.mp4", destPath, 10.0)
	if err == nil {
		t.Fatal("expected error from failing script, got nil")
	}

	if !strings.Contains(err.Error(), "Invalid poster frame timestamp or corrupt source") {
		t.Errorf("expected stderr diagnostics in error message, got: %v", err)
	}
}

func TestExtractFrame_ContextCancellation(t *testing.T) {
	tempDir := t.TempDir()
	destPath := filepath.Join(tempDir, "thumb.jpg")

	// Use "sleep" as the binary so we can test timeout/cancellation
	gen := thumbnail.NewGenerator("sleep")
	ctx, cancel := context.WithTimeout(context.Background(), 50*time.Millisecond)
	defer cancel()

	err := gen.ExtractFrame(ctx, "/tmp/source.mp4", destPath, 10.0)
	if err == nil {
		t.Fatal("expected error on cancelled context, got nil")
	}
}
