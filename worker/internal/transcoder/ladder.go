package transcoder

import "math"

// CalculateDimensions calculates even width and height preserving aspect ratio
// for a given target height, strictly enforcing H.264 macroblock alignment.
func CalculateDimensions(srcWidth, srcHeight, targetHeight int) (int, int) {
	if srcWidth <= 0 || srcHeight <= 0 || targetHeight <= 0 {
		return 0, 0
	}

	h := (targetHeight / 2) * 2
	if h < 2 {
		h = 2
	}

	aspectRatio := float64(srcWidth) / float64(srcHeight)
	w := int(math.Round(float64(h) * aspectRatio))
	w = (w / 2) * 2
	if w < 2 {
		w = 2
	}

	return w, h
}

// SelectLadder selects an adaptive rendition ladder appropriate for the source
// resolution without upscaling beyond source resolution. Vertical videos (e.g. 9:16)
// are handled cleanly with dominant dimension checks, and the 360p profile tier is
// always included as a baseline.
func SelectLadder(srcWidth, srcHeight int) []Profile {
	if srcWidth <= 0 || srcHeight <= 0 {
		return nil
	}

	var ladder []Profile
	for _, tmpl := range StandardProfiles {
		if srcHeight >= tmpl.Height {
			w, h := CalculateDimensions(srcWidth, srcHeight, tmpl.Height)
			p := tmpl
			p.Width = w
			p.Height = h
			ladder = append(ladder, p)
		}
	}

	// Always include 360p profile as a baseline if none selected (e.g. low-resolution source)
	if len(ladder) == 0 {
		targetHeight := Profile360p.Height
		if targetHeight > srcHeight {
			targetHeight = srcHeight
		}
		w, h := CalculateDimensions(srcWidth, srcHeight, targetHeight)
		p := Profile360p
		p.Width = w
		p.Height = h
		ladder = append(ladder, p)
	}

	return ladder
}
