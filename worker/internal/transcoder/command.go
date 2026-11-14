package transcoder

import (
	"fmt"
	"strings"
)

// BuildFFmpegArgs builds dynamic FFmpeg command arguments for single-pass multi-variant HLS transcoding.
func BuildFFmpegArgs(sourcePath, outputDir string, profiles []Profile) []string {
	if len(profiles) == 0 {
		return nil
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
		args = append(args,
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
			"-map", "a:0?",
			fmt.Sprintf("-c:a:%d", i), "aac",
			fmt.Sprintf("-b:a:%d", i), p.AudioBitrate,
			"-ac", "2",
		)
		varStreamMaps = append(varStreamMaps, fmt.Sprintf("v:%d,a:%d", i, i))
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
