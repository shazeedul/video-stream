package transcoder

import (
	"bytes"
	"context"
	"errors"
	"fmt"
	"os"
	"os/exec"
	"strings"
)

// Transcoder executes FFmpeg transcoding jobs.
type Transcoder struct {
	binPath string
}

// NewTranscoder creates a new Transcoder instance with the given ffmpeg binary path.
// If binPath is empty, it defaults to "ffmpeg".
func NewTranscoder(binPath string) *Transcoder {
	if binPath == "" {
		binPath = "ffmpeg"
	}
	return &Transcoder{binPath: binPath}
}

// TranscodeHLS performs a single-pass multi-variant HLS encode of sourcePath into outputDir.
// It creates outputDir if it does not exist, builds the command arguments for the specified profiles,
// and runs the FFmpeg process while capturing stderr diagnostics on failure.
func (t *Transcoder) TranscodeHLS(ctx context.Context, sourcePath, outputDir string, profiles []Profile, opts ...TranscodeOption) error {
	if len(profiles) == 0 {
		return errors.New("no profiles provided for transcoding")
	}
	if sourcePath == "" {
		return errors.New("empty source path")
	}
	if outputDir == "" {
		return errors.New("empty output directory")
	}

	if err := os.MkdirAll(outputDir, 0755); err != nil {
		return fmt.Errorf("failed to create output directory: %w", err)
	}

	args := BuildFFmpegArgs(sourcePath, outputDir, profiles, opts...)

	bin := t.binPath
	if bin == "" {
		bin = "ffmpeg"
	}

	cmd := exec.CommandContext(ctx, bin, args...)
	var stderr bytes.Buffer
	cmd.Stderr = &stderr

	if err := cmd.Run(); err != nil {
		stderrStr := strings.TrimSpace(stderr.String())
		// If stderr is excessively long, preserve the last 4096 bytes for diagnostics
		if len(stderrStr) > 4096 {
			stderrStr = "... " + stderrStr[len(stderrStr)-4096:]
		}
		if stderrStr != "" {
			return fmt.Errorf("ffmpeg transcoding failed: %w (stderr: %s)", err, stderrStr)
		}
		return fmt.Errorf("ffmpeg transcoding failed: %w", err)
	}

	return nil
}
