package pipeline

import (
	"errors"
	"strings"
)

// MediaJob represents the payload delivered via RabbitMQ for video probing.
type MediaJob struct {
	Version      string `json:"version"`
	JobID        string `json:"job_id"`
	TenantID     string `json:"tenant_id"`
	VideoID      string `json:"video_id"`
	SourceBucket string `json:"source_bucket"`
	SourceKey    string `json:"source_key"`
	CreatedAt    string `json:"created_at"`
}

// Validate checks that all required fields are present in the MediaJob.
func (j *MediaJob) Validate() error {
	if strings.TrimSpace(j.JobID) == "" {
		return errors.New("missing or empty job_id")
	}
	if strings.TrimSpace(j.VideoID) == "" {
		return errors.New("missing or empty video_id")
	}
	if strings.TrimSpace(j.SourceBucket) == "" {
		return errors.New("missing or empty source_bucket")
	}
	if strings.TrimSpace(j.SourceKey) == "" {
		return errors.New("missing or empty source_key")
	}
	return nil
}
