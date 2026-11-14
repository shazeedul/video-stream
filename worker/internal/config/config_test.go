package config_test

import (
	"os"
	"testing"
	"wiz-media-worker/internal/config"
)

func TestLoadConfig_DefaultsAndOverrides(t *testing.T) {
	os.Setenv("RABBITMQ_URL", "amqp://test:test@localhost:5672/")
	os.Setenv("INTERNAL_API_SECRET", "super-secret")
	defer os.Unsetenv("RABBITMQ_URL")
	defer os.Unsetenv("INTERNAL_API_SECRET")

	cfg, err := config.Load()
	if err != nil {
		t.Fatalf("expected no error, got %v", err)
	}

	if cfg.RabbitMQURL != "amqp://test:test@localhost:5672/" {
		t.Errorf("unexpected RabbitMQURL: %s", cfg.RabbitMQURL)
	}
	if cfg.InternalAPISecret != "super-secret" {
		t.Errorf("unexpected InternalAPISecret: %s", cfg.InternalAPISecret)
	}
	if cfg.Concurrency != 2 {
		t.Errorf("expected default concurrency 2, got %d", cfg.Concurrency)
	}
}

func TestLoadConfig_AllDefaults(t *testing.T) {
	// Clear any env variables that might be set
	envVars := []string{
		"RABBITMQ_URL",
		"RABBITMQ_QUEUE",
		"MINIO_ENDPOINT",
		"MINIO_ACCESS_KEY",
		"MINIO_SECRET_KEY",
		"MINIO_USE_SSL",
		"MINIO_PROCESSED_BUCKET",
		"MINIO_THUMBNAILS_BUCKET",
		"INTERNAL_CALLBACK_URL",
		"INTERNAL_API_SECRET",
		"CONCURRENCY",
		"WORKER_CONCURRENCY",
		"TEMP_DIR",
		"WORKER_TEMP_DIR",
	}
	for _, k := range envVars {
		orig := os.Getenv(k)
		if orig != "" {
			defer os.Setenv(k, orig)
			os.Unsetenv(k)
		}
	}

	cfg, err := config.Load()
	if err != nil {
		t.Fatalf("expected no error, got %v", err)
	}

	if cfg.RabbitMQURL != "amqp://guest:guest@rabbitmq:5672/" {
		t.Errorf("unexpected default RabbitMQURL: %s", cfg.RabbitMQURL)
	}
	if cfg.RabbitMQQueue != "media.jobs" {
		t.Errorf("unexpected default RabbitMQQueue: %s", cfg.RabbitMQQueue)
	}
	if cfg.MinioEndpoint != "minio:9000" {
		t.Errorf("unexpected default MinioEndpoint: %s", cfg.MinioEndpoint)
	}
	if cfg.MinioAccessKey != "minioadmin" {
		t.Errorf("unexpected default MinioAccessKey: %s", cfg.MinioAccessKey)
	}
	if cfg.MinioSecretKey != "minioadmin" {
		t.Errorf("unexpected default MinioSecretKey: %s", cfg.MinioSecretKey)
	}
	if cfg.MinioUseSSL != false {
		t.Errorf("unexpected default MinioUseSSL: %v", cfg.MinioUseSSL)
	}
	if cfg.MinioProcessedBucket != "videos-processed" {
		t.Errorf("unexpected default MinioProcessedBucket: %s", cfg.MinioProcessedBucket)
	}
	if cfg.MinioThumbnailsBucket != "thumbnails" {
		t.Errorf("unexpected default MinioThumbnailsBucket: %s", cfg.MinioThumbnailsBucket)
	}
	if cfg.InternalCallbackURL != "http://panel:8000/api/v1/internal/processing/callback" {
		t.Errorf("unexpected default InternalCallbackURL: %s", cfg.InternalCallbackURL)
	}
	if cfg.InternalAPISecret != "internal-worker-secret-key-12345" {
		t.Errorf("unexpected default InternalAPISecret: %s", cfg.InternalAPISecret)
	}
	if cfg.Concurrency != 2 {
		t.Errorf("unexpected default Concurrency: %d", cfg.Concurrency)
	}
	if cfg.TempDir != "/tmp/media" {
		t.Errorf("unexpected default TempDir: %s", cfg.TempDir)
	}
}

func TestLoadConfig_CustomValues(t *testing.T) {
	os.Setenv("RABBITMQ_QUEUE", "custom.queue")
	os.Setenv("MINIO_ENDPOINT", "minio.local:9000")
	os.Setenv("MINIO_ACCESS_KEY", "custom-user")
	os.Setenv("MINIO_SECRET_KEY", "custom-password")
	os.Setenv("MINIO_USE_SSL", "true")
	os.Setenv("MINIO_PROCESSED_BUCKET", "custom-processed")
	os.Setenv("MINIO_THUMBNAILS_BUCKET", "custom-thumbs")
	os.Setenv("INTERNAL_CALLBACK_URL", "https://api.example.com/callback")
	os.Setenv("WORKER_CONCURRENCY", "8")
	os.Setenv("WORKER_TEMP_DIR", "/custom/tmp")

	defer func() {
		os.Unsetenv("RABBITMQ_QUEUE")
		os.Unsetenv("MINIO_ENDPOINT")
		os.Unsetenv("MINIO_ACCESS_KEY")
		os.Unsetenv("MINIO_SECRET_KEY")
		os.Unsetenv("MINIO_USE_SSL")
		os.Unsetenv("MINIO_PROCESSED_BUCKET")
		os.Unsetenv("MINIO_THUMBNAILS_BUCKET")
		os.Unsetenv("INTERNAL_CALLBACK_URL")
		os.Unsetenv("WORKER_CONCURRENCY")
		os.Unsetenv("WORKER_TEMP_DIR")
	}()

	cfg, err := config.Load()
	if err != nil {
		t.Fatalf("expected no error, got %v", err)
	}

	if cfg.RabbitMQQueue != "custom.queue" {
		t.Errorf("unexpected RabbitMQQueue: %s", cfg.RabbitMQQueue)
	}
	if cfg.MinioEndpoint != "minio.local:9000" {
		t.Errorf("unexpected MinioEndpoint: %s", cfg.MinioEndpoint)
	}
	if cfg.MinioAccessKey != "custom-user" {
		t.Errorf("unexpected MinioAccessKey: %s", cfg.MinioAccessKey)
	}
	if cfg.MinioSecretKey != "custom-password" {
		t.Errorf("unexpected MinioSecretKey: %s", cfg.MinioSecretKey)
	}
	if cfg.MinioUseSSL != true {
		t.Errorf("unexpected MinioUseSSL: %v", cfg.MinioUseSSL)
	}
	if cfg.MinioProcessedBucket != "custom-processed" {
		t.Errorf("unexpected MinioProcessedBucket: %s", cfg.MinioProcessedBucket)
	}
	if cfg.MinioThumbnailsBucket != "custom-thumbs" {
		t.Errorf("unexpected MinioThumbnailsBucket: %s", cfg.MinioThumbnailsBucket)
	}
	if cfg.InternalCallbackURL != "https://api.example.com/callback" {
		t.Errorf("unexpected InternalCallbackURL: %s", cfg.InternalCallbackURL)
	}
	if cfg.Concurrency != 8 {
		t.Errorf("unexpected Concurrency: %d", cfg.Concurrency)
	}
	if cfg.TempDir != "/custom/tmp" {
		t.Errorf("unexpected TempDir: %s", cfg.TempDir)
	}
}
