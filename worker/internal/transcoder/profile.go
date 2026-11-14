package transcoder

import "fmt"

// Profile represents an adaptive bitrate rendition profile.
type Profile struct {
	Name         string
	Width        int
	Height       int
	VideoBitrate string
	MaxRate      string
	BufSize      string
	AudioBitrate string
	ProfileLevel string
}

// FilterString returns the FFmpeg video filter expression for scaling this profile.
func (p Profile) FilterString(streamIndex int) string {
	return fmt.Sprintf("[v%d]scale=%d:%d[v%dout]", streamIndex, p.Width, p.Height, streamIndex)
}

// Standard profile definitions.
var (
	Profile1080p = Profile{
		Name:         "1080p",
		Height:       1080,
		VideoBitrate: "4500k",
		MaxRate:      "4800k",
		BufSize:      "9000k",
		AudioBitrate: "192k",
		ProfileLevel: "high",
	}

	Profile720p = Profile{
		Name:         "720p",
		Height:       720,
		VideoBitrate: "2500k",
		MaxRate:      "2800k",
		BufSize:      "5000k",
		AudioBitrate: "128k",
		ProfileLevel: "high",
	}

	Profile480p = Profile{
		Name:         "480p",
		Height:       480,
		VideoBitrate: "1000k",
		MaxRate:      "1200k",
		BufSize:      "2000k",
		AudioBitrate: "96k",
		ProfileLevel: "main",
	}

	Profile360p = Profile{
		Name:         "360p",
		Height:       360,
		VideoBitrate: "600k",
		MaxRate:      "700k",
		BufSize:      "1200k",
		AudioBitrate: "64k",
		ProfileLevel: "main",
	}
)

// StandardProfiles lists standard rendition profiles from highest to lowest resolution.
var StandardProfiles = []Profile{
	Profile1080p,
	Profile720p,
	Profile480p,
	Profile360p,
}
