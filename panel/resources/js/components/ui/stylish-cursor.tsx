import { useEffect, useRef } from 'react';

export default function StylishCursor() {
    const dotRef = useRef<HTMLDivElement | null>(null);
    const outlineRef = useRef<HTMLDivElement | null>(null);

    useEffect(() => {
        const handleMove = (e: MouseEvent) => {
            const x = e.clientX;
            const y = e.clientY;
            if (dotRef.current && outlineRef.current) {
                dotRef.current.style.transform = `translate3d(${x - 4}px, ${y - 4}px, 0)`;
                outlineRef.current.style.transform = `translate3d(${x - 20}px, ${y - 20}px, 0)`;
            }
        };

        const handleDown = () => {
            if (dotRef.current) dotRef.current.style.transform += ' scale(0.85)';
            if (outlineRef.current) outlineRef.current.style.transform += ' scale(0.95)';
        };
        const handleUp = () => {
            if (dotRef.current) dotRef.current.style.transform = dotRef.current.style.transform.replace(' scale(0.85)', '');
            if (outlineRef.current) outlineRef.current.style.transform = outlineRef.current.style.transform.replace(' scale(0.95)', '');
        };

        window.addEventListener('mousemove', handleMove);
        window.addEventListener('mousedown', handleDown);
        window.addEventListener('mouseup', handleUp);

        // enlarge cursor on links and buttons
        const interactive = Array.from(document.querySelectorAll('a, button, input, textarea, .cursor-pointer'));
        interactive.forEach((el) => {
            el.addEventListener('mouseenter', () => {
                if (outlineRef.current) outlineRef.current.style.transform += ' scale(1.2)';
            });
            el.addEventListener('mouseleave', () => {
                if (outlineRef.current) outlineRef.current.style.transform = outlineRef.current.style.transform.replace(' scale(1.2)', '');
            });
        });

        return () => {
            window.removeEventListener('mousemove', handleMove);
            window.removeEventListener('mousedown', handleDown);
            window.removeEventListener('mouseup', handleUp);
            interactive.forEach((el) => {
                el.removeEventListener('mouseenter', () => undefined);
                el.removeEventListener('mouseleave', () => undefined);
            });
        };
    }, []);

    return (
        <>
            <div
                ref={outlineRef}
                style={{
                    position: 'fixed',
                    top: 0,
                    left: 0,
                    width: 40,
                    height: 40,
                    borderRadius: '999px',
                    border: '2px solid rgba(59,130,246,0.6)',
                    pointerEvents: 'none',
                    transform: 'translate3d(-9999px, -9999px, 0)',
                    transition: 'transform 120ms linear, opacity 120ms linear',
                    zIndex: 9999,
                    mixBlendMode: 'normal',
                }}
            />
            <div
                ref={dotRef}
                style={{
                    position: 'fixed',
                    top: 0,
                    left: 0,
                    width: 8,
                    height: 8,
                    borderRadius: '999px',
                    background: 'rgba(59,130,246,0.95)',
                    pointerEvents: 'none',
                    transform: 'translate3d(-9999px, -9999px, 0)',
                    transition: 'transform 80ms linear, opacity 80ms linear',
                    zIndex: 10000,
                }}
            />
        </>
    );
}
