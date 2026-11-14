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

	// Verify dimensions are set correctly
	if ladder[0].Width != 1920 || ladder[0].Height != 1080 {
		t.Errorf("1080p: expected 1920x1080, got %dx%d", ladder[0].Width, ladder[0].Height)
	}
	if ladder[1].Width != 1280 || ladder[1].Height != 720 {
		t.Errorf("720p: expected 1280x720, got %dx%d", ladder[1].Width, ladder[1].Height)
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
	for i, p := range ladder {
		if p.Width%2 != 0 || p.Height%2 != 0 {
			t.Errorf("profile %d (%s): expected even dimensions, got %dx%d", i, p.Name, p.Width, p.Height)
		}
	}
}

func TestSelectLadder_LowResolutionBaseline_360p(t *testing.T) {
	// Source lower than 360p (e.g. 320x240) should still yield baseline 360p tier without upscaling
	ladder := transcoder.SelectLadder(320, 240)
	if len(ladder) != 1 {
		t.Fatalf("expected 1 baseline profile for 320x240 source, got %d", len(ladder))
	}
	if ladder[0].Name != "360p" {
		t.Errorf("expected 360p profile as baseline, got %s", ladder[0].Name)
	}
	if ladder[0].Width%2 != 0 || ladder[0].Height%2 != 0 {
		t.Errorf("expected even dimensions, got %dx%d", ladder[0].Width, ladder[0].Height)
	}
	if ladder[0].Height > 240 || ladder[0].Width > 320 {
		t.Errorf("baseline profile should not upscale beyond source resolution, got %dx%d", ladder[0].Width, ladder[0].Height)
	}
}

func TestCalculateDimensions_EvenNumbersGuaranteed(t *testing.T) {
	// Odd input dimensions (e.g. 1921x1079)
	w, h := transcoder.CalculateDimensions(1921, 1079, 720)
	if w%2 != 0 || h%2 != 0 {
		t.Errorf("dimensions must be even, got %dx%d", w, h)
	}
}

func TestCalculateDimensions_EdgeCases(t *testing.T) {
	// Invalid inputs
	w, h := transcoder.CalculateDimensions(0, 1080, 720)
	if w != 0 || h != 0 {
		t.Errorf("expected (0, 0) for zero width, got (%d, %d)", w, h)
	}

	w, h = transcoder.CalculateDimensions(1920, 0, 720)
	if w != 0 || h != 0 {
		t.Errorf("expected (0, 0) for zero height, got (%d, %d)", w, h)
	}

	w, h = transcoder.CalculateDimensions(1920, 1080, 0)
	if w != 0 || h != 0 {
		t.Errorf("expected (0, 0) for zero target height, got (%d, %d)", w, h)
	}

	// Odd target height should also be evened out
	w, h = transcoder.CalculateDimensions(1920, 1080, 721)
	if w%2 != 0 || h%2 != 0 {
		t.Errorf("expected even dimensions for odd target height, got %dx%d", w, h)
	}
}

func TestProfile_FilterString(t *testing.T) {
	p := transcoder.Profile{
		Name:   "720p",
		Width:  1280,
		Height: 720,
	}

	filter := p.FilterString(1)
	expected := "[v1]scale=1280:720[v1out]"
	if filter != expected {
		t.Errorf("expected %s, got %s", expected, filter)
	}
}
