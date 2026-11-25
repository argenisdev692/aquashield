/**
 * AvatarCropperField — avatar picker with a real crop step.
 *
 * The file is not handed to the form until the operator confirms the crop.
 * The output is a square JPEG (the avatar CSS clips it to a circle), capped at
 * `outputSize` so an 8 MP phone photo never reaches the upload endpoint.
 */
import { useEffect, useRef, useState } from 'react';
import Cropper from 'react-easy-crop';
import type { Area, Point } from 'react-easy-crop';
import 'react-easy-crop/react-easy-crop.css';
import { ImagePlus, RotateCcw, Trash2 } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar';
import { Button } from '@/Components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import FieldShell from './FieldShell';

interface AvatarCropperFieldProps {
    label: string;
    name: string;
    value: File | null;
    onChange: (file: File | null) => void;
    onBlur?: () => void;
    currentUrl?: string | null;
    fallback?: string;
    shape?: 'circle' | 'square';
    outputSize?: number;
    maxSizeMb?: number;
    error?: string;
    hint?: string;
    required?: boolean;
    disabled?: boolean;
}

const ACCEPTED_IMAGE_TYPES = ['image/png', 'image/jpeg', 'image/webp'];

const loadImage = (src: string): Promise<HTMLImageElement> =>
    new Promise((resolve, reject) => {
        const image = new Image();
        image.onload = () => resolve(image);
        image.onerror = () => reject(new Error('The selected image could not be read.'));
        image.src = src;
    });

async function cropToFile(
    src: string,
    area: Area,
    outputSize: number,
    fileName: string,
): Promise<File> {
    const image = await loadImage(src);
    const canvas = document.createElement('canvas');
    canvas.width = outputSize;
    canvas.height = outputSize;

    const context = canvas.getContext('2d');

    if (!context) {
        throw new Error('This browser cannot crop images.');
    }

    context.drawImage(image, area.x, area.y, area.width, area.height, 0, 0, outputSize, outputSize);

    const blob = await new Promise<Blob | null>((resolve) =>
        canvas.toBlob(resolve, 'image/jpeg', 0.9),
    );

    if (!blob) {
        throw new Error('The cropped image could not be created.');
    }

    return new File([blob], fileName.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' });
}

export default function AvatarCropperField({
    label,
    name,
    value,
    onChange,
    onBlur,
    currentUrl = null,
    fallback = '?',
    shape = 'circle',
    outputSize = 512,
    maxSizeMb = 5,
    error,
    hint,
    required = false,
    disabled = false,
}: AvatarCropperFieldProps) {
    const fileInput = useRef<HTMLInputElement>(null);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);
    const [sourceUrl, setSourceUrl] = useState<string | null>(null);
    const [sourceName, setSourceName] = useState('avatar.jpg');
    const [cropOpen, setCropOpen] = useState(false);
    const [crop, setCrop] = useState<Point>({ x: 0, y: 0 });
    const [zoom, setZoom] = useState(1);
    const [croppedArea, setCroppedArea] = useState<Area | null>(null);
    const [cropError, setCropError] = useState<string | null>(null);
    const [working, setWorking] = useState(false);

    useEffect(() => {
        if (!value) {
            setPreviewUrl(null);

            return;
        }

        const url = URL.createObjectURL(value);
        setPreviewUrl(url);

        return () => URL.revokeObjectURL(url);
    }, [value]);

    useEffect(
        () => () => {
            if (sourceUrl) {
                URL.revokeObjectURL(sourceUrl);
            }
        },
        [sourceUrl],
    );

    const selectFile = (file: File | undefined) => {
        setCropError(null);

        if (!file) {
            return;
        }

        if (!ACCEPTED_IMAGE_TYPES.includes(file.type)) {
            setCropError('Choose a PNG, JPEG or WebP image.');
            return;
        }

        if (file.size > maxSizeMb * 1024 * 1024) {
            setCropError(`The image must be smaller than ${maxSizeMb} MB.`);
            return;
        }

        if (sourceUrl) {
            URL.revokeObjectURL(sourceUrl);
        }

        setSourceUrl(URL.createObjectURL(file));
        setSourceName(file.name);
        setCrop({ x: 0, y: 0 });
        setZoom(1);
        setCroppedArea(null);
        setCropOpen(true);
    };

    const closeCrop = (force = false) => {
        if (working && !force) {
            return;
        }

        setCropOpen(false);
        setCropError(null);

        if (sourceUrl) {
            URL.revokeObjectURL(sourceUrl);
            setSourceUrl(null);
        }
    };

    const resetCrop = () => {
        setCrop({ x: 0, y: 0 });
        setZoom(1);
        setCroppedArea(null);
        setCropError(null);
    };

    const confirmCrop = async () => {
        if (!sourceUrl || !croppedArea) {
            return;
        }

        setWorking(true);
        setCropError(null);

        try {
            const file = await cropToFile(sourceUrl, croppedArea, outputSize, sourceName);
            onChange(file);
            onBlur?.();
            closeCrop(true);
        } catch (caught) {
            setCropError(
                caught instanceof Error ? caught.message : 'The image could not be cropped.',
            );
        } finally {
            setWorking(false);
        }
    };

    return (
        <FieldShell label={label} name={name} error={error} hint={hint} required={required}>
            {({ id, describedBy, invalid }) => (
                <>
                    <input
                        ref={fileInput}
                        id={id}
                        type="file"
                        accept="image/png,image/jpeg,image/webp"
                        className="sr-only"
                        disabled={disabled}
                        aria-invalid={invalid || undefined}
                        aria-describedby={describedBy}
                        onChange={(event) => {
                            selectFile(event.target.files?.[0]);
                            event.target.value = '';
                        }}
                    />

                    <div className="flex flex-wrap items-center gap-4 rounded-xl border border-surface-200 p-3 dark:border-surface-700">
                        <Avatar className="size-16">
                            <AvatarImage src={previewUrl ?? currentUrl ?? undefined} alt="" />
                            <AvatarFallback>{fallback}</AvatarFallback>
                        </Avatar>

                        <div className="flex min-w-0 flex-1 flex-wrap gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={disabled || working}
                                onClick={() => fileInput.current?.click()}
                            >
                                <ImagePlus className="mr-2 h-4 w-4" aria-hidden="true" />
                                Choose image
                            </Button>

                            {(value || currentUrl) && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={disabled || working}
                                    onClick={() => {
                                        onChange(null);
                                        onBlur?.();
                                    }}
                                >
                                    <Trash2 className="mr-2 h-4 w-4" aria-hidden="true" />
                                    Remove
                                </Button>
                            )}
                        </div>
                    </div>

                    {cropError && !cropOpen && (
                        <p role="alert" className="text-xs font-medium text-aq-danger-strong">
                            {cropError}
                        </p>
                    )}

                    <Dialog open={cropOpen} onOpenChange={(next) => !next && closeCrop()}>
                        <DialogContent className="sm:max-w-lg" showCloseButton={!working}>
                            <div className="flex flex-col gap-1.5">
                                <DialogTitle>Crop avatar</DialogTitle>
                                <DialogDescription>
                                    Drag the image and zoom until the face is centred.
                                </DialogDescription>
                            </div>

                            <div className="relative h-72 overflow-hidden rounded-xl bg-surface-950">
                                {sourceUrl && (
                                    <Cropper
                                        image={sourceUrl}
                                        crop={crop}
                                        zoom={zoom}
                                        aspect={1}
                                        cropShape={shape === 'circle' ? 'round' : 'rect'}
                                        showGrid={false}
                                        // The CSS is imported above, so skip the
                                        // library's runtime <style> injection
                                        // (it would need the CSP nonce).
                                        disableAutomaticStylesInjection
                                        onCropChange={setCrop}
                                        onZoomChange={setZoom}
                                        onCropComplete={(_area, pixels) => setCroppedArea(pixels)}
                                    />
                                )}
                            </div>

                            <label className="flex items-center gap-3 text-sm text-surface-600 dark:text-surface-300">
                                <RotateCcw className="h-4 w-4" aria-hidden="true" />
                                Zoom
                                <Input
                                    type="range"
                                    min={1}
                                    max={3}
                                    step={0.01}
                                    value={zoom}
                                    className="h-8 flex-1"
                                    onChange={(event) => setZoom(Number(event.target.value))}
                                />
                            </label>

                            {cropError && (
                                <p
                                    role="alert"
                                    className="text-sm font-medium text-aq-danger-strong"
                                >
                                    {cropError}
                                </p>
                            )}

                            <div className="flex items-center justify-between gap-2">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={resetCrop}
                                    disabled={working}
                                >
                                    <RotateCcw className="mr-2 h-4 w-4" aria-hidden="true" />
                                    Reset
                                </Button>

                                <div className="flex gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => closeCrop()}
                                        disabled={working}
                                    >
                                        Cancel
                                    </Button>
                                    <Button
                                        type="button"
                                        onClick={confirmCrop}
                                        disabled={working || !croppedArea}
                                    >
                                        {working ? 'Cropping…' : 'Use image'}
                                    </Button>
                                </div>
                            </div>
                        </DialogContent>
                    </Dialog>
                </>
            )}
        </FieldShell>
    );
}
