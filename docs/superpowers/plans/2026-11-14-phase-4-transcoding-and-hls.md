# Phase 4: Transcoding and HLS Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Transform probed raw media uploads into adaptive bitrate (ABR) streamable HLS video assets (master playlist, multi-resolution variant playlists, 4-second `.ts` segments, and poster thumbnail), sync all assets to MinIO object storage, and register them in Laravel to mark videos `READY`.

**Architecture:** The Go media worker (`worker/`) consumes processing jobs from RabbitMQ (`media.jobs`). After probing the source video dimensions and duration (Phase 3), the worker selects an adaptive rendition ladder (up to 1080p, 720p, 480p, 360p without upscaling), executes a single-pass multi-variant FFmpeg command to transcode H.264/AAC media into HLS segments and playlists, extracts a JPEG poster thumbnail, uploads all generated assets in parallel to MinIO (`videos-processed` and `thumbnails` buckets), and notifies Laravel via an authenticated internal callback (`POST /api/v1/internal/processing/callback`) to persist `VideoAsset` records, set `thumbnail_path`, and transition the video to `READY`.

**Tech Stack:** Go 1.23, FFmpeg 6+ (in Alpine Docker), `github.com/minio/minio-go/v7`, `github.com/rabbitmq/amqp091-go`, Laravel 13, PHP 8.4, PostgreSQL 16, MinIO S3, Docker Compose.

**Spec:** `docs/architecture.md` and Brainstorming Design Document approved on 2026-11-14.

## Global Constraints

- **PHP Standards:** `declare(strict_types=1);` and `final class` on all new PHP classes, enforced by Laravel Pint.
- **Go Standards:** Idiomatic Go 1.23+, standard directory layout (`internal/`), structured logging with `log/slog`, strict error handling with context cancellation.
- **No Video Upscaling:** If source height is 720px, renditions are limited to 720p, 480p, and 360p. Never upscale a lower-resolution source to a higher profile.
- **H.264 Macroblock Alignment:** All scaled output dimensions must be divisible by 2 (e.g., using `scale=trunc(oh*a/2)*2:trunc(ow/a/2)*2` or `scale='min(w,iw)':-2`) to prevent FFmpeg encoding errors.
- **Atomic Asset Cleanup:** All temporary transcoding files on worker disk must be reliably purged upon completion or failure.
- **Tenancy Boundary:** All storage paths in MinIO must follow the tenant isolation structure: `tenants/{tenant_id}/videos/{video_id}/hls/...` and `tenants/{tenant_id}/videos/{video_id}/thumbnail.jpg`.

## Review Focus

1. **Non-Standard or Odd Video Dimensions:** Non-standard aspect ratios (vertical 9:16 mobile video, 21:9 ultrawide, or odd-pixel dimensions like 1921x1079) must scale cleanly without FFmpeg macroblock errors (width and height must be strictly even numbers).
2. **Low-Resolution Source Videos:** A source video with low resolution (e.g. 480p or 360p) must only generate renditions at or below its resolution, never generating empty or upscaled playlists.
3. **Mid-Upload Network Failure to MinIO:** If network disconnection or MinIO timeout occurs while uploading HLS segments (e.g. at segment 45 of 100), the worker must retry, fail gracefully if unrecoverable, clean up temp files, and NOT mark the video `READY` with incomplete media in Laravel.
4. **Short-Duration Videos (<2s):** Videos shorter than standard thumbnail offsets (e.g. 0.8s or 1.5s) must have thumbnail extraction timestamps safely clamped to prevent FFmpeg seeking beyond EOF.
5. **Corrupt Video Stream Abort:** A video that passes FFprobe header inspection but contains corrupted frames mid-stream causing FFmpeg transcode failure must be caught, reported as `status: failed` with a sanitized diagnostic message, and acknowledged/discarded without infinite worker crash loops.

---

### Task 1: Rendition Ladder & Resolution Calculator in Go

**Files:**
- Create: `worker/internal/transcoder/profile.go`
- Create: `worker/internal/transcoder/ladder.go`
- Create: `worker/internal/transcoder/ladder_test.go`

**Interfaces:**
- Consumes: `probe.ProbeResult` (`Video.Width`, `Video.Height`, `DurationSeconds`).
- Produces: `transcoder.SelectLadder(sourceWidth, sourceHeight int) []Profile` and `Profile.FilterString(streamIndex int) string`.
- Each `Profile` contains: `Name` (e.g. "1080p", "720p"), `Width int`, `Height int`, `VideoBitrate string`, `MaxRate string`, `BufSize string`, `AudioBitrate string`, `ProfileLevel string`.

- [ ] **Step 1: Write unit tests for rendition ladder selection and dimension calculation**

Create `worker/internal/transcoder/ladder_test.go`:
```go
package transcoder_test

import (
	"testing"
	"wiz-media-worker/internal/transcoder"
)

func TestSelectLadder_1080pSource(t *testing.T) {
	ladder := transcoder.SelectLadder(1920, 1080)
	if len(ladder) != 4 {
		t.Fatalf("expected 4 profiles for 1080p source, got %d", len(ladder))
	}

	expected := []string{"1080p", "720p", "480p", "360p"}
	for i, name := range expected {
		if ladder[i].Name != name {
			t.Errorf("profile %d: expected %s, got %s", i, name, ladder[i].Name)
		}
	}
}

func TestSelectLadder_720pSource_NoUpscaling(t *testing.T) {
	ladder := transcoder.SelectLadder(1280, 720)
	if len(ladder) != 3 {
		t.Fatalf("expected 3 profiles for 720p source, got %d", len(ladder))
	}
	expected := []string{"720p", "480p", "360p"}
	for i, name := range expected {
		if ladder[i].Name != name {
			t.Errorf("profile %d: expected %s, got %s", i, name, ladder[i].Name)
		}
	}
}

func TestSelectLadder_480pSource_NoUpscaling(t *testing.T) {
	ladder := transcoder.SelectLadder(854, 480)
	if len(ladder) != 2 {
		t.Fatalf("expected 2 profiles for 480p source, got %d", len(ladder))
	}
	expected := []string{"480p", "360p"}
	for i, name := range expected {
		if ladder[i].Name != name {
			t.Errorf("profile %d: expected %s, got %s", i, name, ladder[i].Name)
		}
	}
}

func TestSelectLadder_VerticalVideo_1080x1920(t *testing.T) {
	// Vertical 9:16 mobile video: height is 1920, width is 1080
	ladder := transcoder.SelectLadder(1080, 1920)
	if len(ladder) != 4 {
		t.Fatalf("expected 4 profiles for 1080x1920 vertical video, got %d", len(ladder))
	}
	// Height drives profile tier: 1920 -> 1080p tier, scaling preserves aspect ratio
	if ladder[0].Width%2 != 0 || ladder[0].Height%2 != 0 {
		t.Errorf("expected even dimensions for macroblocks, got %dx%d", ladder[0].Width, ladder[0].Height)
	}
}

func TestCalculateDimensions_EvenNumbersGuaranteed(t *testing.T) {
	// Odd input dimensions (e.g. 1921x1079)
	w, h := transcoder.CalculateDimensions(1921, 1079, 720)
	if w%2 != 0 || h%2 != 0 {
		t.Errorf("dimensions must be even, got %dx%d", w, h)
	}
}
```

- [ ] **Step 2: Run test using Docker builder container to verify it fails**

Run:
```bash
docker run --rm -v "D:\Playground\wiz-video-platform\worker:/app" -w /app golang:1.23-alpine go test ./internal/transcoder/...
```
Expected: FAIL with package not defined.

- [ ] **Step 3: Implement Profile and Ladder calculation**

1. Create `worker/internal/transcoder/profile.go`:
   - Define `Profile` struct: `Name`, `Width`, `Height`, `VideoBitrate`, `MaxRate`, `BufSize`, `AudioBitrate`, `ProfileLevel`.
   - Predefined profile tiers:
     - 1080p: Target height 1080, video 4500k, max 4800k, buf 9000k, audio 192k, profile `high`
     - 720p: Target height 720, video 2500k, max 2800k, buf 5000k, audio 128k, profile `high`
     - 480p: Target height 480, video 1000k, max 1200k, buf 2000k, audio 96k, profile `main`
     - 360p: Target height 360, video 600k, max 700k, buf 1200k, audio 64k, profile `main`
2. Create `worker/internal/transcoder/ladder.go`:
   - `SelectLadder(srcWidth, srcHeight int) []Profile`:
     - Determine dominant dimension (usually min(srcWidth, srcHeight) for aspect ratio or srcHeight).
     - Filter out profiles exceeding source resolution.
     - Always include 360p tier as baseline.
   - `CalculateDimensions(srcWidth, srcHeight, targetHeight int) (int, int)`:
     - Maintains aspect ratio.
     - Ensures `(w / 2) * 2` and `(h / 2) * 2` to guarantee even integers for H.264 macroblock alignment.

- [ ] **Step 4: Run test to verify it passes**

Run:
```bash
docker run --rm -v "D:\Playground\wiz-video-platform\worker:/app" -w /app golang:1.23-alpine go test -v ./internal/transcoder/...
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add worker/internal/transcoder/
git commit -m "feat(transcoder): implement rendition ladder and even dimension calculation"
```

---

### Task 2: Single-Pass Multi-Variant FFmpeg Transcoder in Go

**Files:**
- Create: `worker/internal/transcoder/command.go`
- Create: `worker/internal/transcoder/ffmpeg.go`
- Create: `worker/internal/transcoder/command_test.go`
- Create: `worker/internal/transcoder/ffmpeg_test.go`

**Interfaces:**
- Consumes: Source video file path, output directory path, list of `Profile`.
- Produces: `BuildFFmpegArgs(sourcePath, outputDir string, profiles []Profile) []string` and `TranscodeHLS(ctx context.Context, sourcePath, outputDir string, profiles []Profile) error`.
- Generated files in outputDir: `master.m3u8`, `stream_%v.m3u8`, `stream_%v_%03d.ts`.

- [ ] **Step 1: Write test for FFmpeg command builder**

Create `worker/internal/transcoder/command_test.go`:
```go
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
}
```

- [ ] **Step 2: Run test using Docker builder container to verify it fails**

Run:
```bash
docker run --rm -v "D:\Playground\wiz-video-platform\worker:/app" -w /app golang:1.23-alpine go test ./internal/transcoder/...
```
Expected: FAIL with `BuildFFmpegArgs` undefined.

- [ ] **Step 3: Implement FFmpeg command builder and transcode execution**

1. Create `worker/internal/transcoder/command.go`:
   - Builds dynamic `-filter_complex` string:
     `[0:v]split=N[v1][v2]...;[v1]scale=W1:H1[v1out];[v2]scale=W2:H2[v2out]...`
   - Configures per-rendition encoding flags:
     `-map [vXout] -c:v:X libx264 -b:v:X ... -maxrate:v:X ... -bufsize:v:X ... -profile:v:X ...`
     `-map a:0? -c:a:X aac -b:a:X ...`
   - Configures HLS muxer flags:
     `-f hls -hls_time 4 -hls_playlist_type vod -hls_flags independent_segments -master_pl_name master.m3u8`
     `-var_stream_map "v:0,a:0 v:1,a:1 ..."`
     `-hls_segment_filename /outputDir/stream_%v_%03d.ts`
     `/outputDir/stream_%v.m3u8`
2. Create `worker/internal/transcoder/ffmpeg.go`:
   - `Transcoder` struct with `binPath` (default `"ffmpeg"`).
   - `TranscodeHLS(ctx context.Context, sourcePath, outputDir string, profiles []Profile) error`:
     - Creates output directory if not exists.
     - Runs `exec.CommandContext(ctx, binPath, args...)`.
     - Captures stderr; on failure, formats and returns descriptive error message with stderr tail.

- [ ] **Step 4: Run unit tests to verify they pass**

Run:
```bash
docker run --rm -v "D:\Playground\wiz-video-platform\worker:/app" -w /app golang:1.23-alpine go test -v ./internal/transcoder/...
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add worker/internal/transcoder/
git commit -m "feat(transcoder): implement single-pass multi-variant ffmpeg hls command builder and runner"
```

---

### Task 3: Thumbnail Poster Frame Extractor in Go

**Files:**
- Create: `worker/internal/thumbnail/generator.go`
- Create: `worker/internal/thumbnail/generator_test.go`

**Interfaces:**
- Consumes: Source video path, destination JPEG path, video duration seconds (`float64` or `int`).
- Produces: `thumbnail.ExtractFrame(ctx context.Context, sourcePath, destPath string, durationSeconds float64) error`.

- [ ] **Step 1: Write test for Thumbnail timestamp clamping and extraction**

Create `worker/internal/thumbnail/generator_test.go`:
```go
package thumbnail_test

import (
	"context"
	"testing"
	"wiz-media-worker/internal/thumbnail"
)

func TestCalculateTimestamp_Clamping(t *testing.T) {
	// Standard video: 100 seconds -> 10% is 10s (capped at 5s)
	ts1 := thumbnail.CalculateTimestamp(100.0)
	if ts1 < 1.0 || ts1 > 5.0 {
		t.Errorf("expected timestamp between 1s and 5s for 100s video, got %f", ts1)
	}

	// Short video: 1.5 seconds -> clamped safely before EOF (e.g. 0.5s)
	ts2 := thumbnail.CalculateTimestamp(1.5)
	if ts2 <= 0.0 || ts2 >= 1.5 {
		t.Errorf("timestamp must be strictly between 0 and 1.5s for 1.5s video, got %f", ts2)
	}

	// Very short video: 0.5 seconds
	ts3 := thumbnail.CalculateTimestamp(0.5)
	if ts3 <= 0.0 || ts3 >= 0.5 {
		t.Errorf("timestamp must be strictly between 0 and 0.5s for 0.5s video, got %f", ts3)
	}
}

func TestExtractFrame_MissingFileReturnsError(t *testing.T) {
	err := thumbnail.ExtractFrame(context.Background(), "/nonexistent/video.mp4", "/tmp/thumb.jpg", 10.0)
	if err == nil {
		t.Fatalf("expected error on nonexistent source, got nil")
	}
}
```

- [ ] **Step 2: Run test using Docker builder container to verify it fails**

Run:
```bash
docker run --rm -v "D:\Playground\wiz-video-platform\worker:/app" -w /app golang:1.23-alpine go test ./internal/thumbnail/...
```
Expected: FAIL with package not found.

- [ ] **Step 3: Implement Thumbnail Generator**

Create `worker/internal/thumbnail/generator.go`:
- `CalculateTimestamp(durationSeconds float64) float64`:
  - If `durationSeconds <= 1.0`, return `durationSeconds * 0.5`.
  - Otherwise, return `math.Min(5.0, math.Max(1.0, durationSeconds * 0.1))`.
- `ExtractFrame(ctx context.Context, sourcePath, destPath string, durationSeconds float64) error`:
  - Timestamp string formatted as `fmt.Sprintf("%.3f", ts)`.
  - Executes `ffmpeg -ss <ts> -i <sourcePath> -vframes 1 -q:v 2 -y <destPath>`.
  - Captures error output and returns descriptive error if extraction fails.

- [ ] **Step 4: Run test to verify it passes**

Run:
```bash
docker run --rm -v "D:\Playground\wiz-video-platform\worker:/app" -w /app golang:1.23-alpine go test -v ./internal/thumbnail/...
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add worker/internal/thumbnail/
git commit -m "feat(thumbnail): implement video poster frame extractor with timestamp clamping"
```

---

### Task 4: Parallel MinIO Asset Uploader in Go

**Files:**
- Create: `worker/internal/storage/uploader.go`
- Create: `worker/internal/storage/uploader_test.go`
- Modify: `worker/internal/storage/minio.go`

**Interfaces:**
- Consumes: Local output directory with HLS files, thumbnail file, tenant ID, video ID, MinIO client.
- Produces: `UploadHLSAssets(ctx context.Context, hlsDir, thumbnailPath, tenantID, videoID string) ([]AssetInfo, error)`.
- `AssetInfo` contains: `Type` (master, hls_playlist, hls_segment, thumbnail), `Disk`, `ObjectKey`, `SizeBytes`, `MimeType`, `Metadata`.

- [ ] **Step 1: Write test for HLS asset discovery and MIME type detection**

Create `worker/internal/storage/uploader_test.go`:
```go
package storage_test

import (
	"os"
	"path/filepath"
	"testing"
	"wiz-media-worker/internal/storage"
)

func TestDetectMimeType(t *testing.T) {
	tests := []struct {
		filename string
		expected string
	}{
		{"master.m3u8", "application/x-mpegURL"},
		{"stream_0.m3u8", "application/x-mpegURL"},
		{"stream_0_000.ts", "video/MP2T"},
		{"thumbnail.jpg", "image/jpeg"},
		{"unknown.bin", "application/octet-stream"},
	}

	for _, tt := range tests {
		actual := storage.DetectMimeType(tt.filename)
		if actual != tt.expected {
			t.Errorf("for %s: expected %s, got %s", tt.filename, tt.expected, actual)
		}
	}
}

func TestDiscoverFiles(t *testing.T) {
	tempDir, err := os.MkdirTemp("", "hls_discover_test")
	if err != nil {
		t.Fatal(err)
	}
	defer os.RemoveAll(tempDir)

	_ = os.WriteFile(filepath.Join(tempDir, "master.m3u8"), []byte("#EXTM3U"), 0644)
	_ = os.WriteFile(filepath.Join(tempDir, "stream_0.m3u8"), []byte("#EXTM3U"), 0644)
	_ = os.WriteFile(filepath.Join(tempDir, "stream_0_000.ts"), []byte("dummy ts"), 0644)

	files, err := storage.DiscoverHLSFiles(tempDir)
	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}

	if len(files) != 3 {
		t.Fatalf("expected 3 files, got %d", len(files))
	}
}
```

- [ ] **Step 2: Run test using Docker builder container to verify it fails**

Run:
```bash
docker run --rm -v "D:\Playground\wiz-video-platform\worker:/app" -w /app golang:1.23-alpine go test ./internal/storage/...
```
Expected: FAIL with functions undefined.

- [ ] **Step 3: Implement MinIO Parallel Uploader**

1. In `worker/internal/storage/uploader.go`:
   - `AssetInfo` struct: `Type`, `Disk`, `ObjectKey`, `SizeBytes`, `MimeType`, `Metadata map[string]any`.
   - `DetectMimeType(filename string) string`:
     - `.m3u8` &rarr; `application/x-mpegURL`
     - `.ts` &rarr; `video/MP2T`
     - `.jpg` / `.jpeg` &rarr; `image/jpeg`
     - fallback &rarr; `application/octet-stream`
   - `DiscoverHLSFiles(dir string) ([]string, error)`: walks directory collecting all files.
   - `UploadHLSAssets(ctx context.Context, hlsDir, thumbnailPath, tenantID, videoID, processedBucket, thumbnailBucket string) ([]AssetInfo, error)`:
     - Spawns worker pool with concurrency = 5.
     - Playlists & segments uploaded to `processedBucket` under `tenants/{tenantID}/videos/{videoID}/hls/{relPath}`.
     - Classifies asset types:
       - `master.m3u8` &rarr; `"master"`
       - `stream_*.m3u8` &rarr; `"hls_playlist"`
       - `*.ts` &rarr; `"hls_segment"`
     - Uploads thumbnail to `thumbnailBucket` under `tenants/{tenantID}/videos/{videoID}/thumbnail.jpg` (type: `"thumbnail"`).
     - Returns populated slice of `AssetInfo`.

- [ ] **Step 4: Run test to verify it passes**

Run:
```bash
docker run --rm -v "D:\Playground\wiz-video-platform\worker:/app" -w /app golang:1.23-alpine go test -v ./internal/storage/...
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add worker/internal/storage/
git commit -m "feat(storage): implement parallel minio uploader for hls assets and thumbnails"
```

---

### Task 5: Pipeline Orchestration & End-to-End Transcoding in Go Worker

**Files:**
- Modify: `worker/internal/callback/client.go`
- Modify: `worker/internal/pipeline/pipeline.go`
- Modify: `worker/internal/pipeline/pipeline_test.go`
- Modify: `worker/internal/config/config.go`
- Modify: `worker/cmd/worker/main.go`

**Interfaces:**
- Consumes: RabbitMQ `MediaJob`, `probe.Inspector`, `transcoder.TranscodeHLS`, `thumbnail.ExtractFrame`, `storage.UploadHLSAssets`, `callback.Client`.
- Produces: Integrated worker pipeline completing:
  `probe` &rarr; `status: transcoding` &rarr; `transcode` &rarr; `thumbnail` &rarr; `upload` &rarr; `status: ready` with assets &rarr; cleanup.

- [ ] **Step 1: Extend Callback Payload with Assets and ThumbnailKey**

In `worker/internal/callback/client.go`:
```go
type AssetPayload struct {
	Type      string         `json:"type"`
	Disk      string         `json:"disk"`
	ObjectKey string         `json:"object_key"`
	SizeBytes int64          `json:"size_bytes"`
	MimeType  string         `json:"mime_type"`
	Metadata  map[string]any `json:"metadata,omitempty"`
}

type Payload struct {
	JobID           string         `json:"job_id"`
	VideoID         string         `json:"video_id"`
	Status          string         `json:"status"`
	DurationSeconds *int           `json:"duration_seconds,omitempty"`
	ThumbnailKey    string         `json:"thumbnail_key,omitempty"`
	Assets          []AssetPayload `json:"assets,omitempty"`
	Metadata        map[string]any `json:"metadata,omitempty"`
	Error           string         `json:"error,omitempty"`
}
```

- [ ] **Step 2: Add Config variables for processed and thumbnail buckets**

In `worker/internal/config/config.go`:
- Add `MinioProcessedBucket string` (default: `"videos-processed"`).
- Add `MinioThumbnailsBucket string` (default: `"thumbnails"`).

- [ ] **Step 3: Update Pipeline to Execute Full Transcode & Packaging Workflow**

In `worker/internal/pipeline/pipeline.go`:
1. Step 1: Notify Laravel `status: probing`.
2. Step 2: Download original video from MinIO.
3. Step 3: Run FFprobe inspector.
4. Step 4: Notify Laravel `status: transcoding` with probed metadata and duration.
5. Step 5: Select rendition ladder based on probed dimensions (`transcoder.SelectLadder`).
6. Step 6: Execute single-pass FFmpeg HLS transcode into temp directory `hlsDir`.
7. Step 7: Extract poster thumbnail into temp file `thumbPath`.
8. Step 8: Concurrently upload all HLS files and thumbnail to MinIO.
9. Step 9: Notify Laravel `status: ready` with `thumbnail_key` and `assets` array.
10. Step 10: Cleanup all temporary directories/files in `defer`.

- [ ] **Step 4: Update pipeline unit tests and verify in Docker**

Run:
```bash
docker run --rm -v "D:\Playground\wiz-video-platform\worker:/app" -w /app golang:1.23-alpine go test -v ./internal/pipeline/...
```
Expected: PASS.

- [ ] **Step 5: Verify main.go builds cleanly**

Run:
```bash
docker run --rm -v "D:\Playground\wiz-video-platform\worker:/app" -w /app golang:1.23-alpine go build -o /dev/null ./cmd/worker
```
Expected: PASS (exit 0).

- [ ] **Step 6: Commit**

```bash
git add worker/
git commit -m "feat(worker): integrate full hls transcoding, thumbnail, and asset upload pipeline"
```

---

### Task 6: Laravel Callback API Extension for Ready Status & Asset Registration

**Files:**
- Modify: `panel/app/Http/Requests/Internal/ProcessingCallbackRequest.php`
- Create: `panel/app/Domain/Videos/Services/VideoAssetRegistrationService.php`
- Create: `panel/app/Events/Videos/VideoStatusUpdated.php`
- Modify: `panel/app/Http/Controllers/Api/Internal/ProcessingCallbackController.php`
- Modify: `panel/tests/Feature/Api/Internal/ProcessingCallbackTest.php`

**Interfaces:**
- Consumes: Internal callback payload with `status: ready`, `thumbnail_key`, `assets: [...]`.
- Produces: Atomically registers records in `video_assets`, updates `videos.status = 'ready'`, sets `videos.thumbnail_path`, and fires `VideoStatusUpdated` event.

- [ ] **Step 1: Write failing test for callback status 'ready' and asset registration**

In `panel/tests/Feature/Api/Internal/ProcessingCallbackTest.php`, add:
```php
public function test_it_registers_assets_and_marks_video_ready(): void
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $video = Video::factory()->forTenant($tenant)->create([
        'user_id' => $user->id,
        'status' => VideoStatus::TRANSCODING,
    ]);

    $assets = [
        [
            'type' => 'master',
            'disk' => 'minio-processed',
            'object_key' => "tenants/{$tenant->id}/videos/{$video->id}/hls/master.m3u8",
            'size_bytes' => 1024,
            'mime_type' => 'application/x-mpegURL',
            'metadata' => ['renditions' => ['1080p', '720p']],
        ],
        [
            'type' => 'hls_playlist',
            'disk' => 'minio-processed',
            'object_key' => "tenants/{$tenant->id}/videos/{$video->id}/hls/stream_0.m3u8",
            'size_bytes' => 4096,
            'mime_type' => 'application/x-mpegURL',
            'metadata' => ['resolution' => '1080p'],
        ],
        [
            'type' => 'thumbnail',
            'disk' => 'thumbnails',
            'object_key' => "tenants/{$tenant->id}/videos/{$video->id}/thumbnail.jpg",
            'size_bytes' => 65536,
            'mime_type' => 'image/jpeg',
        ],
    ];

    $response = $this->withHeaders(['X-Internal-Secret' => $this->secret])
        ->postJson('/api/v1/internal/processing/callback', [
            'job_id' => '019324c4-7bf3-7521-9989-d419b4ef08f1',
            'video_id' => $video->id,
            'status' => 'ready',
            'duration_seconds' => 120,
            'thumbnail_key' => "tenants/{$tenant->id}/videos/{$video->id}/thumbnail.jpg",
            'assets' => $assets,
        ]);

    $response->assertOk();
    $fresh = $video->fresh();
    $this->assertEquals(VideoStatus::READY, $fresh->status);
    $this->assertEquals("tenants/{$tenant->id}/videos/{$video->id}/thumbnail.jpg", $fresh->thumbnail_path);
    $this->assertEquals(120, $fresh->duration_seconds);
    $this->assertCount(3, $fresh->assets);
    $this->assertDatabaseHas('video_assets', [
        'video_id' => $video->id,
        'type' => 'master',
        'object_key' => "tenants/{$tenant->id}/videos/{$video->id}/hls/master.m3u8",
    ]);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T panel php artisan test tests/Feature/Api/Internal/ProcessingCallbackTest.php`  
Expected: FAIL with validation error on `status: ready` or assets not persisted.

- [ ] **Step 3: Implement Asset Registration Service and Update Callback Controller**

1. In `panel/app/Http/Requests/Internal/ProcessingCallbackRequest.php`:
   - Update `status` validation rule: `in:probing,transcoding,packaging,probed,ready,failed`.
   - Add validation rules:
     - `'thumbnail_key' => ['nullable', 'string', 'max:500'],`
     - `'assets' => ['nullable', 'array'],`
     - `'assets.*.type' => ['required', 'string'],`
     - `'assets.*.disk' => ['required', 'string'],`
     - `'assets.*.object_key' => ['required', 'string'],`
     - `'assets.*.size_bytes' => ['required', 'integer', 'min:0'],`
     - `'assets.*.mime_type' => ['required', 'string'],`
     - `'assets.*.metadata' => ['nullable', 'array'],`
2. Create `panel/app/Domain/Videos/Services/VideoAssetRegistrationService.php`:
   - `registerProcessedAssets(Video $video, array $assets, ?string $thumbnailKey, ?int $durationSeconds): Video`:
     - Inside `DB::transaction()`:
       - Upserts/creates `VideoAsset` records.
       - Updates `video->thumbnail_path = $thumbnailKey`.
       - Updates `video->duration_seconds = $durationSeconds` if provided.
       - Updates `video->status = VideoStatus::READY`.
       - Dispatches `VideoStatusUpdated::dispatch($video)`.
3. Create `panel/app/Events/Videos/VideoStatusUpdated.php`:
   - Implements `ShouldBroadcastNow`.
   - Broadcasts on `PrivateChannel("tenant.{$this->video->tenant_id}")`.
   - Broadcast with: `video_id`, `status`, `thumbnail_path`, `duration_seconds`.
4. Update `panel/app/Http/Controllers/Api/Internal/ProcessingCallbackController.php`:
   - Handles `transcoding`: sets `VideoStatus::TRANSCODING`.
   - Handles `packaging`: sets `VideoStatus::PACKAGING`.
   - Handles `ready`: delegates to `VideoAssetRegistrationService::registerProcessedAssets`.

- [ ] **Step 4: Run test to verify it passes and verify Pint formatting**

Run:
```bash
docker compose exec -T panel php artisan test tests/Feature/Api/Internal/ProcessingCallbackTest.php
docker compose exec -T panel ./vendor/bin/pint --test
```
Expected: PASS (all tests passing, 0 style violations).

- [ ] **Step 5: Commit**

```bash
git add panel/app/ panel/tests/Feature/Api/Internal/
git commit -m "feat(videos): register processed hls assets and broadcast ready status on callback"
```

---

### Task 7: Docker Compose Integration, Fixture Transcode & End-to-End Verification

**Files:**
- Create: `panel/tests/Feature/Processing/HlsTranscodingEndToEndTest.php`
- Modify: `docker-compose.yml`

**Interfaces:**
- Consumes: Running Docker Compose stack (`wiz_postgres`, `wiz_rabbitmq`, `wiz_minio`, `wiz_panel`, `wiz_worker`).
- Produces: Live end-to-end test generating test media, uploading original, triggering outbox publish, verifying worker transcodes to HLS, uploads to MinIO, and transitions video to `READY` with verified `.m3u8` master playlist.

- [ ] **Step 1: Rebuild and restart worker container**

Run:
```bash
docker compose build worker
docker compose up -d worker
docker compose ps
```
Expected: `wiz_worker` is in `Up` status.

- [ ] **Step 2: Create End-to-End HLS Transcoding Feature Test**

Create `panel/tests/Feature/Processing/HlsTranscodingEndToEndTest.php`:
- Generates a synthetic 3-second MP4 test video fixture with audio:
  `ffmpeg -f lavfi -i testsrc=duration=3:size=1280x720:rate=30 -f lavfi -i sine=frequency=1000:duration=3 -c:v libx264 -c:a aac -y /tmp/fixture_720p.mp4`
- Uploads fixture to MinIO `videos-original` bucket.
- Creates `Video` record and `MediaOutboxMessage` in database.
- Executes `php artisan media:publish-outbox`.
- Polls/waits for worker to process the job until `video->status === VideoStatus::READY` (or timeout at 30s).
- Asserts:
  - `video.status` is `READY`.
  - `video.thumbnail_path` is not null and exists in MinIO `thumbnails` bucket.
  - `video.assets` has `master` asset, and its `object_key` exists in MinIO `videos-processed` bucket.
  - Fetches `master.m3u8` from MinIO and asserts it contains `#EXTM3U`, `#EXT-X-STREAM-INF`, and rendition playlist references (`stream_0.m3u8`, `stream_1.m3u8`).

- [ ] **Step 3: Run the End-to-End Test in Panel**

Run:
```bash
docker compose exec -T panel php artisan test tests/Feature/Processing/HlsTranscodingEndToEndTest.php
```
Expected: PASS.

- [ ] **Step 4: Run full test suite across both Go and Laravel**

Run:
```bash
docker run --rm -v "D:\Playground\wiz-video-platform\worker:/app" -w /app golang:1.23-alpine go test -v ./...
docker compose exec -T panel php artisan test tests/Feature/Processing/
docker compose exec -T panel ./vendor/bin/pint --test
```
Expected: ALL PASS.

- [ ] **Step 5: Commit**

```bash
git add panel/tests/Feature/Processing/HlsTranscodingEndToEndTest.php docker-compose.yml
git commit -m "feat(processing): verify complete end-to-end hls transcoding and minio asset synchronization"
```
