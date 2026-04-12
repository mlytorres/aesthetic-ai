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
                    video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } },
                    audio: false
                });
                if (videoRef.current) {
                    videoRef.current.srcObject = stream;
                    videoRef.current.play();
                }
            } catch {
                setError('Unable to access camera. Please check browser permissions or use the upload button.');
            }
        };

        void startCamera();

        return () => {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
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
                        const file = new File([blob], `${type}_cam.jpg`, { type: 'image/jpeg' });
                        onCapture(file);
                    }
                },
                'image/jpeg',
                0.9
            );
        }
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/90 p-4 backdrop-blur-sm">
            <div className="w-full max-w-2xl bg-[#111118] rounded-2xl overflow-hidden border border-white/10 flex flex-col shadow-2xl">
                <div className="flex items-center justify-between p-4 border-b border-white/10">
                    <h3 className="font-bold text-[#F5F0E8]">Take Photo</h3>
                    <button onClick={onCancel} className="text-[#9B9B8E] hover:text-white transition-colors text-xl leading-none">
                        &times;
                    </button>
                </div>
                
                <div className="relative aspect-video bg-black flex items-center justify-center">
                    {error ? (
                        <p className="text-red-400 text-sm text-center px-4">{error}</p>
                    ) : (
                        <video ref={videoRef} className="w-full h-full object-cover scale-x-[-1]" playsInline muted />
                    )}
                    <canvas ref={canvasRef} className="hidden" />
                </div>
                
                <div className="p-6 flex justify-center bg-[#0A0A0F]">
                    <button 
                        onClick={capture}
                        disabled={!!error}
                        title="Snap Photo"
                        className="w-16 h-16 rounded-full border-4 border-[#0E9E8E]/50 bg-[#0E9E8E] hover:bg-[#a8883e] hover:scale-105 transition-all flex items-center justify-center disabled:opacity-50 disabled:hover:scale-100"
                    >
                    </button>
                </div>
            </div>
        </div>
    );
};
