package transcoder

import (
	"fmt"
	"strings"
)

// TranscodeConfig holds options for transcoding.
type TranscodeConfig struct {
	HasAudio bool
}

// TranscodeOption configures transcode execution.
type TranscodeOption func(*TranscodeConfig)

// WithAudio specifies whether the input video has an audio stream.
// Defaults to true if not specified.
func WithAudio(hasAudio bool) TranscodeOption {
	return func(c *TranscodeConfig) {
		c.HasAudio = hasAudio
	}
}

// BuildFFmpegArgs builds dynamic FFmpeg command arguments for single-pass multi-variant HLS transcoding.
func BuildFFmpegArgs(sourcePath, outputDir string, profiles []Profile, opts ...TranscodeOption) []string {
	if len(profiles) == 0 {
		return nil
	}

	cfg := TranscodeConfig{
		HasAudio: true,
	}
	for _, opt := range opts {
		opt(&cfg)
	}

	cleanOutputDir := strings.TrimRight(outputDir, "/\\")
	if cleanOutputDir == "" {
		cleanOutputDir = "."
	}

	// Build filter_complex: [0:v]split=N[v0][v1]...;[v0]scale=W0:H0[v0out];[v1]scale=W1:H1[v1out]...
	var splitOutputs strings.Builder
	for i := range profiles {
		splitOutputs.WriteString(fmt.Sprintf("[v%d]", i))
	}

	filterParts := make([]string, 0, len(profiles)+1)
	filterParts = append(filterParts, fmt.Sprintf("[0:v]split=%d%s", len(profiles), splitOutputs.String()))
	for i, p := range profiles {
		filterParts = append(filterParts, p.FilterString(i))
	}
	filterComplex := strings.Join(filterParts, ";")

	args := []string{
		"-y",
		"-i", sourcePath,
		"-filter_complex", filterComplex,
	}

	varStreamMaps := make([]string, 0, len(profiles))

	for i, p := range profiles {
		streamArgs := []string{
			"-map", fmt.Sprintf("[v%dout]", i),
			fmt.Sprintf("-c:v:%d", i), "libx264",
			"-preset", "veryfast",
			fmt.Sprintf("-b:v:%d", i), p.VideoBitrate,
			fmt.Sprintf("-maxrate:v:%d", i), p.MaxRate,
			fmt.Sprintf("-bufsize:v:%d", i), p.BufSize,
			fmt.Sprintf("-profile:v:%d", i), p.ProfileLevel,
			"-g", "48",
			"-keyint_min", "48",
			"-sc_threshold", "0",
		}
		if cfg.HasAudio {
			streamArgs = append(streamArgs,
				"-map", "a:0?",
				fmt.Sprintf("-c:a:%d", i), "aac",
				fmt.Sprintf("-b:a:%d", i), p.AudioBitrate,
				"-ac", "2",
			)
			varStreamMaps = append(varStreamMaps, fmt.Sprintf("v:%d,a:%d", i, i))
		} else {
			varStreamMaps = append(varStreamMaps, fmt.Sprintf("v:%d", i))
		}
		args = append(args, streamArgs...)
	}

	args = append(args,
		"-f", "hls",
		"-hls_time", "4",
		"-hls_playlist_type", "vod",
		"-hls_flags", "independent_segments",
		"-master_pl_name", "master.m3u8",
		"-var_stream_map", strings.Join(varStreamMaps, " "),
		"-hls_segment_filename", fmt.Sprintf("%s/stream_%%v_%%03d.ts", cleanOutputDir),
		fmt.Sprintf("%s/stream_%%v.m3u8", cleanOutputDir),
	)

	return args
}
