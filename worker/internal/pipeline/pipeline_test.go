package pipeline_test

import (
	"context"
	"errors"
	"fmt"
	"io"
	"log/slog"
	"os"
	"path/filepath"
	"strings"
	"sync"
	"testing"

	"wiz-media-worker/internal/callback"
	"wiz-media-worker/internal/config"
	"wiz-media-worker/internal/pipeline"
	"wiz-media-worker/internal/probe"
	"wiz-media-worker/internal/storage"
	"wiz-media-worker/internal/transcoder"
)

type mockDownloader struct {
	downloadFn func(ctx context.Context, bucket, objectKey, destPath string) error
}

func (m *mockDownloader) DownloadSourceFile(ctx context.Context, bucket, objectKey, destPath string) error {
	if m.downloadFn != nil {
		return m.downloadFn(ctx, bucket, objectKey, destPath)
	}
	return os.WriteFile(destPath, []byte("fake-source-mp4"), 0644)
}

type mockInspector struct {
	inspectFn func(ctx context.Context, filePath string) (*probe.ProbeResult, error)
}

func (m *mockInspector) Inspect(ctx context.Context, filePath string) (*probe.ProbeResult, error) {
	if m.inspectFn != nil {
		return m.inspectFn(ctx, filePath)
	}
	return &probe.ProbeResult{
		DurationSeconds: 120,
		Format:          "mp4",
		SizeBytes:       10240,
		Video: &probe.VideoStreamInfo{
			Codec:  "h264",
			Width:  1920,
			Height: 1080,
		},
		Audio: &probe.AudioStreamInfo{
			Codec:    "aac",
			Channels: 2,
		},
		Metadata: map[string]any{
			"duration": 120,
			"format":   "mp4",
		},
	}, nil
}

type mockTranscoder struct {
	transcodeFn        func(ctx context.Context, sourcePath, outputDir string, profiles []transcoder.Profile, opts ...transcoder.TranscodeOption) error
	calledWithProfiles []transcoder.Profile
	calledWithOpts     []transcoder.TranscodeOption
	calledOutputDir    string
}

func (m *mockTranscoder) TranscodeHLS(ctx context.Context, sourcePath, outputDir string, profiles []transcoder.Profile, opts ...transcoder.TranscodeOption) error {
	m.calledWithProfiles = profiles
	m.calledWithOpts = opts
	m.calledOutputDir = outputDir
	if m.transcodeFn != nil {
		return m.transcodeFn(ctx, sourcePath, outputDir, profiles, opts...)
	}
	_ = os.MkdirAll(outputDir, 0755)
	_ = os.WriteFile(filepath.Join(outputDir, "master.m3u8"), []byte("#EXTM3U"), 0644)
	_ = os.WriteFile(filepath.Join(outputDir, "stream_0.m3u8"), []byte("#EXTM3U"), 0644)
	return nil
}

type mockThumbnail struct {
	extractFn          func(ctx context.Context, sourcePath, destPath string, durationSeconds float64) error
	calledWithDuration float64
	calledDestPath     string
}

func (m *mockThumbnail) ExtractFrame(ctx context.Context, sourcePath, destPath string, durationSeconds float64) error {
	m.calledWithDuration = durationSeconds
	m.calledDestPath = destPath
	if m.extractFn != nil {
		return m.extractFn(ctx, sourcePath, destPath, durationSeconds)
	}
	_ = os.MkdirAll(filepath.Dir(destPath), 0755)
	return os.WriteFile(destPath, []byte("fake-jpeg-data"), 0644)
}

type mockUploader struct {
	uploadFn                func(ctx context.Context, hlsDir, thumbnailPath, tenantID, videoID, processedBucket, thumbnailBucket string) ([]storage.AssetInfo, error)
	calledTenantID          string
	calledVideoID           string
	calledProcessedBucket   string
	calledThumbnailsBucket  string
	calledHlsDir            string
	calledThumbnailPath     string
}

func (m *mockUploader) UploadHLSAssets(
	ctx context.Context,
	hlsDir string,
	thumbnailPath string,
	tenantID string,
	videoID string,
	processedBucket string,
	thumbnailBucket string,
) ([]storage.AssetInfo, error) {
	m.calledTenantID = tenantID
	m.calledVideoID = videoID
	m.calledProcessedBucket = processedBucket
	m.calledThumbnailsBucket = thumbnailBucket
	m.calledHlsDir = hlsDir
	m.calledThumbnailPath = thumbnailPath
	if m.uploadFn != nil {
		return m.uploadFn(ctx, hlsDir, thumbnailPath, tenantID, videoID, processedBucket, thumbnailBucket)
	}
	return []storage.AssetInfo{
		{
			Type:      "master",
			Disk:      "minio-processed",
			ObjectKey: fmt.Sprintf("tenants/%s/videos/%s/hls/master.m3u8", tenantID, videoID),
			SizeBytes: 256,
			MimeType:  "application/x-mpegURL",
		},
		{
			Type:      "hls_playlist",
			Disk:      "minio-processed",
			ObjectKey: fmt.Sprintf("tenants/%s/videos/%s/hls/stream_0.m3u8", tenantID, videoID),
			SizeBytes: 512,
			MimeType:  "application/x-mpegURL",
		},
		{
			Type:      "thumbnail",
			Disk:      "thumbnails",
			ObjectKey: fmt.Sprintf("tenants/%s/videos/%s/thumbnail.jpg", tenantID, videoID),
			SizeBytes: 1024,
			MimeType:  "image/jpeg",
		},
	}, nil
}

type mockCallbackClient struct {
	mu           sync.Mutex
	payloads     []callback.Payload
	err          error
	statusErrMap map[string]error
}

func (m *mockCallbackClient) SendStatus(ctx context.Context, payload callback.Payload) error {
	m.mu.Lock()
	defer m.mu.Unlock()
	m.payloads = append(m.payloads, payload)
	if m.statusErrMap != nil {
		if err, ok := m.statusErrMap[payload.Status]; ok {
			return err
		}
	}
	return m.err
}

func (m *mockCallbackClient) Payloads() []callback.Payload {
	m.mu.Lock()
	defer m.mu.Unlock()
	cp := make([]callback.Payload, len(m.payloads))
	copy(cp, m.payloads)
	return cp
}

func newTestPipeline(
	cfg *config.Config,
	dl pipeline.Downloader,
	insp pipeline.Inspector,
	tr pipeline.Transcoder,
	th pipeline.ThumbnailGenerator,
	up pipeline.AssetUploader,
	cb pipeline.CallbackClient,
	logger *slog.Logger,
) *pipeline.Pipeline {
	if dl == nil {
		dl = &mockDownloader{}
	}
	if insp == nil {
		insp = &mockInspector{}
	}
	if tr == nil {
		tr = &mockTranscoder{}
	}
	if th == nil {
		th = &mockThumbnail{}
	}
	if up == nil {
		up = &mockUploader{}
	}
	if cb == nil {
		cb = &mockCallbackClient{}
	}
	if logger == nil {
		logger = slog.New(slog.NewTextHandler(io.Discard, nil))
	}
	return pipeline.NewPipeline(cfg, dl, insp, tr, th, up, cb, logger)
}

func TestPipeline_Process_Success(t *testing.T) {
	tempDir := t.TempDir()
	cfg := &config.Config{
		TempDir:               tempDir,
		MinioProcessedBucket:  "test-processed",
		MinioThumbnailsBucket: "test-thumbs",
	}

	var downloadedPath string
	dl := &mockDownloader{
		downloadFn: func(ctx context.Context, bucket, objectKey, destPath string) error {
			downloadedPath = destPath
			return os.WriteFile(destPath, []byte("fake-mp4-data"), 0644)
		},
	}

	var createdHlsDir string
	tr := &mockTranscoder{
		transcodeFn: func(ctx context.Context, sourcePath, outputDir string, profiles []transcoder.Profile, opts ...transcoder.TranscodeOption) error {
			createdHlsDir = outputDir
			_ = os.MkdirAll(outputDir, 0755)
			_ = os.WriteFile(filepath.Join(outputDir, "master.m3u8"), []byte("#EXTM3U"), 0644)
			return nil
		},
	}

	var createdThumbPath string
	th := &mockThumbnail{
		extractFn: func(ctx context.Context, sourcePath, destPath string, durationSeconds float64) error {
			createdThumbPath = destPath
			return os.WriteFile(destPath, []byte("fake-jpeg-data"), 0644)
		},
	}

	up := &mockUploader{}
	cb := &mockCallbackClient{}

	p := newTestPipeline(cfg, dl, nil, tr, th, up, cb, nil)

	job := pipeline.MediaJob{
		Version:      "1.0",
		JobID:        "job-abc",
		TenantID:     "tenant-1",
		VideoID:      "video-xyz",
		SourceBucket: "uploads",
		SourceKey:    "raw/video.mp4",
	}

	err := p.Process(context.Background(), job)
	if err != nil {
		t.Fatalf("expected no error, got %v", err)
	}

	// Verify temp source file was deleted
	if _, err := os.Stat(downloadedPath); !os.IsNotExist(err) {
		t.Errorf("expected temp file %s to be deleted, but it exists", downloadedPath)
	}
	// Verify temp HLS dir was deleted
	if _, err := os.Stat(createdHlsDir); !os.IsNotExist(err) {
		t.Errorf("expected temp hls dir %s to be deleted, but it exists", createdHlsDir)
	}
	// Verify temp thumbnail was deleted
	if _, err := os.Stat(createdThumbPath); !os.IsNotExist(err) {
		t.Errorf("expected temp thumb %s to be deleted, but it exists", createdThumbPath)
	}

	// Verify callbacks sent: probing -> transcoding -> ready
	payloads := cb.Payloads()
	if len(payloads) != 3 {
		t.Fatalf("expected 3 callbacks (probing, transcoding, ready), got %d", len(payloads))
	}

	if payloads[0].Status != callback.StatusProbing {
		t.Errorf("expected first callback status 'probing', got '%s'", payloads[0].Status)
	}

	if payloads[1].Status != callback.StatusTranscoding {
		t.Errorf("expected second callback status 'transcoding', got '%s'", payloads[1].Status)
	}
	if payloads[1].DurationSeconds == nil || *payloads[1].DurationSeconds != 120 {
		t.Errorf("expected duration 120, got %v", payloads[1].DurationSeconds)
	}
	if payloads[1].Metadata["format"] != "mp4" {
		t.Errorf("expected metadata format mp4, got %v", payloads[1].Metadata["format"])
	}

	if payloads[2].Status != callback.StatusReady {
		t.Errorf("expected third callback status 'ready', got '%s'", payloads[2].Status)
	}
	expectedThumbKey := "tenants/tenant-1/videos/video-xyz/thumbnail.jpg"
	if payloads[2].ThumbnailKey != expectedThumbKey {
		t.Errorf("expected thumbnail key %q, got %q", expectedThumbKey, payloads[2].ThumbnailKey)
	}
	if len(payloads[2].Assets) != 3 {
		t.Errorf("expected 3 assets in ready payload, got %d", len(payloads[2].Assets))
	}

	// Verify uploader parameters
	if up.calledProcessedBucket != "test-processed" {
		t.Errorf("expected processed bucket 'test-processed', got %q", up.calledProcessedBucket)
	}
	if up.calledThumbnailsBucket != "test-thumbs" {
		t.Errorf("expected thumbnail bucket 'test-thumbs', got %q", up.calledThumbnailsBucket)
	}
}

func TestPipeline_Process_DownloadFailure(t *testing.T) {
	tempDir := t.TempDir()
	cfg := &config.Config{TempDir: tempDir}

	dl := &mockDownloader{
		downloadFn: func(ctx context.Context, bucket, objectKey, destPath string) error {
			return errors.New("minio connection timeout")
		},
	}

	inspectorCalled := false
	insp := &mockInspector{
		inspectFn: func(ctx context.Context, filePath string) (*probe.ProbeResult, error) {
			inspectorCalled = true
			return nil, nil
		},
	}

	cb := &mockCallbackClient{}
	p := newTestPipeline(cfg, dl, insp, nil, nil, nil, cb, nil)

	job := pipeline.MediaJob{
		JobID:        "job-fail-dl",
		VideoID:      "video-fail-dl",
		SourceBucket: "uploads",
		SourceKey:    "raw/corrupted.mp4",
	}

	err := p.Process(context.Background(), job)
	if err == nil {
		t.Fatal("expected process to return error on download failure, got nil")
	}

	if inspectorCalled {
		t.Error("expected inspector NOT to be called after download failure")
	}

	payloads := cb.Payloads()
	if len(payloads) != 2 {
		t.Fatalf("expected 2 callbacks (probing, failed), got %d", len(payloads))
	}

	if payloads[0].Status != callback.StatusProbing {
		t.Errorf("expected status 'probing', got '%s'", payloads[0].Status)
	}
	if payloads[1].Status != callback.StatusFailed {
		t.Errorf("expected status 'failed', got '%s'", payloads[1].Status)
	}
	if payloads[1].Error == "" {
		t.Error("expected failure callback to include error string")
	}
}

func TestPipeline_Process_ProbeFailure_DecisivelyReportedReturnsNil(t *testing.T) {
	tempDir := t.TempDir()
	cfg := &config.Config{TempDir: tempDir}

	var downloadedPath string
	dl := &mockDownloader{
		downloadFn: func(ctx context.Context, bucket, objectKey, destPath string) error {
			downloadedPath = destPath
			return os.WriteFile(destPath, []byte("not-a-valid-video"), 0644)
		},
	}

	insp := &mockInspector{
		inspectFn: func(ctx context.Context, filePath string) (*probe.ProbeResult, error) {
			return nil, errors.New("ffprobe invalid data found")
		},
	}

	cb := &mockCallbackClient{}
	p := newTestPipeline(cfg, dl, insp, nil, nil, nil, cb, nil)

	job := pipeline.MediaJob{
		JobID:        "job-fail-probe",
		VideoID:      "video-fail-probe",
		SourceBucket: "uploads",
		SourceKey:    "raw/bad.mp4",
	}

	err := p.Process(context.Background(), job)
	if err != nil {
		t.Fatalf("expected nil when corrupt file was decisively reported, got %v", err)
	}

	// Verify temp file was cleaned up despite failure
	if _, err := os.Stat(downloadedPath); !os.IsNotExist(err) {
		t.Errorf("expected temp file %s to be cleaned up on failure", downloadedPath)
	}

	payloads := cb.Payloads()
	if len(payloads) != 2 {
		t.Fatalf("expected 2 callbacks (probing, failed), got %d", len(payloads))
	}
	if payloads[1].Status != callback.StatusFailed {
		t.Errorf("expected status 'failed', got '%s'", payloads[1].Status)
	}
}

func TestPipeline_Process_ProbeFailure_CallbackFailsReturnsError(t *testing.T) {
	tempDir := t.TempDir()
	cfg := &config.Config{TempDir: tempDir}

	insp := &mockInspector{
		inspectFn: func(ctx context.Context, filePath string) (*probe.ProbeResult, error) {
			return nil, errors.New("ffprobe failure")
		},
	}

	cb := &mockCallbackClient{
		statusErrMap: map[string]error{
			callback.StatusFailed: errors.New("network timeout reaching callback endpoint"),
		},
	}
	p := newTestPipeline(cfg, nil, insp, nil, nil, nil, cb, nil)

	job := pipeline.MediaJob{
		JobID:        "job-cb-fail",
		VideoID:      "vid-cb-fail",
		SourceBucket: "uploads",
		SourceKey:    "raw/bad.mp4",
	}

	err := p.Process(context.Background(), job)
	if err == nil {
		t.Fatal("expected error when callback notification fails, got nil")
	}
}

func TestPipeline_Process_NoVideoStream_DecisivelyReportedReturnsNil(t *testing.T) {
	tempDir := t.TempDir()
	cfg := &config.Config{TempDir: tempDir}

	insp := &mockInspector{
		inspectFn: func(ctx context.Context, filePath string) (*probe.ProbeResult, error) {
			return &probe.ProbeResult{
				DurationSeconds: 10,
				Video:           nil, // No video stream
			}, nil
		},
	}

	cb := &mockCallbackClient{}
	p := newTestPipeline(cfg, nil, insp, nil, nil, nil, cb, nil)

	job := pipeline.MediaJob{
		JobID:        "job-no-video",
		VideoID:      "vid-no-video",
		SourceBucket: "uploads",
		SourceKey:    "raw/audio_only.mp3",
	}

	err := p.Process(context.Background(), job)
	if err != nil {
		t.Fatalf("expected nil when no video stream is decisively reported, got %v", err)
	}

	payloads := cb.Payloads()
	if len(payloads) != 2 {
		t.Fatalf("expected 2 callbacks (probing, failed), got %d", len(payloads))
	}
	if payloads[1].Status != callback.StatusFailed {
		t.Errorf("expected status 'failed', got '%s'", payloads[1].Status)
	}
	if !strings.Contains(payloads[1].Error, "no video stream") {
		t.Errorf("expected error message to mention no video stream, got: %s", payloads[1].Error)
	}
}

func TestPipeline_Process_TranscodingCallback_NotFoundOrUnprocessable_DiscardsJobReturnsNil(t *testing.T) {
	for _, statusCode := range []int{404, 422} {
		tempDir := t.TempDir()
		cfg := &config.Config{TempDir: tempDir}

		transcoderCalled := false
		tr := &mockTranscoder{
			transcodeFn: func(ctx context.Context, sourcePath, outputDir string, profiles []transcoder.Profile, opts ...transcoder.TranscodeOption) error {
				transcoderCalled = true
				return nil
			},
		}

		cb := &mockCallbackClient{
			statusErrMap: map[string]error{
				callback.StatusTranscoding: &callback.ClientError{
					StatusCode: statusCode,
					Message:    fmt.Sprintf("HTTP %d Client Error", statusCode),
				},
			},
		}
		p := newTestPipeline(cfg, nil, nil, tr, nil, nil, cb, nil)

		job := pipeline.MediaJob{
			JobID:        "job-del-transcoding",
			VideoID:      "vid-del-transcoding",
			SourceBucket: "uploads",
			SourceKey:    "raw/video.mp4",
		}

		err := p.Process(context.Background(), job)
		if err != nil {
			t.Fatalf("expected nil on status code %d, got %v", statusCode, err)
		}

		if transcoderCalled {
			t.Errorf("expected transcoder NOT to be called when transcoding callback returns %d", statusCode)
		}
	}
}

func TestPipeline_Process_TranscodeFailure_DecisivelyReportedReturnsNil(t *testing.T) {
	tempDir := t.TempDir()
	cfg := &config.Config{TempDir: tempDir}

	tr := &mockTranscoder{
		transcodeFn: func(ctx context.Context, sourcePath, outputDir string, profiles []transcoder.Profile, opts ...transcoder.TranscodeOption) error {
			return errors.New("ffmpeg fatal transcode error: invalid frame format")
		},
	}

	cb := &mockCallbackClient{}
	p := newTestPipeline(cfg, nil, nil, tr, nil, nil, cb, nil)

	job := pipeline.MediaJob{
		JobID:        "job-fail-transcode",
		VideoID:      "vid-fail-transcode",
		SourceBucket: "uploads",
		SourceKey:    "raw/video.mp4",
	}

	err := p.Process(context.Background(), job)
	if err != nil {
		t.Fatalf("expected nil when transcode failure is decisively reported, got %v", err)
	}

	payloads := cb.Payloads()
	if len(payloads) != 3 {
		t.Fatalf("expected 3 callbacks (probing, transcoding, failed), got %d", len(payloads))
	}
	if payloads[2].Status != callback.StatusFailed {
		t.Errorf("expected status 'failed', got '%s'", payloads[2].Status)
	}
	if !strings.Contains(payloads[2].Error, "transcoding failed") {
		t.Errorf("expected error message to mention transcoding failed, got: %s", payloads[2].Error)
	}
}

func TestPipeline_Process_TranscodeFailure_CallbackFailsReturnsError(t *testing.T) {
	tempDir := t.TempDir()
	cfg := &config.Config{TempDir: tempDir}

	tr := &mockTranscoder{
		transcodeFn: func(ctx context.Context, sourcePath, outputDir string, profiles []transcoder.Profile, opts ...transcoder.TranscodeOption) error {
			return errors.New("ffmpeg fatal error")
		},
	}

	cb := &mockCallbackClient{
		statusErrMap: map[string]error{
			callback.StatusFailed: errors.New("failed to reach callback API"),
		},
	}
	p := newTestPipeline(cfg, nil, nil, tr, nil, nil, cb, nil)

	job := pipeline.MediaJob{
		JobID:        "job-tr-cb-fail",
		VideoID:      "vid-tr-cb-fail",
		SourceBucket: "uploads",
		SourceKey:    "raw/video.mp4",
	}

	err := p.Process(context.Background(), job)
	if err == nil {
		t.Fatal("expected error when failure callback fails, got nil")
	}
}

func TestPipeline_Process_ThumbnailFailure_DecisivelyReportedReturnsNil(t *testing.T) {
	tempDir := t.TempDir()
	cfg := &config.Config{TempDir: tempDir}

	th := &mockThumbnail{
		extractFn: func(ctx context.Context, sourcePath, destPath string, durationSeconds float64) error {
			return errors.New("ffmpeg thumbnail extraction failed: seek past EOF")
		},
	}

	cb := &mockCallbackClient{}
	p := newTestPipeline(cfg, nil, nil, nil, th, nil, cb, nil)

	job := pipeline.MediaJob{
		JobID:        "job-fail-thumb",
		VideoID:      "vid-fail-thumb",
		SourceBucket: "uploads",
		SourceKey:    "raw/video.mp4",
	}

	err := p.Process(context.Background(), job)
	if err != nil {
		t.Fatalf("expected nil when thumbnail failure is decisively reported, got %v", err)
	}

	payloads := cb.Payloads()
	if len(payloads) != 3 {
		t.Fatalf("expected 3 callbacks (probing, transcoding, failed), got %d", len(payloads))
	}
	if payloads[2].Status != callback.StatusFailed {
		t.Errorf("expected status 'failed', got '%s'", payloads[2].Status)
	}
	if !strings.Contains(payloads[2].Error, "thumbnail extraction failed") {
		t.Errorf("expected error message to mention thumbnail extraction failed, got: %s", payloads[2].Error)
	}
}

func TestPipeline_Process_ThumbnailFailure_CallbackFailsReturnsError(t *testing.T) {
	tempDir := t.TempDir()
	cfg := &config.Config{TempDir: tempDir}

	th := &mockThumbnail{
		extractFn: func(ctx context.Context, sourcePath, destPath string, durationSeconds float64) error {
			return errors.New("thumbnail error")
		},
	}

	cb := &mockCallbackClient{
		statusErrMap: map[string]error{
			callback.StatusFailed: errors.New("network error"),
		},
	}
	p := newTestPipeline(cfg, nil, nil, nil, th, nil, cb, nil)

	job := pipeline.MediaJob{
		JobID:        "job-th-cb-fail",
		VideoID:      "vid-th-cb-fail",
		SourceBucket: "uploads",
		SourceKey:    "raw/video.mp4",
	}

	err := p.Process(context.Background(), job)
	if err == nil {
		t.Fatal("expected error when thumbnail failure callback fails, got nil")
	}
}

func TestPipeline_Process_UploadFailure_ReturnsError(t *testing.T) {
	tempDir := t.TempDir()
	cfg := &config.Config{TempDir: tempDir}

	up := &mockUploader{
		uploadFn: func(ctx context.Context, hlsDir, thumbnailPath, tenantID, videoID, processedBucket, thumbnailBucket string) ([]storage.AssetInfo, error) {
			return nil, errors.New("minio S3 service unavailable")
		},
	}

	cb := &mockCallbackClient{}
	p := newTestPipeline(cfg, nil, nil, nil, nil, up, cb, nil)

	job := pipeline.MediaJob{
		JobID:        "job-fail-upload",
		VideoID:      "vid-fail-upload",
		SourceBucket: "uploads",
		SourceKey:    "raw/video.mp4",
	}

	err := p.Process(context.Background(), job)
	if err == nil {
		t.Fatal("expected error on upload failure, got nil")
	}

	payloads := cb.Payloads()
	if len(payloads) != 3 {
		t.Fatalf("expected 3 callbacks (probing, transcoding, failed), got %d", len(payloads))
	}
	if payloads[2].Status != callback.StatusFailed {
		t.Errorf("expected status 'failed', got '%s'", payloads[2].Status)
	}
}

func TestPipeline_Process_UploadFailure_NotFoundOrUnprocessable_DiscardsJobReturnsNil(t *testing.T) {
	for _, statusCode := range []int{404, 422} {
		tempDir := t.TempDir()
		cfg := &config.Config{TempDir: tempDir}

		up := &mockUploader{
			uploadFn: func(ctx context.Context, hlsDir, thumbnailPath, tenantID, videoID, processedBucket, thumbnailBucket string) ([]storage.AssetInfo, error) {
				return nil, errors.New("storage network timeout")
			},
		}

		cb := &mockCallbackClient{
			statusErrMap: map[string]error{
				callback.StatusFailed: &callback.ClientError{
					StatusCode: statusCode,
					Message:    fmt.Sprintf("HTTP %d Client Error", statusCode),
				},
			},
		}
		p := newTestPipeline(cfg, nil, nil, nil, nil, up, cb, nil)

		job := pipeline.MediaJob{
			JobID:        "job-del-upload-fail",
			VideoID:      "vid-del-upload-fail",
			SourceBucket: "uploads",
			SourceKey:    "raw/video.mp4",
		}

		err := p.Process(context.Background(), job)
		if err != nil {
			t.Fatalf("expected nil when failure callback returns %d, got %v", statusCode, err)
		}
	}
}

func TestPipeline_Process_ReadyCallback_NotFoundOrUnprocessable_DiscardsJobReturnsNil(t *testing.T) {
	for _, statusCode := range []int{404, 422} {
		tempDir := t.TempDir()
		cfg := &config.Config{TempDir: tempDir}

		cb := &mockCallbackClient{
			statusErrMap: map[string]error{
				callback.StatusReady: &callback.ClientError{
					StatusCode: statusCode,
					Message:    fmt.Sprintf("HTTP %d Client Error", statusCode),
				},
			},
		}
		p := newTestPipeline(cfg, nil, nil, nil, nil, nil, cb, nil)

		job := pipeline.MediaJob{
			JobID:        "job-del-ready",
			VideoID:      "vid-del-ready",
			SourceBucket: "uploads",
			SourceKey:    "raw/video.mp4",
		}

		err := p.Process(context.Background(), job)
		if err != nil {
			t.Fatalf("expected nil when ready returns %d, got %v", statusCode, err)
		}
	}
}

func TestPipeline_Process_ReadyCallback_ErrorReturnsError(t *testing.T) {
	tempDir := t.TempDir()
	cfg := &config.Config{TempDir: tempDir}

	cb := &mockCallbackClient{
		statusErrMap: map[string]error{
			callback.StatusReady: errors.New("500 internal server error"),
		},
	}
	p := newTestPipeline(cfg, nil, nil, nil, nil, nil, cb, nil)

	job := pipeline.MediaJob{
		JobID:        "job-ready-fail",
		VideoID:      "vid-ready-fail",
		SourceBucket: "uploads",
		SourceKey:    "raw/video.mp4",
	}

	err := p.Process(context.Background(), job)
	if err == nil {
		t.Fatal("expected error when ready callback fails, got nil")
	}
}

func TestPipeline_Process_NoAudioStream_TranscodeHandledWithoutAudio(t *testing.T) {
	tempDir := t.TempDir()
	cfg := &config.Config{TempDir: tempDir}

	insp := &mockInspector{
		inspectFn: func(ctx context.Context, filePath string) (*probe.ProbeResult, error) {
			return &probe.ProbeResult{
				DurationSeconds: 60,
				Format:          "mp4",
				SizeBytes:       5120,
				Video: &probe.VideoStreamInfo{
					Codec:  "h264",
					Width:  1280,
					Height: 720,
				},
				Audio: nil, // No audio stream
				Metadata: map[string]any{
					"duration": 60,
				},
			}, nil
		},
	}

	var capturedOpts []transcoder.TranscodeOption
	tr := &mockTranscoder{
		transcodeFn: func(ctx context.Context, sourcePath, outputDir string, profiles []transcoder.Profile, opts ...transcoder.TranscodeOption) error {
			capturedOpts = opts
			_ = os.MkdirAll(outputDir, 0755)
			_ = os.WriteFile(filepath.Join(outputDir, "master.m3u8"), []byte("#EXTM3U"), 0644)
			return nil
		},
	}

	cb := &mockCallbackClient{}
	p := newTestPipeline(cfg, nil, insp, tr, nil, nil, cb, nil)

	job := pipeline.MediaJob{
		JobID:        "job-no-audio",
		VideoID:      "vid-no-audio",
		SourceBucket: "uploads",
		SourceKey:    "raw/silent.mp4",
	}

	err := p.Process(context.Background(), job)
	if err != nil {
		t.Fatalf("expected success for no-audio video, got %v", err)
	}

	// Verify WithAudio(false) was passed
	tcfg := transcoder.TranscodeConfig{HasAudio: true}
	for _, opt := range capturedOpts {
		opt(&tcfg)
	}
	if tcfg.HasAudio {
		t.Errorf("expected TranscodeHLS to be called with HasAudio=false")
	}
}

func TestPipeline_Process_ProbingCallback_NotFoundOrUnprocessable_DiscardsJobReturnsNil(t *testing.T) {
	for _, statusCode := range []int{404, 422} {
		tempDir := t.TempDir()
		cfg := &config.Config{TempDir: tempDir}

		storageCalled := false
		dl := &mockDownloader{
			downloadFn: func(ctx context.Context, bucket, objectKey, destPath string) error {
				storageCalled = true
				return nil
			},
		}

		cb := &mockCallbackClient{
			statusErrMap: map[string]error{
				callback.StatusProbing: &callback.ClientError{
					StatusCode: statusCode,
					Message:    fmt.Sprintf("HTTP %d Client Error", statusCode),
				},
			},
		}
		p := newTestPipeline(cfg, dl, nil, nil, nil, nil, cb, nil)

		job := pipeline.MediaJob{
			JobID:        "job-del",
			VideoID:      "vid-del",
			SourceBucket: "uploads",
			SourceKey:    "raw/video.mp4",
		}

		err := p.Process(context.Background(), job)
		if err != nil {
			t.Fatalf("expected nil on status code %d, got %v", statusCode, err)
		}

		if storageCalled {
			t.Errorf("expected storage download NOT to be attempted when probing returns %d", statusCode)
		}

		// Must not attempt to send failure callback
		for _, pl := range cb.Payloads() {
			if pl.Status == callback.StatusFailed {
				t.Errorf("expected status 'failed' NOT to be sent when video is missing (%d)", statusCode)
			}
		}
	}
}

func TestPipeline_Process_ErrorTruncation(t *testing.T) {
	tempDir := t.TempDir()
	cfg := &config.Config{TempDir: tempDir}

	longErrorMessage := strings.Repeat("A", 3000)
	dl := &mockDownloader{
		downloadFn: func(ctx context.Context, bucket, objectKey, destPath string) error {
			return errors.New(longErrorMessage)
		},
	}

	cb := &mockCallbackClient{}
	p := newTestPipeline(cfg, dl, nil, nil, nil, nil, cb, nil)

	job := pipeline.MediaJob{
		JobID:        "job-long-err",
		VideoID:      "vid-long-err",
		SourceBucket: "uploads",
		SourceKey:    "raw/corrupted.mp4",
	}

	_ = p.Process(context.Background(), job)

	payloads := cb.Payloads()
	if len(payloads) < 2 {
		t.Fatalf("expected at least 2 callbacks, got %d", len(payloads))
	}

	failedPayload := payloads[len(payloads)-1]
	if len(failedPayload.Error) > 2000 {
		t.Errorf("expected error string to be <= 2000 characters, got %d", len(failedPayload.Error))
	}
	if !strings.HasSuffix(failedPayload.Error, " ... [truncated]") {
		t.Errorf("expected truncated error to end with ' ... [truncated]', got '%s'", failedPayload.Error[len(failedPayload.Error)-20:])
	}
}

func TestPipeline_Process_ErrorTruncation_UTF8Runes(t *testing.T) {
	tempDir := t.TempDir()
	cfg := &config.Config{TempDir: tempDir}

	multiByteErr := strings.Repeat("世界", 1250)
	dl := &mockDownloader{
		downloadFn: func(ctx context.Context, bucket, objectKey, destPath string) error {
			return errors.New(multiByteErr)
		},
	}

	cb := &mockCallbackClient{}
	p := newTestPipeline(cfg, dl, nil, nil, nil, nil, cb, nil)

	job := pipeline.MediaJob{
		JobID:        "job-utf8",
		VideoID:      "vid-utf8",
		SourceBucket: "uploads",
		SourceKey:    "raw/video.mp4",
	}

	_ = p.Process(context.Background(), job)

	payloads := cb.Payloads()
	if len(payloads) < 2 {
		t.Fatalf("expected at least 2 callbacks, got %d", len(payloads))
	}

	failedPayload := payloads[len(payloads)-1]
	runes := []rune(failedPayload.Error)
	expectedRuneCount := 1950 + len([]rune(" ... [truncated]"))
	if len(runes) != expectedRuneCount {
		t.Errorf("expected %d runes, got %d", expectedRuneCount, len(runes))
	}
	if !strings.HasSuffix(failedPayload.Error, " ... [truncated]") {
		t.Errorf("expected suffix ' ... [truncated]'")
	}
}

func TestMediaJob_Validate(t *testing.T) {
	tests := []struct {
		name    string
		job     pipeline.MediaJob
		wantErr bool
	}{
		{
			name: "valid job",
			job: pipeline.MediaJob{
				JobID:        "j-1",
				VideoID:      "v-1",
				SourceBucket: "b-1",
				SourceKey:    "k-1",
			},
			wantErr: false,
		},
		{
			name: "missing job_id",
			job: pipeline.MediaJob{
				VideoID:      "v-1",
				SourceBucket: "b-1",
				SourceKey:    "k-1",
			},
			wantErr: true,
		},
		{
			name: "missing video_id",
			job: pipeline.MediaJob{
				JobID:        "j-1",
				SourceBucket: "b-1",
				SourceKey:    "k-1",
			},
			wantErr: true,
		},
		{
			name: "missing source_bucket",
			job: pipeline.MediaJob{
				JobID:     "j-1",
				VideoID:   "v-1",
				SourceKey: "k-1",
			},
			wantErr: true,
		},
		{
			name: "missing source_key",
			job: pipeline.MediaJob{
				JobID:        "j-1",
				VideoID:      "v-1",
				SourceBucket: "b-1",
			},
			wantErr: true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			err := tt.job.Validate()
			if (err != nil) != tt.wantErr {
				t.Errorf("Validate() error = %v, wantErr = %v", err, tt.wantErr)
			}
		})
	}
}
