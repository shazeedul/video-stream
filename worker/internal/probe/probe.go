package probe

import (
	"bytes"
	"context"
	"encoding/json"
	"fmt"
	"os/exec"
	"strconv"
	"strings"
)

// Inspector encapsulates ffprobe CLI invocation.
type Inspector struct {
	binPath string
}

// NewInspector creates an Inspector with the specified binary path.
// If binPath is empty, it defaults to "ffprobe".
func NewInspector(binPath string) *Inspector {
	if binPath == "" {
		binPath = "ffprobe"
	}
	return &Inspector{binPath: binPath}
}

// BinPath returns the path to the configured ffprobe binary.
func (i *Inspector) BinPath() string {
	return i.binPath
}

// Inspect runs ffprobe on the given filePath and returns the parsed ProbeResult.
func (i *Inspector) Inspect(ctx context.Context, filePath string) (*ProbeResult, error) {
	cmd := exec.CommandContext(
		ctx,
		i.binPath,
		"-v", "quiet",
		"-print_format", "json",
		"-show_format",
		"-show_streams",
		filePath,
	)

	var stdout, stderr bytes.Buffer
	cmd.Stdout = &stdout
	cmd.Stderr = &stderr

	if err := cmd.Run(); err != nil {
		stderrStr := strings.TrimSpace(stderr.String())
		if stderrStr != "" {
			return nil, fmt.Errorf("ffprobe execution failed on %q: %w: %s", filePath, err, stderrStr)
		}
		return nil, fmt.Errorf("ffprobe execution failed on %q: %w", filePath, err)
	}

	return ParseJSON(stdout.Bytes())
}

// Inspect is a convenience function using the default "ffprobe" binary path.
func Inspect(ctx context.Context, filePath string) (*ProbeResult, error) {
	return NewInspector("").Inspect(ctx, filePath)
}

// ParseJSON parses raw JSON output from ffprobe into a structured ProbeResult.
func ParseJSON(data []byte) (*ProbeResult, error) {
	var raw FFprobeOutput
	if err := json.Unmarshal(data, &raw); err != nil {
		return nil, fmt.Errorf("failed to unmarshal ffprobe json: %w", err)
	}

	res := &ProbeResult{
		Format: raw.Format.FormatName,
	}

	// Parse duration: e.g. "128.500000"
	if raw.Format.Duration != "" {
		if durFloat, err := strconv.ParseFloat(raw.Format.Duration, 64); err == nil {
			res.DurationSeconds = int(durFloat)
		}
	}

	// Parse size: e.g. "52428800"
	if raw.Format.Size != "" {
		if sizeBytes, err := strconv.ParseInt(raw.Format.Size, 10, 64); err == nil {
			res.SizeBytes = sizeBytes
		}
	}

	// Parse bitrate: e.g. "3263272"
	if raw.Format.BitRate != "" {
		if br, err := strconv.ParseInt(raw.Format.BitRate, 10, 64); err == nil {
			res.Bitrate = br
		}
	}

	// Find primary video and audio streams
	for _, stream := range raw.Streams {
		switch stream.CodecType {
		case "video":
			if res.Video == nil {
				vInfo := &VideoStreamInfo{
					Codec:       stream.CodecName,
					Profile:     stream.Profile,
					Width:       stream.Width,
					Height:      stream.Height,
					AspectRatio: stream.DisplayAspectRatio,
					PixFmt:      stream.PixFmt,
					FPS:         ParseFrameRate(stream.RFrameRate),
				}
				if vInfo.FPS == 0 {
					vInfo.FPS = ParseFrameRate(stream.AvgFrameRate)
				}
				if stream.BitRate != "" {
					if br, err := strconv.ParseInt(stream.BitRate, 10, 64); err == nil {
						vInfo.Bitrate = br
					}
				}
				res.Video = vInfo
			}
		case "audio":
			if res.Audio == nil {
				aInfo := &AudioStreamInfo{
					Codec:    stream.CodecName,
					Channels: stream.Channels,
				}
				if stream.SampleRate != "" {
					if sr, err := strconv.Atoi(stream.SampleRate); err == nil {
						aInfo.SampleRate = sr
					}
				}
				if stream.BitRate != "" {
					if br, err := strconv.ParseInt(stream.BitRate, 10, 64); err == nil {
						aInfo.Bitrate = br
					}
				}
				res.Audio = aInfo
			}
		}
	}

	// If duration was missing from format, try streams
	if res.DurationSeconds == 0 {
		for _, s := range raw.Streams {
			if s.Duration != "" {
				if d, err := strconv.ParseFloat(s.Duration, 64); err == nil && d > 0 {
					res.DurationSeconds = int(d)
					break
				}
			}
		}
	}

	// Build metadata map
	res.Metadata = make(map[string]any)
	res.Metadata["duration"] = res.DurationSeconds
	res.Metadata["format"] = res.Format
	res.Metadata["size"] = res.SizeBytes
	res.Metadata["bitrate"] = res.Bitrate

	if res.Video != nil {
		res.Metadata["video_codec"] = res.Video.Codec
		res.Metadata["profile"] = res.Video.Profile
		res.Metadata["width"] = res.Video.Width
		res.Metadata["height"] = res.Video.Height
		res.Metadata["aspect_ratio"] = res.Video.AspectRatio
		res.Metadata["fps"] = res.Video.FPS
		res.Metadata["pix_fmt"] = res.Video.PixFmt
		res.Metadata["video_bitrate"] = res.Video.Bitrate
	}

	if res.Audio != nil {
		res.Metadata["audio_codec"] = res.Audio.Codec
		res.Metadata["channels"] = res.Audio.Channels
		res.Metadata["sample_rate"] = res.Audio.SampleRate
		res.Metadata["audio_bitrate"] = res.Audio.Bitrate
	}

	// Also store unmarshaled raw metadata
	var rawMap map[string]any
	if err := json.Unmarshal(data, &rawMap); err == nil {
		res.RawMetadata = rawMap
	}

	return res, nil
}

// ParseFrameRate converts fractional or decimal frame rate strings (e.g. "30/1", "24000/1001", "29.97") to float64.
// Returns 0.0 if the string is empty or cannot be parsed.
func ParseFrameRate(fpsStr string) float64 {
	fpsStr = strings.TrimSpace(fpsStr)
	if fpsStr == "" || fpsStr == "0/0" {
		return 0.0
	}

	if strings.Contains(fpsStr, "/") {
		parts := strings.Split(fpsStr, "/")
		if len(parts) == 2 {
			num, err1 := strconv.ParseFloat(strings.TrimSpace(parts[0]), 64)
			den, err2 := strconv.ParseFloat(strings.TrimSpace(parts[1]), 64)
			if err1 == nil && err2 == nil && den != 0 {
				return num / den
			}
		}
		return 0.0
	}

	fps, err := strconv.ParseFloat(fpsStr, 64)
	if err != nil {
		return 0.0
	}
	return fps
}
