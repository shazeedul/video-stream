import { VideoPlayer } from '@/components/player/video-player';
import type { Video } from '@/types/video';
import { Head } from '@inertiajs/react';

interface EmbedVideoProps {
    readonly video: Video;
    readonly playback_url: string;
    readonly autoplay?: boolean;
    readonly muted?: boolean;
    readonly loop?: boolean;
}

export default function EmbedVideo({ video, playback_url, autoplay = false, muted = false, loop = false }: EmbedVideoProps) {
    return (
        <>
            <Head title={video.title} />
            <div className="fixed inset-0 m-0 flex size-full items-center justify-center overflow-hidden bg-black p-0">
                <VideoPlayer
                    src={playback_url}
                    poster={video.thumbnail_path ?? undefined}
                    title={video.title}
                    autoplay={autoplay}
                    muted={muted}
                    loop={loop}
                    className="size-full"
                />
            </div>
        </>
    );
}
