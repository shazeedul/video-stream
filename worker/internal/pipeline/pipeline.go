package pipeline

import (
	"context"
	"errors"
	"fmt"
	"log/slog"
	"os"
	"path/filepath"

	"wiz-media-worker/internal/callback"
	"wiz-media-worker/internal/config"
	"wiz-media-worker/internal/probe"
)

// ErrJobDiscarded indicates that the media job was discarded because the video was deleted or missing in the platform.
var ErrJobDiscarded = errors.New("job discarded: video missing or deleted in platform")

// Downloader abstracts downloading source media files from object storage.
type Downloader interface {
	DownloadSourceFile(ctx context.Context, bucket, objectKey, destPath string) error
}

// Inspector abstracts ffprobe media analysis.
type Inspector interface {
	Inspect(ctx context.Context, filePath string) (*probe.ProbeResult, error)
}

// CallbackClient abstracts sending status notifications back to the platform API.
type CallbackClient interface {
	SendStatus(ctx context.Context, payload callback.Payload) error
}

// Pipeline orchestrates the end-to-end probing lifecycle for a media job.
type Pipeline struct {
	cfg      *config.Config
	storage  Downloader
	probe    Inspector
	callback CallbackClient
	logger   *slog.Logger
}

// NewPipeline constructs a new Pipeline instance.
func NewPipeline(
	cfg *config.Config,
	storage Downloader,
	probe Inspector,
	cb CallbackClient,
	logger *slog.Logger,
) *Pipeline {
	if logger == nil {
		logger = slog.Default()
	}
	return &Pipeline{
		cfg:      cfg,
		storage:  storage,
		probe:    probe,
		callback: cb,
		logger:   logger,
	}
}

// Process executes the complete media probing lifecycle for a media job:
// 1. Sends callback: status: probing
//    - If callback returns 404/422 (video deleted/aborted in panel), pipeline discards job and returns nil.
// 2. Generates local temporary file path in cfg.TempDir
// 3. Downloads video from MinIO storage (storage.DownloadSourceFile)
// 4. Runs FFprobe inspector (probe.Inspect)
// 5. If probe succeeds: sends callback status: probed, duration_seconds, metadata
// 6. If probe fails: sends callback status: failed, error: truncated err
//    - If failure callback succeeds: media corruption is decisively reported (returns nil -> message ACKed).
//    - If failure callback returns 404 or 422: video deleted/aborted in panel, safely discarded (returns nil -> message ACKed).
//    - If failure callback fails with other error: returns error (message NACKed with requeue).
// 7. If storage download fails: sends callback status: failed, returns error (message NACKed with requeue).
//    - If failure callback returns 404 or 422: safely discarded (returns nil -> message ACKed).
// 8. Always cleans up temporary file via defer os.Remove(...)
func (p *Pipeline) Process(ctx context.Context, job MediaJob) error {
	p.logger.InfoContext(ctx, "processing media job", "job_id", job.JobID, "video_id", job.VideoID)

	// 1. Send status: probing
	if err := p.callback.SendStatus(ctx, callback.Payload{
		JobID:   job.JobID,
		VideoID: job.VideoID,
		Status:  callback.StatusProbing,
	}); err != nil {
		var clientErr *callback.ClientError
		if errors.As(err, &clientErr) && clientErr.IsNotFoundOrUnprocessable() {
			p.logger.WarnContext(ctx, "video deleted or aborted in panel, discarding job",
				"job_id", job.JobID,
				"video_id", job.VideoID,
				"status_code", clientErr.StatusCode,
			)
			return nil
		}
		p.logger.WarnContext(ctx, "failed to send probing status callback", "job_id", job.JobID, "error", err)
	}

	// 2. Prepare temp directory and file path
	if err := os.MkdirAll(p.cfg.TempDir, 0755); err != nil {
		failErr := fmt.Errorf("failed to create temp directory %s: %w", p.cfg.TempDir, err)
		if cbErr := p.notifyFailure(ctx, job, failErr); errors.Is(cbErr, ErrJobDiscarded) {
			return nil
		}
		return failErr
	}

	tempFileName := fmt.Sprintf("%s-%s", job.JobID, filepath.Base(job.SourceKey))
	tempFilePath := filepath.Join(p.cfg.TempDir, tempFileName)

	// Clean up temporary file upon completion
	defer func() {
		if err := os.Remove(tempFilePath); err != nil && !os.IsNotExist(err) {
			p.logger.WarnContext(ctx, "failed to remove temp file", "file", tempFilePath, "error", err)
		}
	}()

	// 3. Download video from MinIO storage
	if err := p.storage.DownloadSourceFile(ctx, job.SourceBucket, job.SourceKey, tempFilePath); err != nil {
		failErr := fmt.Errorf("download source file failed: %w", err)
		if cbErr := p.notifyFailure(ctx, job, failErr); errors.Is(cbErr, ErrJobDiscarded) {
			return nil
		}
		return failErr
	}

	// 4. Run FFprobe inspector
	result, err := p.probe.Inspect(ctx, tempFilePath)
	if err != nil {
		failErr := fmt.Errorf("probe inspection failed: %w", err)
		cbErr := p.notifyFailure(ctx, job, failErr)
		if errors.Is(cbErr, ErrJobDiscarded) {
			return nil
		}
		if cbErr != nil {
			return fmt.Errorf("probe failed and failure callback failed: %w (probe err: %v)", cbErr, failErr)
		}
		p.logger.InfoContext(ctx, "corrupted/invalid media decisively reported to platform as failed",
			"job_id", job.JobID,
			"error", failErr,
		)
		return nil
	}

	// 5. Send probed callback with metadata and duration
	dur := result.DurationSeconds
	if err := p.callback.SendStatus(ctx, callback.Payload{
		JobID:           job.JobID,
		VideoID:         job.VideoID,
		Status:          callback.StatusProbed,
		DurationSeconds: &dur,
		Metadata:        result.ToMetadataMap(),
	}); err != nil {
		var clientErr *callback.ClientError
		if errors.As(err, &clientErr) && clientErr.IsNotFoundOrUnprocessable() {
			p.logger.WarnContext(ctx, "video deleted or aborted in panel, discarding job",
				"job_id", job.JobID,
				"video_id", job.VideoID,
				"status_code", clientErr.StatusCode,
			)
			return nil
		}
		p.logger.ErrorContext(ctx, "failed to send probed callback", "job_id", job.JobID, "error", err)
		return fmt.Errorf("failed to send probed callback: %w", err)
	}

	p.logger.InfoContext(ctx, "media job successfully probed", "job_id", job.JobID, "duration_seconds", result.DurationSeconds)
	return nil
}

func (p *Pipeline) notifyFailure(ctx context.Context, job MediaJob, err error) error {
	p.logger.ErrorContext(ctx, "media job failed", "job_id", job.JobID, "error", err)

	errStr := err.Error()
	runes := []rune(errStr)
	if len(runes) > 1950 {
		errStr = string(runes[:1950]) + " ... [truncated]"
	}

	cbErr := p.callback.SendStatus(ctx, callback.Payload{
		JobID:   job.JobID,
		VideoID: job.VideoID,
		Status:  callback.StatusFailed,
		Error:   errStr,
	})
	if cbErr != nil {
		var clientErr *callback.ClientError
		if errors.As(cbErr, &clientErr) && clientErr.IsNotFoundOrUnprocessable() {
			p.logger.WarnContext(ctx, "video deleted or aborted in panel, discarding job",
				"job_id", job.JobID,
				"video_id", job.VideoID,
				"status_code", clientErr.StatusCode,
			)
			return ErrJobDiscarded
		}
		p.logger.WarnContext(ctx, "failed to send failure callback", "job_id", job.JobID, "error", cbErr)
		return fmt.Errorf("failure callback failed: %w", cbErr)
	}
	return nil
}
