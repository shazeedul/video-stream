package storage

import (
	"context"
	"fmt"
	"os"
	"path/filepath"
	"strings"

	"github.com/minio/minio-go/v7"
	"github.com/minio/minio-go/v7/pkg/credentials"
	"wiz-media-worker/internal/config"
)

// ObjectUploader abstracts the MinIO FPutObject functionality for uploading files.
type ObjectUploader interface {
	FPutObject(ctx context.Context, bucketName, objectName, filePath string, opts minio.PutObjectOptions) (info minio.UploadInfo, err error)
}

// MinioStorage wraps the MinIO client to provide object storage operations.
type MinioStorage struct {
	client   *minio.Client
	uploader ObjectUploader
}

// NewMinioClient initializes and returns a new MinioStorage client from the application config.
func NewMinioClient(cfg *config.Config) (*MinioStorage, error) {
	endpoint := cleanEndpoint(cfg.MinioEndpoint)
	useSSL := cfg.MinioUseSSL

	if strings.HasPrefix(cfg.MinioEndpoint, "https://") {
		useSSL = true
	}

	client, err := minio.New(endpoint, &minio.Options{
		Creds:  credentials.NewStaticV4(cfg.MinioAccessKey, cfg.MinioSecretKey, ""),
		Secure: useSSL,
	})
	if err != nil {
		return nil, fmt.Errorf("failed to initialize minio client: %w", err)
	}

	return &MinioStorage{
		client:   client,
		uploader: client,
	}, nil
}

// NewTestStorage creates a MinioStorage instance backed by a custom ObjectUploader for testing.
func NewTestStorage(uploader ObjectUploader) *MinioStorage {
	return &MinioStorage{
		uploader: uploader,
	}
}

// SetUploader sets the ObjectUploader used for uploads.
func (s *MinioStorage) SetUploader(u ObjectUploader) {
	s.uploader = u
}

// getUploader returns the active ObjectUploader.
func (s *MinioStorage) getUploader() ObjectUploader {
	if s.uploader != nil {
		return s.uploader
	}
	return s.client
}

// cleanEndpoint strips any URL scheme and trailing slashes so minio-go can parse it.
func cleanEndpoint(raw string) string {
	raw = strings.TrimPrefix(raw, "http://")
	raw = strings.TrimPrefix(raw, "https://")
	return strings.TrimRight(raw, "/")
}

// Client returns the underlying minio.Client.
func (s *MinioStorage) Client() *minio.Client {
	return s.client
}

// DownloadToFile downloads an object from the specified bucket and key to the local file path.
func (s *MinioStorage) DownloadToFile(ctx context.Context, bucket, objectKey, localPath string) error {
	if dir := filepath.Dir(localPath); dir != "" && dir != "." {
		if err := os.MkdirAll(dir, 0755); err != nil {
			return fmt.Errorf("failed to create directory for local path %s: %w", localPath, err)
		}
	}

	err := s.client.FGetObject(ctx, bucket, objectKey, localPath, minio.GetObjectOptions{})
	if err != nil {
		return fmt.Errorf("failed to download object %s/%s to %s: %w", bucket, objectKey, localPath, err)
	}

	return nil
}

// DownloadSourceFile is an alias for DownloadToFile to satisfy source downloading conventions.
func (s *MinioStorage) DownloadSourceFile(ctx context.Context, bucket, objectKey, destPath string) error {
	return s.DownloadToFile(ctx, bucket, objectKey, destPath)
}
