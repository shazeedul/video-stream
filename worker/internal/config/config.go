package config

import (
	"os"
	"strconv"
	"strings"
)

// Config holds the application configuration loaded from environment variables.
type Config struct {
	RabbitMQURL           string
	RabbitMQQueue         string
	MinioEndpoint         string
	MinioAccessKey        string
	MinioSecretKey        string
	MinioUseSSL           bool
	MinioProcessedBucket  string
	MinioThumbnailsBucket string
	InternalCallbackURL   string
	InternalAPISecret     string
	Concurrency           int
	TempDir               string
}

// Load loads the configuration from environment variables with sensible defaults.
func Load() (*Config, error) {
	cfg := &Config{
		RabbitMQURL:           getEnv("RABBITMQ_URL", "amqp://guest:guest@rabbitmq:5672/"),
		RabbitMQQueue:         getEnv("RABBITMQ_QUEUE", "media.jobs"),
		MinioEndpoint:         getEnv("MINIO_ENDPOINT", "minio:9000"),
		MinioAccessKey:        getEnv("MINIO_ACCESS_KEY", "minioadmin"),
		MinioSecretKey:        getEnv("MINIO_SECRET_KEY", "minioadmin"),
		MinioUseSSL:           getEnvBool("MINIO_USE_SSL", false),
		MinioProcessedBucket:  getEnv("MINIO_PROCESSED_BUCKET", "videos-processed"),
		MinioThumbnailsBucket: getEnv("MINIO_THUMBNAILS_BUCKET", "thumbnails"),
		InternalCallbackURL:   getEnv("INTERNAL_CALLBACK_URL", "http://panel:8000/api/v1/internal/processing/callback"),
		InternalAPISecret:     getEnv("INTERNAL_API_SECRET", "internal-worker-secret-key-12345"),
		Concurrency:           getEnvInt("CONCURRENCY", 2),
		TempDir:               getEnv("TEMP_DIR", "/tmp/media"),
	}

	if val := os.Getenv("WORKER_CONCURRENCY"); val != "" {
		if i, err := strconv.Atoi(val); err == nil && i > 0 {
			cfg.Concurrency = i
		}
	}

	if val := os.Getenv("WORKER_TEMP_DIR"); val != "" {
		cfg.TempDir = val
	}

	return cfg, nil
}

func getEnv(key, defaultVal string) string {
	if val, ok := os.LookupEnv(key); ok && val != "" {
		return val
	}
	return defaultVal
}

func getEnvInt(key string, defaultVal int) int {
	if val, ok := os.LookupEnv(key); ok && val != "" {
		if parsed, err := strconv.Atoi(val); err == nil {
			return parsed
		}
	}
	return defaultVal
}

func getEnvBool(key string, defaultVal bool) bool {
	if val, ok := os.LookupEnv(key); ok && val != "" {
		lower := strings.ToLower(strings.TrimSpace(val))
		if lower == "true" || lower == "1" || lower == "yes" || lower == "on" {
			return true
		}
		if lower == "false" || lower == "0" || lower == "no" || lower == "off" {
			return false
		}
	}
	return defaultVal
}
