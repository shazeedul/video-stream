package storage

import (
	"context"
	"errors"
	"fmt"
	"os"
	"path/filepath"
	"sort"
	"strings"
	"sync"

	"github.com/minio/minio-go/v7"
)

const defaultUploadConcurrency = 5

// AssetInfo describes a transcoded video asset or thumbnail stored in object storage.
type AssetInfo struct {
	Type      string         `json:"type"`
	Disk      string         `json:"disk"`
	ObjectKey string         `json:"object_key"`
	SizeBytes int64          `json:"size_bytes"`
	MimeType  string         `json:"mime_type"`
	Metadata  map[string]any `json:"metadata,omitempty"`
}

type uploadTask struct {
	bucket    string
	key       string
	localPath string
	assetInfo AssetInfo
}

// DetectMimeType determines the appropriate Content-Type / MIME type for an asset file name.
func DetectMimeType(filename string) string {
	ext := strings.ToLower(filepath.Ext(filename))
	switch ext {
	case ".m3u8":
		return "application/x-mpegURL"
	case ".ts":
		return "video/MP2T"
	case ".jpg", ".jpeg":
		return "image/jpeg"
	default:
		return "application/octet-stream"
	}
}

// DiscoverHLSFiles recursively traverses the provided directory and returns relative paths
// for all discovered files, normalized with forward slashes.
func DiscoverHLSFiles(dir string) ([]string, error) {
	var files []string
	err := filepath.Walk(dir, func(path string, info os.FileInfo, err error) error {
		if err != nil {
			return err
		}
		if info.IsDir() {
			return nil
		}
		rel, err := filepath.Rel(dir, path)
		if err != nil {
			return err
		}
		files = append(files, filepath.ToSlash(rel))
		return nil
	})
	if err != nil {
		return nil, fmt.Errorf("failed to discover HLS files in %s: %w", dir, err)
	}

	sort.Strings(files)
	return files, nil
}

// UploadHLSAssets uploads all discovered HLS files and the optional poster thumbnail to MinIO
// using a bounded concurrent worker pool (concurrency = 5). If any upload fails, pending uploads
// are cancelled via context and an error is returned.
func (s *MinioStorage) UploadHLSAssets(
	ctx context.Context,
	hlsDir string,
	thumbnailPath string,
	tenantID string,
	videoID string,
	processedBucket string,
	thumbnailBucket string,
) ([]AssetInfo, error) {
	if s == nil {
		return nil, errors.New("minio storage is nil")
	}

	uploader := s.getUploader()
	if uploader == nil {
		return nil, errors.New("minio uploader not initialized")
	}

	relFiles, err := DiscoverHLSFiles(hlsDir)
	if err != nil {
		return nil, err
	}

	var tasks []uploadTask

	for _, relFile := range relFiles {
		localPath := filepath.Join(hlsDir, filepath.FromSlash(relFile))

		// If thumbnailPath points to a file within hlsDir, skip it in HLS files list
		// so it is not uploaded twice or categorized as an HLS asset.
		if thumbnailPath != "" {
			if absThumb, err := filepath.Abs(thumbnailPath); err == nil {
				if absLocal, err := filepath.Abs(localPath); err == nil && absThumb == absLocal {
					continue
				}
			}
		}

		fi, err := os.Stat(localPath)
		if err != nil {
			return nil, fmt.Errorf("failed to stat HLS file %s: %w", localPath, err)
		}

		objectKey := fmt.Sprintf("tenants/%s/videos/%s/hls/%s", tenantID, videoID, relFile)

		var assetType string
		if relFile == "master.m3u8" {
			assetType = "master"
		} else if strings.HasSuffix(relFile, ".m3u8") {
			assetType = "hls_playlist"
		} else if strings.HasSuffix(relFile, ".ts") {
			assetType = "hls_segment"
		} else {
			assetType = "asset"
		}

		tasks = append(tasks, uploadTask{
			bucket:    processedBucket,
			key:       objectKey,
			localPath: localPath,
			assetInfo: AssetInfo{
				Type:      assetType,
				Disk:      "minio-processed",
				ObjectKey: objectKey,
				SizeBytes: fi.Size(),
				MimeType:  DetectMimeType(relFile),
			},
		})
	}

	if thumbnailPath != "" {
		fi, err := os.Stat(thumbnailPath)
		if err != nil {
			return nil, fmt.Errorf("failed to stat thumbnail %s: %w", thumbnailPath, err)
		}

		thumbKey := fmt.Sprintf("tenants/%s/videos/%s/thumbnail.jpg", tenantID, videoID)
		tasks = append(tasks, uploadTask{
			bucket:    thumbnailBucket,
			key:       thumbKey,
			localPath: thumbnailPath,
			assetInfo: AssetInfo{
				Type:      "thumbnail",
				Disk:      "thumbnails",
				ObjectKey: thumbKey,
				SizeBytes: fi.Size(),
				MimeType:  DetectMimeType(thumbnailPath),
			},
		})
	}

	if len(tasks) == 0 {
		return []AssetInfo{}, nil
	}

	ctx, cancel := context.WithCancel(ctx)
	defer cancel()

	sem := make(chan struct{}, defaultUploadConcurrency)
	var (
		wg       sync.WaitGroup
		errOnce  sync.Once
		firstErr error
		results  = make([]AssetInfo, len(tasks))
	)

taskLoop:
	for i, t := range tasks {
		select {
		case <-ctx.Done():
			break taskLoop
		case sem <- struct{}{}:
		}

		if ctx.Err() != nil {
			<-sem
			break taskLoop
		}

		wg.Add(1)
		go func(idx int, task uploadTask) {
			defer wg.Done()
			defer func() { <-sem }()

			if ctx.Err() != nil {
				return
			}

			info, err := uploader.FPutObject(ctx, task.bucket, task.key, task.localPath, minio.PutObjectOptions{
				ContentType: task.assetInfo.MimeType,
			})
			if err != nil {
				errOnce.Do(func() {
					firstErr = fmt.Errorf("failed to upload %s to %s/%s: %w", task.localPath, task.bucket, task.key, err)
					cancel()
				})
				return
			}

			if info.Size > 0 {
				task.assetInfo.SizeBytes = info.Size
			}
			results[idx] = task.assetInfo
		}(i, t)
	}

	wg.Wait()

	if firstErr != nil {
		return nil, firstErr
	}
	if err := ctx.Err(); err != nil {
		return nil, err
	}

	return results, nil
}

// UploadHLSAssets is a convenience helper for uploading HLS assets using a MinioStorage instance.
func UploadHLSAssets(
	ctx context.Context,
	s *MinioStorage,
	hlsDir string,
	thumbnailPath string,
	tenantID string,
	videoID string,
	processedBucket string,
	thumbnailBucket string,
) ([]AssetInfo, error) {
	return s.UploadHLSAssets(ctx, hlsDir, thumbnailPath, tenantID, videoID, processedBucket, thumbnailBucket)
}
