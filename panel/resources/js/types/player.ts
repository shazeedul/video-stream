import type Player from 'video.js/dist/types/player';

export interface VideoPlayerProps {
    readonly src: string;
    readonly poster?: string | null;
    readonly title?: string | null;
    readonly autoplay?: boolean;
    readonly controls?: boolean;
    readonly muted?: boolean;
    readonly loop?: boolean;
    readonly className?: string;
    readonly onReady?: (player: Player) => void;
    readonly onPlay?: () => void;
    readonly onPause?: () => void;
    readonly onEnded?: () => void;
}
