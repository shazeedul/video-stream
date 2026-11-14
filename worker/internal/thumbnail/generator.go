package thumbnail

import (
	"bytes"
	"context"
	"errors"
	"fmt"
	"math"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
)

// Generator handles video poster frame extraction using FFmpeg.
type Generator struct {
	binPath string
}

// NewGenerator creates a new thumbnail Generator.
// If binPath is empty, it defaults to "ffmpeg".
func NewGenerator(binPath string) *Generator {
	if binPath == "" {
		binPath = "ffmpeg"
	}
	return &Generator{binPath: binPath}
}

// CalculateTimestamp returns a target timestamp in seconds for poster frame extraction.
// Clamping logic:
// - If durationSeconds <= 0, return 0.0
// - If durationSeconds <= 1.0, return durationSeconds * 0.5
// - Otherwise, return math.Min(5.0, math.Max(1.0, durationSeconds * 0.1))
func CalculateTimestamp(durationSeconds float64) float64 {
	if durationSeconds <= 0 {
		return 0.0
	}
	if durationSeconds <= 1.0 {
		return durationSeconds * 0.5
	}
	return math.Min(5.0, math.Max(1.0, durationSeconds*0.1))
}

// ExtractFrame extracts a single poster frame from sourcePath at a calculated timestamp
// and saves it as a JPEG at destPath using the default FFmpeg binary.
func ExtractFrame(ctx context.Context, sourcePath, destPath string, durationSeconds float64) error {
	return NewGenerator("").ExtractFrame(ctx, sourcePath, destPath, durationSeconds)
}

// ExtractFrame extracts a single poster frame from sourcePath at a calculated timestamp
// and saves it as a JPEG at destPath.
func (g *Generator) ExtractFrame(ctx context.Context, sourcePath, destPath string, durationSeconds float64) error {
	if sourcePath == "" {
		return errors.New("empty source path")
	}
	if destPath == "" {
		return errors.New("empty destination path")
	}

	dir := filepath.Dir(destPath)
	if dir != "" && dir != "." {
		if err := os.MkdirAll(dir, 0755); err != nil {
			return fmt.Errorf("failed to create destination directory: %w", err)
		}
	}

	ts := CalculateTimestamp(durationSeconds)
	tsStr := fmt.Sprintf("%.3f", ts)

	bin := g.binPath
	if bin == "" {
		bin = "ffmpeg"
	}

	args := []string{
		"-ss", tsStr,
		"-i", sourcePath,
		"-vframes", "1",
		"-q:v", "2",
		"-y",
		destPath,
	}

	cmd := exec.CommandContext(ctx, bin, args...)
	var stderr bytes.Buffer
	cmd.Stderr = &stderr

	if err := cmd.Run(); err != nil {
		stderrStr := strings.TrimSpace(stderr.String())
		if len(stderrStr) > 4096 {
			stderrStr = "... " + stderrStr[len(stderrStr)-4096:]
		}
		if stderrStr != "" {
			return fmt.Errorf("ffmpeg thumbnail extraction failed: %w (stderr: %s)", err, stderrStr)
		}
		return fmt.Errorf("ffmpeg thumbnail extraction failed: %w", err)
	}

	return nil
}
