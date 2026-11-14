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

        const videoElement = document.createElement('video-js');
        videoElement.classList.add('vjs-big-play-centered', 'vjs-16-9');
        videoRef.current.appendChild(videoElement);

        const player = (playerRef.current = videojs(
            videoElement,
            {
                controls: controls ?? true,
                autoplay: autoplay ?? false,
                muted: muted ?? false,
                loop: loop ?? false,
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
