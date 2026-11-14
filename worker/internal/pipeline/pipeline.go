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
	"wiz-media-worker/internal/storage"
	"wiz-media-worker/internal/transcoder"
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

// Transcoder abstracts video transcoding to multi-variant HLS.
type Transcoder interface {
	TranscodeHLS(ctx context.Context, sourcePath, outputDir string, profiles []transcoder.Profile, opts ...transcoder.TranscodeOption) error
}

// ThumbnailGenerator abstracts poster frame extraction.
type ThumbnailGenerator interface {
	ExtractFrame(ctx context.Context, sourcePath, destPath string, durationSeconds float64) error
}

// AssetUploader abstracts uploading transcoded assets and thumbnails to object storage.
type AssetUploader interface {
	UploadHLSAssets(
		ctx context.Context,
		hlsDir string,
		thumbnailPath string,
		tenantID string,
		videoID string,
		processedBucket string,
		thumbnailBucket string,
	) ([]storage.AssetInfo, error)
}

// CallbackClient abstracts sending status notifications back to the platform API.
type CallbackClient interface {
	SendStatus(ctx context.Context, payload callback.Payload) error
}

// Pipeline orchestrates the end-to-end media processing lifecycle for a media job.
type Pipeline struct {
	cfg        *config.Config
	downloader Downloader
	probe      Inspector
	transcoder Transcoder
	thumbnail  ThumbnailGenerator
	uploader   AssetUploader
	callback   CallbackClient
	logger     *slog.Logger
}

// NewPipeline constructs a new Pipeline instance.
func NewPipeline(
	cfg *config.Config,
	downloader Downloader,
	probe Inspector,
	transcoder Transcoder,
	thumbnail ThumbnailGenerator,
	uploader AssetUploader,
	cb CallbackClient,
	logger *slog.Logger,
) *Pipeline {
	if logger == nil {
		logger = slog.Default()
	}
	return &Pipeline{
		cfg:        cfg,
		downloader: downloader,
		probe:      probe,
		transcoder: transcoder,
		thumbnail:  thumbnail,
		uploader:   uploader,
		callback:   cb,
		logger:     logger,
	}
}

// Process executes the complete media processing lifecycle for a media job:
// 1. Sends callback: status: probing
//    - If callback returns 404/422 (video deleted/aborted in panel), pipeline discards job and returns nil.
// 2. Generates local temporary file paths in cfg.TempDir
// 3. Downloads original source video from MinIO storage (downloader.DownloadSourceFile)
// 4. Runs FFprobe inspector (probe.Inspect)
// 5. Sends callback: status: transcoding with probed metadata and duration
// 6. Selects rendition ladder based on probed dimensions (transcoder.SelectLadder)
// 7. Executes single-pass FFmpeg HLS transcode into temp directory hlsDir (transcoder.TranscodeHLS)
// 8. Extracts poster thumbnail into temp file thumbPath (thumbnail.ExtractFrame)
// 9. Concurrently uploads all HLS files and thumbnail to MinIO (uploader.UploadHLSAssets)
// 10. Sends callback: status: ready with thumbnail_key and assets array
// 11. Always cleans up temporary files/directories via defer
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

	// 2. Prepare temp directory and file paths
	if err := os.MkdirAll(p.cfg.TempDir, 0755); err != nil {
		failErr := fmt.Errorf("failed to create temp directory %s: %w", p.cfg.TempDir, err)
		if cbErr := p.notifyFailure(ctx, job, failErr); errors.Is(cbErr, ErrJobDiscarded) {
			return nil
		}
		return failErr
	}

	tempFileName := fmt.Sprintf("%s-%s", job.JobID, filepath.Base(job.SourceKey))
	tempFilePath := filepath.Join(p.cfg.TempDir, tempFileName)
	hlsDir := filepath.Join(p.cfg.TempDir, fmt.Sprintf("%s-hls", job.JobID))
	thumbPath := filepath.Join(p.cfg.TempDir, fmt.Sprintf("%s-thumb.jpg", job.JobID))

	// Clean up all temporary files and directories upon completion
	defer func() {
		if err := os.Remove(tempFilePath); err != nil && !os.IsNotExist(err) {
			p.logger.WarnContext(ctx, "failed to remove temp source file", "file", tempFilePath, "error", err)
		}
		if err := os.RemoveAll(hlsDir); err != nil && !os.IsNotExist(err) {
			p.logger.WarnContext(ctx, "failed to remove hls dir", "dir", hlsDir, "error", err)
		}
		if err := os.Remove(thumbPath); err != nil && !os.IsNotExist(err) {
			p.logger.WarnContext(ctx, "failed to remove thumb file", "file", thumbPath, "error", err)
		}
	}()

	// 3. Download source file from MinIO storage
	if err := p.downloader.DownloadSourceFile(ctx, job.SourceBucket, job.SourceKey, tempFilePath); err != nil {
		failErr := fmt.Errorf("download source file failed: %w", err)
		if cbErr := p.notifyFailure(ctx, job, failErr); errors.Is(cbErr, ErrJobDiscarded) {
			return nil
		}
		return failErr
	}

	// 4. Run FFprobe inspector
	probeResult, err := p.probe.Inspect(ctx, tempFilePath)
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

	if probeResult.Video == nil {
		failErr := fmt.Errorf("probe inspection succeeded but no video stream detected")
		cbErr := p.notifyFailure(ctx, job, failErr)
		if errors.Is(cbErr, ErrJobDiscarded) {
			return nil
		}
		if cbErr != nil {
			return fmt.Errorf("no video stream and failure callback failed: %w", cbErr)
		}
		p.logger.InfoContext(ctx, "no video stream decisively reported to platform as failed",
			"job_id", job.JobID,
			"error", failErr,
		)
		return nil
	}

	// 5. Send status: transcoding with probed duration and metadata
	dur := probeResult.DurationSeconds
	if err := p.callback.SendStatus(ctx, callback.Payload{
		JobID:           job.JobID,
		VideoID:         job.VideoID,
		Status:          callback.StatusTranscoding,
		DurationSeconds: &dur,
		Metadata:        probeResult.ToMetadataMap(),
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
		p.logger.ErrorContext(ctx, "failed to send transcoding callback", "job_id", job.JobID, "error", err)
		return fmt.Errorf("failed to send transcoding callback: %w", err)
	}

	// 6. Select rendition ladder based on probed dimensions
	ladder := transcoder.SelectLadder(probeResult.Video.Width, probeResult.Video.Height)
	if len(ladder) == 0 {
		failErr := fmt.Errorf("no rendition profiles selected for resolution %dx%d", probeResult.Video.Width, probeResult.Video.Height)
		cbErr := p.notifyFailure(ctx, job, failErr)
		if errors.Is(cbErr, ErrJobDiscarded) {
			return nil
		}
		if cbErr != nil {
			return fmt.Errorf("invalid ladder and failure callback failed: %w", cbErr)
		}
		p.logger.InfoContext(ctx, "invalid ladder decisively reported to platform as failed",
			"job_id", job.JobID,
			"error", failErr,
		)
		return nil
	}

	// 7. Execute single-pass multi-variant FFmpeg HLS transcode
	hasAudio := probeResult.Audio != nil
	if err := p.transcoder.TranscodeHLS(ctx, tempFilePath, hlsDir, ladder, transcoder.WithAudio(hasAudio)); err != nil {
		failErr := fmt.Errorf("transcoding failed: %w", err)
		cbErr := p.notifyFailure(ctx, job, failErr)
		if errors.Is(cbErr, ErrJobDiscarded) {
			return nil
		}
		if cbErr != nil {
			return fmt.Errorf("transcoding failed and failure callback failed: %w (transcode err: %v)", cbErr, failErr)
		}
		p.logger.InfoContext(ctx, "transcoding failure decisively reported to platform as failed",
			"job_id", job.JobID,
			"error", failErr,
		)
		return nil
	}

	// 8. Extract poster thumbnail frame
	if err := p.thumbnail.ExtractFrame(ctx, tempFilePath, thumbPath, float64(probeResult.DurationSeconds)); err != nil {
		failErr := fmt.Errorf("thumbnail extraction failed: %w", err)
		cbErr := p.notifyFailure(ctx, job, failErr)
		if errors.Is(cbErr, ErrJobDiscarded) {
			return nil
		}
		if cbErr != nil {
			return fmt.Errorf("thumbnail extraction failed and failure callback failed: %w (thumbnail err: %v)", cbErr, failErr)
		}
		p.logger.InfoContext(ctx, "thumbnail failure decisively reported to platform as failed",
			"job_id", job.JobID,
			"error", failErr,
		)
		return nil
	}

	// 9. Concurrently upload HLS assets and thumbnail to MinIO
	assets, err := p.uploader.UploadHLSAssets(
		ctx,
		hlsDir,
		thumbPath,
		job.TenantID,
		job.VideoID,
		p.cfg.MinioProcessedBucket,
		p.cfg.MinioThumbnailsBucket,
	)
	if err != nil {
		failErr := fmt.Errorf("upload assets failed: %w", err)
		if cbErr := p.notifyFailure(ctx, job, failErr); errors.Is(cbErr, ErrJobDiscarded) {
			return nil
		}
		return failErr
	}

	// 10. Notify Laravel status: ready with thumbnail_key and assets array
	assetPayloads := make([]callback.AssetPayload, len(assets))
	var thumbnailKey string
	for i, a := range assets {
		assetPayloads[i] = callback.AssetPayload{
			Type:      a.Type,
			Disk:      a.Disk,
			ObjectKey: a.ObjectKey,
			SizeBytes: a.SizeBytes,
			MimeType:  a.MimeType,
			Metadata:  a.Metadata,
		}
		if a.Type == "thumbnail" {
			thumbnailKey = a.ObjectKey
		}
	}

	if err := p.callback.SendStatus(ctx, callback.Payload{
		JobID:           job.JobID,
		VideoID:         job.VideoID,
		Status:          callback.StatusReady,
		DurationSeconds: &dur,
		ThumbnailKey:    thumbnailKey,
		Assets:          assetPayloads,
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
		p.logger.ErrorContext(ctx, "failed to send ready callback", "job_id", job.JobID, "error", err)
		return fmt.Errorf("failed to send ready callback: %w", err)
	}

	p.logger.InfoContext(ctx, "media job successfully processed and ready",
		"job_id", job.JobID,
		"video_id", job.VideoID,
		"assets_count", len(assets),
		"thumbnail_key", thumbnailKey,
	)
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
