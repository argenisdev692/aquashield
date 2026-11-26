/**
 * CompanyBrandingPanel — the four brand assets, uploaded on their own path.
 *
 * Text fields save with the page form; assets are multipart writes to
 * `/company-data/assets/{asset}` and the server answers with every resolved
 * URL. That is why the page owns the asset controller through
 * `useCompanyAssets` and this panel never rides the Inertia form: one failed
 * logo upload must not throw away unsaved text edits, and a logo change must
 * not submit the whole record.
 */
import { useEffect, useState } from 'react';
import { ImageOff, Info, PenLine, Trash2 } from 'lucide-react';
import AppButton from '@/Components/Forms/AppButton';
import FileDropzone from '@/Components/Forms/FileDropzone';
import SignatureField from '@/Components/Forms/SignatureField';
import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Tag } from '@/Components/ui/tag';
import { cn } from '@/lib/utils';
import { signatureDataUrlToFile } from '@/Modules/CompanyData/hooks/useCompanyAssets';
import type {
    CompanyAssetKind,
    CompanyAssets,
    CompanyAssetsController,
} from '@/Modules/CompanyData/hooks/useCompanyAssets';

interface CompanyBrandingPanelProps {
    controller: CompanyAssetsController;
    canUpdate: boolean;
}

interface AssetDefinition {
    kind: CompanyAssetKind;
    label: string;
    description: string;
    url: (assets: CompanyAssets) => string | null;
    /** Backdrop the asset is meant to be judged on. */
    previewClassName: string;
}

const ASSETS: readonly AssetDefinition[] = [
    {
        kind: 'logo',
        label: 'Primary logo',
        description: 'Used on light surfaces: invoices, documents and email.',
        url: (assets) => assets.logo_url,
        previewClassName: 'bg-surface-50 dark:bg-surface-100',
    },
    {
        kind: 'logo_white',
        label: 'White logo',
        description: 'Used on the dark shell, footers and dark emails.',
        url: (assets) => assets.logo_white_url,
        previewClassName: 'bg-surface-950',
    },
    {
        kind: 'mark',
        label: 'Brand mark',
        description: 'The compact square mark for the rail, favicons and app icons.',
        url: (assets) => assets.mark_url,
        previewClassName: 'bg-surface-50 dark:bg-surface-950/60',
    },
    {
        kind: 'signature',
        label: 'Authorised signature',
        description: 'Rendered on signed certificates and completion documents.',
        url: (assets) => assets.signature_url,
        previewClassName: 'bg-surface-50 dark:bg-surface-100',
    },
];

interface AssetCardProps {
    definition: AssetDefinition;
    assets: CompanyAssets;
    canUpdate: boolean;
    pending: CompanyAssetKind | null;
    onUpload: (kind: CompanyAssetKind, file: File) => Promise<boolean>;
    onRequestRemove: (definition: AssetDefinition) => void;
}

function AssetCard({
    definition,
    assets,
    canUpdate,
    pending,
    onUpload,
    onRequestRemove,
}: AssetCardProps) {
    const [files, setFiles] = useState<File[]>([]);
    const [notice, setNotice] = useState<string | null>(null);
    const [drawing, setDrawing] = useState(false);
    const [draft, setDraft] = useState<string | null>(null);
    const [previewFailed, setPreviewFailed] = useState(false);

    const url = definition.url(assets);
    const busy = pending !== null;
    const uploading = pending === definition.kind;
    const selected = files[0];
    const drawable = definition.kind === 'signature';

    // A signature is served through a signed URL that expires; a dead image
    // falls back to the "no file" state instead of a broken-image icon.
    useEffect(() => {
        setPreviewFailed(false);
    }, [url]);

    const upload = async (): Promise<void> => {
        if (selected === undefined) {
            return;
        }

        const succeeded = await onUpload(definition.kind, selected);

        if (succeeded) {
            setFiles([]);
            setNotice(`${definition.label} updated.`);
        }
    };

    /**
     * A drawn signature is a PNG data URL; the module transport turns it into
     * the file the asset endpoint validates and stores (the server keeps the
     * signature as a PNG so the transparency survives).
     */
    const saveSignature = async (): Promise<void> => {
        if (draft === null) {
            return;
        }

        const succeeded = await onUpload(definition.kind, signatureDataUrlToFile(draft));

        if (succeeded) {
            setDraft(null);
            setDrawing(false);
            setNotice(`${definition.label} updated.`);
        }
    };

    return (
        <div className="flex flex-col gap-3 rounded-xl border border-aq-shell-line bg-surface-50/40 p-4 dark:bg-surface-950/20">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <h3 className="font-heading text-sm font-semibold text-surface-800 dark:text-surface-100">
                        {definition.label}
                    </h3>
                    <p className="mt-0.5 text-xs text-surface-600 dark:text-surface-300">
                        {definition.description}
                    </p>
                </div>

                <Tag variant={url !== null ? 'success' : 'outline'}>
                    {url !== null ? 'Uploaded' : 'Missing'}
                </Tag>
            </div>

            <div
                className={cn(
                    'grid h-28 place-items-center overflow-hidden rounded-lg border border-aq-shell-line',
                    definition.previewClassName,
                )}
            >
                {url !== null && !previewFailed ? (
                    <img
                        src={url}
                        alt={`${definition.label} preview`}
                        className="max-h-24 max-w-full object-contain p-3"
                        onError={() => setPreviewFailed(true)}
                    />
                ) : (
                    <span className="flex flex-col items-center gap-1.5 text-xs text-surface-500 dark:text-surface-300">
                        <ImageOff className="size-5" aria-hidden="true" />
                        {url !== null ? 'Preview unavailable' : 'No file uploaded'}
                    </span>
                )}
            </div>

            {canUpdate && drawable && !drawing && (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="self-start"
                    disabled={busy}
                    onClick={() => setDrawing(true)}
                >
                    <PenLine className="size-3.5" aria-hidden="true" />
                    Draw signature
                </Button>
            )}

            {canUpdate && drawable && drawing && (
                <div className="flex flex-col gap-2">
                    <SignatureField
                        label="Draw the signature"
                        name="signature-draft"
                        value={draft}
                        onChange={setDraft}
                        hint="Draw with a mouse, finger or stylus. Saving replaces the current signature."
                        disabled={busy}
                    />

                    <div className="flex flex-wrap items-center gap-2">
                        <AppButton
                            type="button"
                            loading={uploading}
                            disabled={draft === null || busy}
                            onClick={() => void saveSignature()}
                        >
                            Save signature
                        </AppButton>

                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            disabled={busy}
                            onClick={() => {
                                setDraft(null);
                                setDrawing(false);
                            }}
                        >
                            Cancel
                        </Button>
                    </div>
                </div>
            )}

            {canUpdate && !drawing && (
                <>
                    <FileDropzone
                        label={`Replace ${definition.label.toLowerCase()}`}
                        name={definition.kind}
                        files={files}
                        onFilesChange={setFiles}
                        accept="image/png,image/jpeg,image/webp"
                        maxSizeMbByType={{ image: 10 }}
                        hint="PNG, JPEG or WebP. 16–4096 px per side."
                        onReject={() => setFiles([])}
                        disabled={busy}
                    />

                    <div className="flex flex-wrap items-center gap-2">
                        <AppButton
                            type="button"
                            loading={uploading}
                            disabled={selected === undefined || busy}
                            onClick={() => void upload()}
                        >
                            Upload
                        </AppButton>

                        {url !== null && (
                            <Button
                                type="button"
                                variant="destructive"
                                size="sm"
                                disabled={busy}
                                onClick={() => onRequestRemove(definition)}
                            >
                                <Trash2 className="size-3.5" aria-hidden="true" />
                                Remove
                            </Button>
                        )}
                    </div>
                </>
            )}

            {notice !== null && (
                <p role="status" className="text-xs text-aq-success">
                    {notice}
                </p>
            )}
        </div>
    );
}

export default function CompanyBrandingPanel({ controller, canUpdate }: CompanyBrandingPanelProps) {
    const { assets, pending, error, upload, remove } = controller;
    const [removeTarget, setRemoveTarget] = useState<AssetDefinition | null>(null);
    const [notice, setNotice] = useState<string | null>(null);

    const confirmRemove = async (): Promise<void> => {
        if (removeTarget === null) {
            return;
        }

        const label = removeTarget.label;
        const succeeded = await remove(removeTarget.kind);

        if (succeeded) {
            setRemoveTarget(null);
            setNotice(`${label} removed.`);
        }
    };

    const busy = pending !== null;

    return (
        <div className="flex flex-col gap-5">
            <div className="flex items-start gap-2 rounded-lg border border-aq-shell-line bg-surface-50/70 px-3 py-2.5 text-xs text-surface-600 dark:bg-surface-950/30 dark:text-surface-300">
                <Info className="mt-0.5 size-3.5 shrink-0 text-aq-accent" aria-hidden="true" />
                <p>
                    Assets save immediately through their own endpoint. The text fields on the other
                    tabs save with the button at the bottom of the page.
                </p>
            </div>

            {error !== null && (
                <p
                    role="alert"
                    className="rounded-lg border border-aq-danger/40 bg-aq-danger/5 px-3 py-2 text-sm text-aq-danger-strong"
                >
                    {error}
                </p>
            )}

            {notice !== null && (
                <p role="status" className="text-sm text-aq-success">
                    {notice}
                </p>
            )}

            <div className="grid gap-4 lg:grid-cols-2">
                {ASSETS.map((definition) => (
                    <AssetCard
                        key={definition.kind}
                        definition={definition}
                        assets={assets}
                        canUpdate={canUpdate}
                        pending={pending}
                        onUpload={upload}
                        onRequestRemove={(target) => {
                            setNotice(null);
                            setRemoveTarget(target);
                        }}
                    />
                ))}
            </div>

            {!canUpdate && (
                <p className="text-xs text-surface-500 dark:text-surface-300">
                    Brand assets are managed by a super admin. All four current files are visible
                    here.
                </p>
            )}

            <Dialog
                open={removeTarget !== null}
                onOpenChange={(open) => {
                    if (!open && !busy) {
                        setRemoveTarget(null);
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            Remove the {removeTarget?.label.toLowerCase() ?? 'asset'}?
                        </DialogTitle>
                        <DialogDescription>
                            The file is deleted from storage. The rest of the company profile is not
                            affected, and you can upload a replacement at any time.
                        </DialogDescription>
                    </DialogHeader>

                    {error !== null && (
                        <p
                            role="alert"
                            className="rounded-lg border border-aq-danger/40 bg-aq-danger/5 px-3 py-2 text-sm text-aq-danger-strong"
                        >
                            {error}
                        </p>
                    )}

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={busy}
                            onClick={() => setRemoveTarget(null)}
                        >
                            Keep it
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            disabled={busy}
                            onClick={() => void confirmRemove()}
                        >
                            <Trash2 className="size-3.5" aria-hidden="true" />
                            Remove file
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
