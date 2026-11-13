package probe_test

import (
	"context"
	"math"
	"testing"

	"wiz-media-worker/internal/probe"
)

func TestParseProbeJSON(t *testing.T) {
	sampleJSON := []byte(`{
		"format": {
			"filename": "sample.mp4",
			"format_name": "mov,mp4,m4a,3gp,3g2,mj2",
			"duration": "128.500000",
			"size": "52428800",
			"bit_rate": "3263272"
		},
		"streams": [
			{
				"codec_type": "video",
				"codec_name": "h264",
				"profile": "High",
				"width": 1920,
				"height": 1080,
				"r_frame_rate": "30/1",
				"pix_fmt": "yuv420p"
			},
			{
				"codec_type": "audio",
				"codec_name": "aac",
				"channels": 2,
				"sample_rate": "48000",
				"bit_rate": "192000"
			}
		]
	}`)

	result, err := probe.ParseJSON(sampleJSON)
	if err != nil {
		t.Fatalf("expected no error, got %v", err)
	}

	if result.DurationSeconds != 128 {
		t.Errorf("expected duration 128, got %d", result.DurationSeconds)
	}
	if result.Format != "mov,mp4,m4a,3gp,3g2,mj2" {
		t.Errorf("unexpected format: %s", result.Format)
	}
	if result.SizeBytes != 52428800 {
		t.Errorf("expected size 52428800, got %d", result.SizeBytes)
	}
	if result.Bitrate != 3263272 {
		t.Errorf("expected bitrate 3263272, got %d", result.Bitrate)
	}
	if result.Video == nil {
		t.Fatal("expected video stream, got nil")
	}
	if result.Video.Codec != "h264" || result.Video.Width != 1920 || result.Video.Height != 1080 {
		t.Errorf("unexpected video stream data: %+v", result.Video)
	}
	if result.Video.FPS != 30.0 {
		t.Errorf("expected fps 30.0, got %f", result.Video.FPS)
	}
	if result.Audio == nil {
		t.Fatal("expected audio stream, got nil")
	}
	if result.Audio.Codec != "aac" || result.Audio.Channels != 2 || result.Audio.SampleRate != 48000 {
		t.Errorf("unexpected audio stream data: %+v", result.Audio)
	}
	if result.Metadata == nil {
		t.Fatal("expected non-nil Metadata map")
	}
	if result.Metadata["video_codec"] != "h264" || result.Metadata["audio_codec"] != "aac" {
		t.Errorf("unexpected metadata map: %+v", result.Metadata)
	}
	if result.ToMetadataMap()["video_codec"] != "h264" {
		t.Errorf("unexpected ToMetadataMap result: %+v", result.ToMetadataMap())
	}
}

func TestParseJSON_VideoOnly(t *testing.T) {
	sampleJSON := []byte(`{
		"format": {
			"filename": "silent.mp4",
			"format_name": "mp4",
			"duration": "10.000000",
			"size": "102400",
			"bit_rate": "819200"
		},
		"streams": [
			{
				"codec_type": "video",
				"codec_name": "h264",
				"width": 1280,
				"height": 720,
				"r_frame_rate": "24000/1001"
			}
		]
	}`)

	result, err := probe.ParseJSON(sampleJSON)
	if err != nil {
		t.Fatalf("expected no error, got %v", err)
	}

	if result.Video == nil {
		t.Fatal("expected video stream, got nil")
	}
	if result.Audio != nil {
		t.Errorf("expected nil audio stream, got %+v", result.Audio)
	}
	expectedFPS := 24000.0 / 1001.0
	if math.Abs(result.Video.FPS-expectedFPS) > 0.001 {
		t.Errorf("expected fps ~23.976, got %f", result.Video.FPS)
	}
}

func TestParseJSON_AudioOnly(t *testing.T) {
	sampleJSON := []byte(`{
		"format": {
			"filename": "audio.mp3",
			"format_name": "mp3",
			"duration": "60.000000",
			"size": "960000",
			"bit_rate": "128000"
		},
		"streams": [
			{
				"codec_type": "audio",
				"codec_name": "mp3",
				"channels": 2,
				"sample_rate": "44100",
				"bit_rate": "128000"
			}
		]
	}`)

	result, err := probe.ParseJSON(sampleJSON)
	if err != nil {
		t.Fatalf("expected no error, got %v", err)
	}

	if result.Video != nil {
		t.Errorf("expected nil video stream, got %+v", result.Video)
	}
	if result.Audio == nil {
		t.Fatal("expected audio stream, got nil")
	}
	if result.Audio.SampleRate != 44100 {
		t.Errorf("expected sample rate 44100, got %d", result.Audio.SampleRate)
	}
}

func TestParseJSON_DurationFallbackToStream(t *testing.T) {
	sampleJSON := []byte(`{
		"format": {
			"filename": "stream_dur.mp4",
			"format_name": "mp4",
			"size": "1000",
			"bit_rate": "10000"
		},
		"streams": [
			{
				"codec_type": "video",
				"codec_name": "h264",
				"width": 640,
				"height": 360,
				"duration": "45.800000"
			}
		]
	}`)

	result, err := probe.ParseJSON(sampleJSON)
	if err != nil {
		t.Fatalf("expected no error, got %v", err)
	}
	if result.DurationSeconds != 45 {
		t.Errorf("expected duration 45 from stream fallback, got %d", result.DurationSeconds)
	}
}

func TestParseJSON_MalformedJSON(t *testing.T) {
	malformed := []byte(`{ not valid json `)
	_, err := probe.ParseJSON(malformed)
	if err == nil {
		t.Fatalf("expected error for malformed json, got nil")
	}
}

func TestParseFrameRate(t *testing.T) {
	tests := []struct {
		input    string
		expected float64
	}{
		{"30/1", 30.0},
		{"24000/1001", 24000.0 / 1001.0},
		{"29.97", 29.97},
		{"0/0", 0.0},
		{"", 0.0},
		{"invalid", 0.0},
		{"60/0", 0.0},
		{"1/2/3", 0.0},
	}

	for _, tc := range tests {
		actual := probe.ParseFrameRate(tc.input)
		if math.Abs(actual-tc.expected) > 0.0001 {
			t.Errorf("ParseFrameRate(%q) = %f, expected %f", tc.input, actual, tc.expected)
		}
	}
}

func TestInspect_FailsOnMissingFile(t *testing.T) {
	inspector := probe.NewInspector("ffprobe")
	_, err := inspector.Inspect(context.Background(), "/nonexistent/video.mp4")
	if err == nil {
		t.Fatalf("expected error on nonexistent file, got nil")
	}

	// Also test package-level Inspect
	_, err = probe.Inspect(context.Background(), "/nonexistent/video.mp4")
	if err == nil {
		t.Fatalf("expected error on nonexistent file from probe.Inspect, got nil")
	}
}

func TestNewInspector_DefaultBinPath(t *testing.T) {
	inspector := probe.NewInspector("")
	if inspector == nil {
		t.Fatal("expected non-nil inspector")
	}
	if inspector.BinPath() != "ffprobe" {
		t.Errorf("expected default binPath 'ffprobe', got '%s'", inspector.BinPath())
	}
}
