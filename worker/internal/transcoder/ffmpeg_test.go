package transcoder_test

import (
	"context"
	"os"
	"path/filepath"
	"strings"
	"testing"
	"time"
	"wiz-media-worker/internal/transcoder"
)

func TestTranscodeHLS_ValidationErrors(t *testing.T) {
	tr := transcoder.NewTranscoder("ffmpeg")
	ctx := context.Background()

	validProfiles := []transcoder.Profile{
		{Name: "360p", Width: 640, Height: 360, VideoBitrate: "600k", MaxRate: "700k", BufSize: "1200k", AudioBitrate: "64k", ProfileLevel: "main"},
	}

	// Empty profiles
	if err := tr.TranscodeHLS(ctx, "/path/to/src.mp4", "/path/to/out", nil); err == nil {
		t.Error("expected error for nil profiles, got nil")
	}

	if err := tr.TranscodeHLS(ctx, "/path/to/src.mp4", "/path/to/out", []transcoder.Profile{}); err == nil {
		t.Error("expected error for empty profiles, got nil")
	}

	// Empty source path
	if err := tr.TranscodeHLS(ctx, "", "/path/to/out", validProfiles); err == nil {
		t.Error("expected error for empty sourcePath, got nil")
	}

	// Empty output dir
	if err := tr.TranscodeHLS(ctx, "/path/to/src.mp4", "", validProfiles); err == nil {
		t.Error("expected error for empty outputDir, got nil")
	}
}

func TestTranscodeHLS_InvalidBinary(t *testing.T) {
	tr := transcoder.NewTranscoder("non_existent_ffmpeg_binary_xyz_123")
	ctx := context.Background()
	profiles := []transcoder.Profile{
		{Name: "360p", Width: 640, Height: 360, VideoBitrate: "600k", MaxRate: "700k", BufSize: "1200k", AudioBitrate: "64k", ProfileLevel: "main"},
	}

	tempDir := t.TempDir()
	outDir := filepath.Join(tempDir, "hls_out")

	err := tr.TranscodeHLS(ctx, "/tmp/fake.mp4", outDir, profiles)
	if err == nil {
		t.Fatal("expected error for non-existent binary, got nil")
	}
}

func TestTranscodeHLS_CreatesOutputDir(t *testing.T) {
	tempDir := t.TempDir()
	outDir := filepath.Join(tempDir, "nested", "hls_out")

	// Verify outDir doesn't exist yet
	if _, err := os.Stat(outDir); !os.IsNotExist(err) {
		t.Fatalf("expected outDir to not exist initially")
	}

	// Use a mock binary (e.g., true / exit 0) so the command succeeds after creating the directory
	// On Linux/Alpine, "true" is available
	tr := transcoder.NewTranscoder("true")
	ctx := context.Background()
	profiles := []transcoder.Profile{
		{Name: "360p", Width: 640, Height: 360, VideoBitrate: "600k", MaxRate: "700k", BufSize: "1200k", AudioBitrate: "64k", ProfileLevel: "main"},
	}

	err := tr.TranscodeHLS(ctx, "/tmp/src.mp4", outDir, profiles)
	if err != nil {
		t.Fatalf("expected success with 'true' binary, got: %v", err)
	}

	// Check that directory was created
	info, err := os.Stat(outDir)
	if err != nil {
		t.Fatalf("expected outDir to be created: %v", err)
	}
	if !info.IsDir() {
		t.Errorf("expected outDir to be a directory")
	}
}

func TestTranscodeHLS_CapturesStderrDiagnostics(t *testing.T) {
	tempDir := t.TempDir()
	outDir := filepath.Join(tempDir, "hls_out")

	// Create a dummy script or invoke a command that writes to stderr and fails
	// In alpine / sh, we can use a small script
	scriptPath := filepath.Join(tempDir, "fake_fail.sh")
	scriptContent := "#!/bin/sh\necho 'Error: Invalid codec or corrupt frame' >&2\nexit 1\n"
	if err := os.WriteFile(scriptPath, []byte(scriptContent), 0755); err != nil {
		t.Fatalf("failed to write dummy script: %v", err)
	}

	tr := transcoder.NewTranscoder(scriptPath)
	ctx := context.Background()
	profiles := []transcoder.Profile{
		{Name: "360p", Width: 640, Height: 360, VideoBitrate: "600k", MaxRate: "700k", BufSize: "1200k", AudioBitrate: "64k", ProfileLevel: "main"},
	}

	err := tr.TranscodeHLS(ctx, "/tmp/src.mp4", outDir, profiles)
	if err == nil {
		t.Fatal("expected transcode error, got nil")
	}

	if !strings.Contains(err.Error(), "Invalid codec or corrupt frame") {
		t.Errorf("expected stderr diagnostics in error message, got: %v", err)
	}
}

func TestTranscodeHLS_ContextCancellation(t *testing.T) {
	tempDir := t.TempDir()
	outDir := filepath.Join(tempDir, "hls_out")

	// Use "sleep" as the binary so we can test timeout/cancellation
	tr := transcoder.NewTranscoder("sleep")
	ctx, cancel := context.WithTimeout(context.Background(), 50*time.Millisecond)
	defer cancel()

	profiles := []transcoder.Profile{
		{Name: "360p", Width: 640, Height: 360, VideoBitrate: "600k", MaxRate: "700k", BufSize: "1200k", AudioBitrate: "64k", ProfileLevel: "main"},
	}

	err := tr.TranscodeHLS(ctx, "/tmp/src.mp4", outDir, profiles)
	if err == nil {
		t.Fatal("expected error on cancelled context, got nil")
	}
}
