import { useEffect, useRef, useState } from 'react';
import type { FC } from 'react';
import type { PhotoType } from '@/types/intake';

interface Props {
    type: PhotoType;
    onCapture: (file: File) => void;
    onCancel: () => void;
}

export const WebcamCapture: FC<Props> = ({ type, onCapture, onCancel }) => {
    const videoRef = useRef<HTMLVideoElement>(null);
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        let stream: MediaStream | null = null;

        const startCamera = async () => {
            try {
                stream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: 'user',
                        width: { ideal: 1280 },
                        height: { ideal: 720 },
                    },
                    audio: false,
                });

                if (videoRef.current) {
                    videoRef.current.srcObject = stream;
                    videoRef.current.play();
                }
            } catch {
                setError(
                    'Unable to access camera. Please check browser permissions or use the upload button.',
                );
            }
        };

        void startCamera();

        return () => {
            if (stream) {
                stream.getTracks().forEach((track) => track.stop());
            }
        };
    }, []);

    const capture = () => {
        if (videoRef.current && canvasRef.current) {
            const context = canvasRef.current.getContext('2d');

            if (!context) {
                return;
            }

            canvasRef.current.width = videoRef.current.videoWidth;
            canvasRef.current.height = videoRef.current.videoHeight;
            context.drawImage(videoRef.current, 0, 0);

            canvasRef.current.toBlob(
                (blob) => {
                    if (blob) {
                        const file = new File([blob], `${type}_cam.jpg`, {
                            type: 'image/jpeg',
                        });
                        onCapture(file);
                    }
                },
                'image/jpeg',
                0.9,
            );
        }
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/90 p-4 backdrop-blur-sm">
            <div className="flex w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-[var(--intake-border)] bg-[var(--intake-surface)] shadow-2xl">
                <div className="flex items-center justify-between border-b border-[var(--intake-border)] p-4">
                    <h3 className="font-bold text-[var(--intake-fg)]">
                        Take Photo
                    </h3>
                    <button
                        onClick={onCancel}
                        className="text-xl leading-none text-[var(--intake-muted)] transition-colors hover:text-white"
                    >
                        &times;
                    </button>
                </div>

                <div className="relative flex aspect-video items-center justify-center bg-black">
                    {error ? (
                        <p className="px-4 text-center text-sm text-red-400">
                            {error}
                        </p>
                    ) : (
                        <video
                            ref={videoRef}
                            className="h-full w-full scale-x-[-1] object-cover"
                            playsInline
                            muted
                        />
                    )}
                    <canvas ref={canvasRef} className="hidden" />
                </div>

                <div className="flex justify-center bg-[var(--intake-bg)] p-6">
                    <button
                        onClick={capture}
                        disabled={!!error}
                        title="Snap Photo"
                        className="flex h-16 w-16 items-center justify-center rounded-full border-4 border-[#0E9E8E]/50 bg-[#0E9E8E] transition-all hover:scale-105 hover:bg-[#a8883e] disabled:opacity-50 disabled:hover:scale-100"
                    ></button>
                </div>
            </div>
        </div>
    );
};
