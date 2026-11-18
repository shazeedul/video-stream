import { useEffect, useRef } from 'react';
import videojs from 'video.js';
import type Player from 'video.js/dist/types/player';
import 'video.js/dist/video-js.css';

import { cn } from '@/lib/utils';
import type { VideoPlayerProps } from '@/types/player';

export function VideoPlayer({
    src,
    poster,
    title,
    autoplay = false,
    controls = true,
    muted = false,
    loop = false,
    className,
    onReady,
    onPlay,
    onPause,
    onEnded,
}: VideoPlayerProps) {
    const videoRef = useRef<HTMLDivElement | null>(null);
    const playerRef = useRef<Player | null>(null);

    const onReadyRef = useRef(onReady);
    const onPlayRef = useRef(onPlay);
    const onPauseRef = useRef(onPause);
    const onEndedRef = useRef(onEnded);

    const prevSrcRef = useRef(src);
    const prevPosterRef = useRef(poster);

    useEffect(() => {
        onReadyRef.current = onReady;
        onPlayRef.current = onPlay;
        onPauseRef.current = onPause;
        onEndedRef.current = onEnded;
    });

    useEffect(() => {
        if (!videoRef.current) {
            return;
        }

        // Configure VHS buffering for YouTube-like aggressive preloading & smooth quality transitions
        const vhsGlobal = (videojs as unknown as { Vhs?: Record<string, unknown> }).Vhs;
        if (vhsGlobal) {
            vhsGlobal.GOAL_BUFFER_LENGTH = 60; // Buffer up to 60s ahead
            vhsGlobal.MAX_GOAL_BUFFER_LENGTH = 120; // Max buffer 120s ahead
            vhsGlobal.BUFFER_LOW_WATER_LINE = 10;
        }

        const videoElement = document.createElement('video-js');
        videoElement.classList.add('vjs-big-play-centered', 'vjs-16-9');
        videoElement.setAttribute('preload', 'auto');
        videoElement.setAttribute('playsinline', 'true');
        videoRef.current.appendChild(videoElement);

        const player = (playerRef.current = videojs(
            videoElement,
            {
                controls: controls ?? true,
                autoplay: autoplay ?? false,
                muted: muted ?? false,
                loop: loop ?? false,
                preload: 'auto',
                responsive: true,
                fluid: true,
                playbackRates: [0.5, 0.75, 1, 1.25, 1.5, 2],
                sources: [
                    {
                        src,
                        type: 'application/x-mpegURL',
                    },
                ],
                poster: poster || undefined,
                html5: {
                    vhs: {
                        overrideNative: true,
                        goalBufferLength: 60,
                        maxGoalBufferLength: 120,
                        bufferLowWaterLine: 10,
                        smoothQualityChange: true,
                        handlePartialData: true,
                    },
                },
            },
            () => {
                onReadyRef.current?.(player);
            },
        ));

        player.on('play', () => {
            onPlayRef.current?.();
        });

        player.on('pause', () => {
            onPauseRef.current?.();
        });

        player.on('ended', () => {
            onEndedRef.current?.();
        });

        return () => {
            if (playerRef.current && !playerRef.current.isDisposed()) {
                playerRef.current.dispose();
                playerRef.current = null;
            }
        };
    }, []);

    // Dynamically update source and poster when props change
    useEffect(() => {
        const player = playerRef.current;
        if (!player || player.isDisposed()) {
            return;
        }

        if (prevSrcRef.current !== src) {
            prevSrcRef.current = src;
            player.src({ src, type: 'application/x-mpegURL' });
        }

        if (prevPosterRef.current !== poster) {
            prevPosterRef.current = poster;
            if (poster) {
                player.poster(poster);
            }
        }
    }, [src, poster]);

    // Dynamically update control states if props change
    useEffect(() => {
        const player = playerRef.current;
        if (!player || player.isDisposed()) {
            return;
        }

        player.controls(controls ?? true);
        player.muted(muted ?? false);
        player.loop(loop ?? false);
    }, [controls, muted, loop]);

    return (
        <div data-vjs-player className={cn('aspect-video w-full overflow-hidden rounded-lg bg-black', className)} title={title ?? undefined}>
            <div ref={videoRef} className="h-full w-full" />
        </div>
    );
}

export default VideoPlayer;
