package storage_test

import (
	"context"
	"errors"
	"fmt"
	"os"
	"path/filepath"
	"sync"
	"sync/atomic"
	"testing"
	"time"

	"github.com/minio/minio-go/v7"
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
		{"cover.jpeg", "image/jpeg"},
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

	// Create nested directory and file to verify recursive discovery
	nestedDir := filepath.Join(tempDir, "sub")
	_ = os.MkdirAll(nestedDir, 0755)
	_ = os.WriteFile(filepath.Join(nestedDir, "nested.ts"), []byte("nested ts"), 0644)

	files, err := storage.DiscoverHLSFiles(tempDir)
	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}

	if len(files) != 4 {
		t.Fatalf("expected 4 files, got %d: %v", len(files), files)
	}

	expectedFiles := map[string]bool{
		"master.m3u8":     false,
		"stream_0.m3u8":   false,
		"stream_0_000.ts": false,
		"sub/nested.ts":   false,
	}

	for _, f := range files {
		if _, ok := expectedFiles[f]; ok {
			expectedFiles[f] = true
		} else {
			t.Errorf("unexpected file discovered: %s", f)
		}
	}

	for f, found := range expectedFiles {
		if !found {
			t.Errorf("expected file not found: %s", f)
		}
	}
}

type mockUploader struct {
	mu          sync.Mutex
	uploads     []uploadCall
	failOnKey   string
	delay       time.Duration
	activeCount int32
	maxActive   int32
}

type uploadCall struct {
	Bucket     string
	ObjectName string
	FilePath   string
	Opts       minio.PutObjectOptions
}

func (m *mockUploader) FPutObject(ctx context.Context, bucketName, objectName, filePath string, opts minio.PutObjectOptions) (minio.UploadInfo, error) {
	curr := atomic.AddInt32(&m.activeCount, 1)
	defer atomic.AddInt32(&m.activeCount, -1)

	m.mu.Lock()
	if curr > m.maxActive {
		m.maxActive = curr
	}
	m.mu.Unlock()

	if m.delay > 0 {
		select {
		case <-time.After(m.delay):
		case <-ctx.Done():
			return minio.UploadInfo{}, ctx.Err()
		}
	}

	if m.failOnKey != "" && objectName == m.failOnKey {
		return minio.UploadInfo{}, errors.New("simulated minio upload failure")
	}

	fi, err := os.Stat(filePath)
	if err != nil {
		return minio.UploadInfo{}, err
	}

	m.mu.Lock()
	m.uploads = append(m.uploads, uploadCall{
		Bucket:     bucketName,
		ObjectName: objectName,
		FilePath:   filePath,
		Opts:       opts,
	})
	m.mu.Unlock()

	return minio.UploadInfo{
		Bucket: bucketName,
		Key:    objectName,
		Size:   fi.Size(),
	}, nil
}

func TestUploadHLSAssets_Success(t *testing.T) {
	tempDir, err := os.MkdirTemp("", "hls_upload_test")
	if err != nil {
		t.Fatal(err)
	}
	defer os.RemoveAll(tempDir)

	masterContent := []byte("#EXTM3U\n#EXT-X-VERSION:3")
	streamContent := []byte("#EXTM3U\n#EXT-X-TARGETDURATION:4")
	segContent := []byte("fake-ts-segment-bytes")
	thumbContent := []byte("fake-jpeg-data")

	_ = os.WriteFile(filepath.Join(tempDir, "master.m3u8"), masterContent, 0644)
	_ = os.WriteFile(filepath.Join(tempDir, "stream_0.m3u8"), streamContent, 0644)
	_ = os.WriteFile(filepath.Join(tempDir, "stream_0_000.ts"), segContent, 0644)

	thumbFile := filepath.Join(tempDir, "thumb.jpg")
	_ = os.WriteFile(thumbFile, thumbContent, 0644)

	mock := &mockUploader{}
	store := storage.NewTestStorage(mock)

	assets, err := store.UploadHLSAssets(
		context.Background(),
		tempDir,
		thumbFile,
		"t-100",
		"v-200",
		"videos-processed",
		"thumbnails",
	)
	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}

	// 3 HLS files + 1 thumbnail = 4 assets
	if len(assets) != 4 {
		t.Fatalf("expected 4 assets, got %d", len(assets))
	}

	assetsByType := make(map[string]storage.AssetInfo)
	for _, a := range assets {
		assetsByType[a.Type] = a
	}

	// Verify master
	master, ok := assetsByType["master"]
	if !ok {
		t.Fatal("missing 'master' asset")
	}
	if master.Disk != "minio-processed" {
		t.Errorf("master disk: expected minio-processed, got %s", master.Disk)
	}
	if master.ObjectKey != "tenants/t-100/videos/v-200/hls/master.m3u8" {
		t.Errorf("master key unexpected: %s", master.ObjectKey)
	}
	if master.MimeType != "application/x-mpegURL" {
		t.Errorf("master mime unexpected: %s", master.MimeType)
	}
	if master.SizeBytes != int64(len(masterContent)) {
		t.Errorf("master size unexpected: %d", master.SizeBytes)
	}

	// Verify playlist
	playlist, ok := assetsByType["hls_playlist"]
	if !ok {
		t.Fatal("missing 'hls_playlist' asset")
	}
	if playlist.Disk != "minio-processed" {
		t.Errorf("playlist disk unexpected: %s", playlist.Disk)
	}
	if playlist.ObjectKey != "tenants/t-100/videos/v-200/hls/stream_0.m3u8" {
		t.Errorf("playlist key unexpected: %s", playlist.ObjectKey)
	}
	if playlist.MimeType != "application/x-mpegURL" {
		t.Errorf("playlist mime unexpected: %s", playlist.MimeType)
	}

	// Verify segment
	segment, ok := assetsByType["hls_segment"]
	if !ok {
		t.Fatal("missing 'hls_segment' asset")
	}
	if segment.Disk != "minio-processed" {
		t.Errorf("segment disk unexpected: %s", segment.Disk)
	}
	if segment.ObjectKey != "tenants/t-100/videos/v-200/hls/stream_0_000.ts" {
		t.Errorf("segment key unexpected: %s", segment.ObjectKey)
	}
	if segment.MimeType != "video/MP2T" {
		t.Errorf("segment mime unexpected: %s", segment.MimeType)
	}

	// Verify thumbnail
	thumb, ok := assetsByType["thumbnail"]
	if !ok {
		t.Fatal("missing 'thumbnail' asset")
	}
	if thumb.Disk != "thumbnails" {
		t.Errorf("thumb disk unexpected: %s", thumb.Disk)
	}
	if thumb.ObjectKey != "tenants/t-100/videos/v-200/thumbnail.jpg" {
		t.Errorf("thumb key unexpected: %s", thumb.ObjectKey)
	}
	if thumb.MimeType != "image/jpeg" {
		t.Errorf("thumb mime unexpected: %s", thumb.MimeType)
	}
	if thumb.SizeBytes != int64(len(thumbContent)) {
		t.Errorf("thumb size unexpected: %d", thumb.SizeBytes)
	}
}

func TestUploadHLSAssets_UploadErrorCancelsPending(t *testing.T) {
	tempDir, err := os.MkdirTemp("", "hls_upload_fail_test")
	if err != nil {
		t.Fatal(err)
	}
	defer os.RemoveAll(tempDir)

	for i := 0; i < 10; i++ {
		_ = os.WriteFile(filepath.Join(tempDir, fmt.Sprintf("stream_0_%03d.ts", i)), []byte("seg"), 0644)
	}

	mock := &mockUploader{
		failOnKey: "tenants/t-1/videos/v-1/hls/stream_0_002.ts",
		delay:     10 * time.Millisecond,
	}
	store := storage.NewTestStorage(mock)

	_, err = store.UploadHLSAssets(
		context.Background(),
		tempDir,
		"", // no thumbnail
		"t-1",
		"v-1",
		"videos-processed",
		"thumbnails",
	)
	if err == nil {
		t.Fatal("expected upload error, got nil")
	}
}

func TestUploadHLSAssets_WithoutThumbnail(t *testing.T) {
	tempDir, err := os.MkdirTemp("", "hls_no_thumb_test")
	if err != nil {
		t.Fatal(err)
	}
	defer os.RemoveAll(tempDir)

	_ = os.WriteFile(filepath.Join(tempDir, "master.m3u8"), []byte("#EXTM3U"), 0644)

	mock := &mockUploader{}
	store := storage.NewTestStorage(mock)

	assets, err := store.UploadHLSAssets(
		context.Background(),
		tempDir,
		"", // no thumbnail
		"t-1",
		"v-1",
		"videos-processed",
		"thumbnails",
	)
	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}

	if len(assets) != 1 {
		t.Fatalf("expected 1 asset, got %d", len(assets))
	}
	if assets[0].Type != "master" {
		t.Errorf("expected type master, got %s", assets[0].Type)
	}
}

func TestUploadHLSAssets_MissingDirReturnsError(t *testing.T) {
	mock := &mockUploader{}
	store := storage.NewTestStorage(mock)

	_, err := store.UploadHLSAssets(
		context.Background(),
		"/nonexistent/dir/for/sure",
		"",
		"t-1",
		"v-1",
		"videos-processed",
		"thumbnails",
	)
	if err == nil {
		t.Fatal("expected error for nonexistent directory, got nil")
	}
}

func TestUploadHLSAssets_MissingThumbnailReturnsError(t *testing.T) {
	tempDir, err := os.MkdirTemp("", "hls_missing_thumb_test")
	if err != nil {
		t.Fatal(err)
	}
	defer os.RemoveAll(tempDir)

	_ = os.WriteFile(filepath.Join(tempDir, "master.m3u8"), []byte("#EXTM3U"), 0644)

	mock := &mockUploader{}
	store := storage.NewTestStorage(mock)

	_, err = store.UploadHLSAssets(
		context.Background(),
		tempDir,
		"/nonexistent/thumbnail.jpg",
		"t-1",
		"v-1",
		"videos-processed",
		"thumbnails",
	)
	if err == nil {
		t.Fatal("expected error for nonexistent thumbnail file, got nil")
	}
}
