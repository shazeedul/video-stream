package storage_test

import (
	"context"
	"testing"
	"wiz-media-worker/internal/config"
	"wiz-media-worker/internal/storage"
)

func TestNewMinioClient_Success(t *testing.T) {
	cfg := &config.Config{
		MinioEndpoint:  "http://minio:9000",
		MinioAccessKey: "minioadmin",
		MinioSecretKey: "minioadmin",
		MinioUseSSL:    false,
	}

	client, err := storage.NewMinioClient(cfg)
	if err != nil {
		t.Fatalf("expected no error, got %v", err)
	}

	if client == nil {
		t.Fatal("expected non-nil client")
	}

	if client.Client() == nil {
		t.Fatal("expected non-nil underlying minio client")
	}
}

func TestNewMinioClient_HTTPS(t *testing.T) {
	cfg := &config.Config{
		MinioEndpoint:  "https://s3.amazonaws.com",
		MinioAccessKey: "test-key",
		MinioSecretKey: "test-secret",
		MinioUseSSL:    false, // should auto-enable SSL due to https://
	}

	client, err := storage.NewMinioClient(cfg)
	if err != nil {
		t.Fatalf("expected no error, got %v", err)
	}

	if client == nil {
		t.Fatal("expected non-nil client")
	}
}

func TestDownloadToFile_NonexistentObjectReturnsError(t *testing.T) {
	cfg := &config.Config{
		MinioEndpoint:  "localhost:65534", // offline port
		MinioAccessKey: "minioadmin",
		MinioSecretKey: "minioadmin",
		MinioUseSSL:    false,
	}

	client, err := storage.NewMinioClient(cfg)
	if err != nil {
		t.Fatalf("expected client initialization to succeed, got %v", err)
	}

	err = client.DownloadToFile(context.Background(), "test-bucket", "missing.mp4", "/tmp/nonexistent/missing.mp4")
	if err == nil {
		t.Fatal("expected error downloading from offline minio instance, got nil")
	}
}
