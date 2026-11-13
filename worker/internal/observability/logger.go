package observability

import (
	"log/slog"
	"os"
)

// NewLogger creates and returns a structured JSON logger writing to os.Stdout.
func NewLogger() *slog.Logger {
	handler := slog.NewJSONHandler(os.Stdout, &slog.HandlerOptions{
		Level: slog.LevelInfo,
	})
	return slog.New(handler)
}

// InitLogger initializes and sets the default global slog logger to the structured JSON logger.
func InitLogger() *slog.Logger {
	logger := NewLogger()
	slog.SetDefault(logger)
	return logger
}
