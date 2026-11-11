import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { useRef, useState } from 'react';
import ReactCrop, { type Crop, type PixelCrop, centerCrop, makeAspectCrop } from 'react-image-crop';
import 'react-image-crop/dist/ReactCrop.css';

interface ImageCropModalProps {
    isOpen: boolean;
    onClose: () => void;
    selectedImage: string | null;
    onCropComplete: (croppedImageData: string) => void;
}

const CROP_SIZES = [
    { label: 'Square (1:1)', aspect: 1 },
    { label: 'Portrait (3:4)', aspect: 3 / 4 },
    { label: 'Landscape (4:3)', aspect: 4 / 3 },
    { label: 'Free', aspect: undefined },
];

export default function ImageCropModal({ isOpen, onClose, selectedImage, onCropComplete }: Readonly<ImageCropModalProps>) {
    const [crop, setCrop] = useState<Crop>();
    const [completedCrop, setCompletedCrop] = useState<PixelCrop>();
    const [selectedAspect, setSelectedAspect] = useState<number | undefined>(1); // Default to square
    const imgRef = useRef<HTMLImageElement>(null);
    const { __ } = useLocalizer();

    const onImageLoad = (e: React.SyntheticEvent<HTMLImageElement>) => {
        const { width, height } = e.currentTarget;

        const initialCrop = centerCrop(
            makeAspectCrop(
                {
                    unit: '%',
                    width: 90,
                },
                selectedAspect || 1,
                width,
                height,
            ),
            width,
            height,
        );

        setCrop(initialCrop);

        // Convert to pixel crop and set as completed crop so user can upload without interaction
        const pixelCrop: PixelCrop = {
            x: (initialCrop.x / 100) * width,
            y: (initialCrop.y / 100) * height,
            width: (initialCrop.width / 100) * width,
            height: (initialCrop.height / 100) * height,
            unit: 'px',
        };
        setCompletedCrop(pixelCrop);
    };

    const getCroppedImg = async (): Promise<string> => {
        const image = imgRef.current;
        const crop = completedCrop;

        if (!image) {
            throw new Error('Image reference not found');
        }

        if (!crop || crop.width === 0 || crop.height === 0) {
            throw new Error('Invalid crop area - please adjust the crop selection');
        }

        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');

        if (!ctx) {
            throw new Error('No 2d context');
        }

        const scaleX = image.naturalWidth / image.width;
        const scaleY = image.naturalHeight / image.height;

        canvas.width = crop.width;
        canvas.height = crop.height;

        ctx.drawImage(image, crop.x * scaleX, crop.y * scaleY, crop.width * scaleX, crop.height * scaleY, 0, 0, crop.width, crop.height);

        return new Promise((resolve) => {
            canvas.toBlob(
                (blob) => {
                    if (!blob) {
                        throw new Error('Canvas is empty');
                    }
                    const reader = new FileReader();
                    reader.onload = () => resolve(reader.result as string);
                    reader.readAsDataURL(blob);
                },
                'image/jpeg',
                0.95,
            );
        });
    };

    const handleSave = async () => {
        try {
            if (!completedCrop || completedCrop.width === 0 || completedCrop.height === 0) {
                console.error('Invalid crop selection');
                return;
            }

            const croppedImageData = await getCroppedImg();
            onCropComplete(croppedImageData);
            onClose();
        } catch (error) {
            console.error('Error cropping image:', error);
        }
    };

    const handleAspectChange = (aspect: number | undefined) => {
        setSelectedAspect(aspect);

        if (imgRef.current) {
            const { width, height } = imgRef.current;
            const newCrop = centerCrop(
                makeAspectCrop(
                    {
                        unit: '%',
                        width: 90,
                    },
                    aspect || 1,
                    width,
                    height,
                ),
                width,
                height,
            );
            setCrop(newCrop);

            // Also update completed crop so user can upload without interaction
            const pixelCrop: PixelCrop = {
                x: (newCrop.x / 100) * width,
                y: (newCrop.y / 100) * height,
                width: (newCrop.width / 100) * width,
                height: (newCrop.height / 100) * height,
                unit: 'px',
            };
            setCompletedCrop(pixelCrop);
        }
    };

    const handleClose = () => {
        setCrop(undefined);
        setCompletedCrop(undefined);
        onClose();
    };

    if (!selectedImage) return null;

    return (
        <Dialog open={isOpen} onOpenChange={handleClose}>
            <DialogContent className="max-w-4xl">
                <DialogHeader>
                    <DialogTitle>{__('Crop Profile Photo')}</DialogTitle>
                </DialogHeader>

                <div className="space-y-4">
                    {/* Crop Size Selection */}
                    <div className="flex flex-wrap gap-2">
                        {CROP_SIZES.map((size) => (
                            <Button
                                key={size.label}
                                variant={selectedAspect === size.aspect ? 'default' : 'outline'}
                                size="sm"
                                onClick={() => handleAspectChange(size.aspect)}
                            >
                                {size.label}
                            </Button>
                        ))}
                    </div>

                    {/* Image Cropping Area */}
                    <div className="flex justify-center">
                        <ReactCrop
                            crop={crop}
                            onChange={(_, percentCrop) => setCrop(percentCrop)}
                            onComplete={(c) => setCompletedCrop(c)}
                            aspect={selectedAspect}
                            className="max-w-full"
                        >
                            <img
                                ref={imgRef}
                                alt="Crop preview"
                                src={selectedImage}
                                style={{ maxHeight: '400px', maxWidth: '100%' }}
                                onLoad={onImageLoad}
                            />
                        </ReactCrop>
                    </div>

                    {/* Action Buttons */}
                    <div className="flex justify-end gap-2">
                        <Button variant="outline" onClick={handleClose}>
                            Cancel
                        </Button>
                        <Button onClick={handleSave} disabled={!completedCrop || completedCrop.width === 0 || completedCrop.height === 0}>
                            Save Crop
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
