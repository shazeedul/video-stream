package main

import (
	"context"
	"errors"
	"os"
	"os/signal"
	"syscall"

	"wiz-media-worker/internal/callback"
	"wiz-media-worker/internal/config"
	"wiz-media-worker/internal/consumer"
	"wiz-media-worker/internal/observability"
	"wiz-media-worker/internal/pipeline"
	"wiz-media-worker/internal/probe"
	"wiz-media-worker/internal/storage"
)

func main() {
	logger := observability.InitLogger()
	logger.Info("starting wiz-media-worker daemon...")

	cfg, err := config.Load()
	if err != nil {
		logger.Error("failed to load configuration", "error", err)
		os.Exit(1)
	}

	logger.Info("configuration loaded successfully",
		"rabbitmq_queue", cfg.RabbitMQQueue,
		"concurrency", cfg.Concurrency,
		"temp_dir", cfg.TempDir,
		"minio_endpoint", cfg.MinioEndpoint,
		"callback_url", cfg.InternalCallbackURL,
	)

	// Initialize MinIO storage client
	storageClient, err := storage.NewMinioClient(cfg)
	if err != nil {
		logger.Error("failed to initialize minio storage client", "error", err)
		os.Exit(1)
	}

	// Initialize probe inspector (defaults to "ffprobe" binary in PATH)
	inspector := probe.NewInspector("")

	// Initialize HTTP callback client
	callbackClient := callback.NewClient(cfg.InternalCallbackURL, cfg.InternalAPISecret)

	// Initialize media pipeline orchestrator
	mediaPipeline := pipeline.NewPipeline(cfg, storageClient, inspector, callbackClient, logger)

	// Initialize RabbitMQ consumer daemon
	mediaConsumer := consumer.NewConsumer(cfg, mediaPipeline, logger)

	// Graceful shutdown handling on SIGINT/SIGTERM
	ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
	defer stop()

	logger.Info("worker initialized, starting queue consumer...")
	if err := mediaConsumer.Start(ctx); err != nil && !errors.Is(err, context.Canceled) {
		logger.Error("worker consumer stopped with error", "error", err)
		os.Exit(1)
	}

	logger.Info("wiz-media-worker terminated gracefully")
}
