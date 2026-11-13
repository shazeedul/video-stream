package probe

// FFprobeOutput represents the top-level JSON structure returned by ffprobe.
type FFprobeOutput struct {
	Format  FFprobeFormat   `json:"format"`
	Streams []FFprobeStream `json:"streams"`
}

// FFprobeFormat represents container-level media format metadata from ffprobe.
type FFprobeFormat struct {
	Filename       string            `json:"filename"`
	NBStreams      int               `json:"nb_streams"`
	NBPrograms     int               `json:"nb_programs"`
	FormatName     string            `json:"format_name"`
	FormatLongName string            `json:"format_long_name"`
	StartTime      string            `json:"start_time"`
	Duration       string            `json:"duration"`
	Size           string            `json:"size"`
	BitRate        string            `json:"bit_rate"`
	ProbeScore     int               `json:"probe_score"`
	Tags           map[string]string `json:"tags,omitempty"`
}

// FFprobeStream represents a single stream (audio, video, etc.) from ffprobe.
type FFprobeStream struct {
	Index              int               `json:"index"`
	CodecName          string            `json:"codec_name"`
	CodecLongName      string            `json:"codec_long_name"`
	Profile            string            `json:"profile"`
	CodecType          string            `json:"codec_type"`
	CodecTagString     string            `json:"codec_tag_string"`
	CodecTag           string            `json:"codec_tag"`
	Width              int               `json:"width,omitempty"`
	Height             int               `json:"height,omitempty"`
	CodedWidth         int               `json:"coded_width,omitempty"`
	CodedHeight        int               `json:"coded_height,omitempty"`
	HasBFrames         int               `json:"has_b_frames,omitempty"`
	SampleAspectRatio  string            `json:"sample_aspect_ratio,omitempty"`
	DisplayAspectRatio string            `json:"display_aspect_ratio,omitempty"`
	PixFmt             string            `json:"pix_fmt,omitempty"`
	Level              int               `json:"level,omitempty"`
	RFrameRate         string            `json:"r_frame_rate,omitempty"`
	AvgFrameRate       string            `json:"avg_frame_rate,omitempty"`
	TimeBase           string            `json:"time_base,omitempty"`
	StartPts           int64             `json:"start_pts,omitempty"`
	StartTime          string            `json:"start_time,omitempty"`
	DurationTs         int64             `json:"duration_ts,omitempty"`
	Duration           string            `json:"duration,omitempty"`
	BitRate            string            `json:"bit_rate,omitempty"`
	NBFrames           string            `json:"nb_frames,omitempty"`
	SampleRate         string            `json:"sample_rate,omitempty"`
	Channels           int               `json:"channels,omitempty"`
	ChannelLayout      string            `json:"channel_layout,omitempty"`
	BitsPerSample      int               `json:"bits_per_sample,omitempty"`
	Tags               map[string]string `json:"tags,omitempty"`
}

// VideoStreamInfo contains normalized video stream parameters.
type VideoStreamInfo struct {
	Codec       string  `json:"codec"`
	Profile     string  `json:"profile"`
	Width       int     `json:"width"`
	Height      int     `json:"height"`
	AspectRatio string  `json:"aspect_ratio"`
	FPS         float64 `json:"fps"`
	PixFmt      string  `json:"pix_fmt"`
	Bitrate     int64   `json:"bitrate"`
}

// AudioStreamInfo contains normalized audio stream parameters.
type AudioStreamInfo struct {
	Codec      string `json:"codec"`
	Channels   int    `json:"channels"`
	SampleRate int    `json:"sample_rate"`
	Bitrate    int64  `json:"bitrate"`
}

// ProbeResult contains normalized media metadata extracted by ffprobe.
type ProbeResult struct {
	DurationSeconds int              `json:"duration_seconds"`
	Format          string           `json:"format"`
	SizeBytes       int64            `json:"size_bytes"`
	Bitrate         int64            `json:"bitrate"`
	Video           *VideoStreamInfo `json:"video,omitempty"`
	Audio           *AudioStreamInfo `json:"audio,omitempty"`
	Metadata        map[string]any   `json:"metadata"`
	RawMetadata     map[string]any   `json:"raw_metadata,omitempty"`
}

// ToMetadataMap returns the flattened metadata map suitable for database persistence.
func (p *ProbeResult) ToMetadataMap() map[string]any {
	return p.Metadata
}
