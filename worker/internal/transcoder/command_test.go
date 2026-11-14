package transcoder_test

import (
	"strings"
	"testing"
	"wiz-media-worker/internal/transcoder"
)

func TestBuildFFmpegArgs_MultiVariantHLS(t *testing.T) {
	profiles := []transcoder.Profile{
		{Name: "720p", Width: 1280, Height: 720, VideoBitrate: "2500k", MaxRate: "2800k", BufSize: "5000k", AudioBitrate: "128k", ProfileLevel: "high"},
		{Name: "360p", Width: 640, Height: 360, VideoBitrate: "600k", MaxRate: "700k", BufSize: "1200k", AudioBitrate: "64k", ProfileLevel: "main"},
	}

	args := transcoder.BuildFFmpegArgs("/tmp/input.mp4", "/tmp/hls_out", profiles)
	cmdLine := strings.Join(args, " ")

	// Must include input
	if !strings.Contains(cmdLine, "-i /tmp/input.mp4") {
		t.Errorf("missing input: %s", cmdLine)
	}

	// Must include filter_complex with split
	if !strings.Contains(cmdLine, "-filter_complex") || !strings.Contains(cmdLine, "split=2") {
		t.Errorf("missing filter_complex with split: %s", cmdLine)
	}

	// Must include HLS muxer options
	if !strings.Contains(cmdLine, "-f hls") || !strings.Contains(cmdLine, "-hls_time 4") {
		t.Errorf("missing HLS muxer parameters: %s", cmdLine)
	}

	// Must include master playlist and var_stream_map
	if !strings.Contains(cmdLine, "-master_pl_name master.m3u8") {
		t.Errorf("missing master_pl_name: %s", cmdLine)
	}
	if !strings.Contains(cmdLine, "-var_stream_map v:0,a:0 v:1,a:1") {
		t.Errorf("missing or incorrect var_stream_map: %s", cmdLine)
	}

	// Must include segment filename pattern and playlist pattern
	if !strings.Contains(cmdLine, "-hls_segment_filename /tmp/hls_out/stream_%v_%03d.ts") {
		t.Errorf("missing or incorrect hls_segment_filename: %s", cmdLine)
	}
	if !strings.Contains(cmdLine, "/tmp/hls_out/stream_%v.m3u8") {
		t.Errorf("missing or incorrect playlist pattern: %s", cmdLine)
	}
}

func TestBuildFFmpegArgs_EmptyProfiles(t *testing.T) {
	args := transcoder.BuildFFmpegArgs("/tmp/input.mp4", "/tmp/hls_out", nil)
	if args != nil {
		t.Errorf("expected nil args for nil profiles, got %v", args)
	}

	args = transcoder.BuildFFmpegArgs("/tmp/input.mp4", "/tmp/hls_out", []transcoder.Profile{})
	if args != nil {
		t.Errorf("expected nil args for empty profiles, got %v", args)
	}
}

func TestBuildFFmpegArgs_SingleProfile(t *testing.T) {
	profiles := []transcoder.Profile{
		{Name: "1080p", Width: 1920, Height: 1080, VideoBitrate: "4500k", MaxRate: "4800k", BufSize: "9000k", AudioBitrate: "192k", ProfileLevel: "high"},
	}

	args := transcoder.BuildFFmpegArgs("/tmp/input.mp4", "/tmp/hls_out/", profiles)
	cmdLine := strings.Join(args, " ")

	if !strings.Contains(cmdLine, "split=1") {
		t.Errorf("expected split=1 for single profile, got: %s", cmdLine)
	}
	if !strings.Contains(cmdLine, "-var_stream_map v:0,a:0") {
		t.Errorf("expected var_stream_map v:0,a:0, got: %s", cmdLine)
	}
	// Verify trailing slash handled cleanly
	if !strings.Contains(cmdLine, "-hls_segment_filename /tmp/hls_out/stream_%v_%03d.ts") {
		t.Errorf("unexpected double slash in segment filename: %s", cmdLine)
	}
}

func TestBuildFFmpegArgs_PerStreamEncodingFlags(t *testing.T) {
	profiles := []transcoder.Profile{
		{Name: "720p", Width: 1280, Height: 720, VideoBitrate: "2500k", MaxRate: "2800k", BufSize: "5000k", AudioBitrate: "128k", ProfileLevel: "high"},
		{Name: "360p", Width: 640, Height: 360, VideoBitrate: "600k", MaxRate: "700k", BufSize: "1200k", AudioBitrate: "64k", ProfileLevel: "main"},
	}

	args := transcoder.BuildFFmpegArgs("/tmp/input.mp4", "/tmp/hls_out", profiles)
	cmdLine := strings.Join(args, " ")

	expectedFlags := []string{
		"-map [v0out] -c:v:0 libx264 -preset veryfast -b:v:0 2500k -maxrate:v:0 2800k -bufsize:v:0 5000k -profile:v:0 high -g 48 -keyint_min 48 -sc_threshold 0",
		"-map a:0? -c:a:0 aac -b:a:0 128k -ac 2",
		"-map [v1out] -c:v:1 libx264 -preset veryfast -b:v:1 600k -maxrate:v:1 700k -bufsize:v:1 1200k -profile:v:1 main -g 48 -keyint_min 48 -sc_threshold 0",
		"-map a:0? -c:a:1 aac -b:a:1 64k -ac 2",
	}

	for _, flag := range expectedFlags {
		if !strings.Contains(cmdLine, flag) {
			t.Errorf("missing expected flag segment %q in command line: %s", flag, cmdLine)
		}
	}
}
