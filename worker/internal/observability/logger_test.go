package observability_test

import (
	"testing"
	"wiz-media-worker/internal/observability"
)

func TestNewLogger(t *testing.T) {
	logger := observability.NewLogger()
	if logger == nil {
		t.Fatal("expected non-nil logger")
	}

	initLogger := observability.InitLogger()
	if initLogger == nil {
		t.Fatal("expected non-nil initialized logger")
	}
}
