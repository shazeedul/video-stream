import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { destroy as destroyRoute, moveDown as moveDownRoute, moveUp as moveUpRoute, update as updateRoute } from '@/routes/admin/system-settings';
import type { SystemSetting } from '@/types/system-setting';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { Can } from '@devwizard/laravel-react-permissions';
import { router } from '@inertiajs/react';
import { ChevronDown, ChevronUp, Copy, Eye, Lock, Pencil, Save, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { SettingField } from './SettingField';

interface SettingCardProps {
    setting: SystemSetting;
    onChange: (value: string | File | null) => void;
    onEdit?: () => void;
    canUpdate?: boolean;
    canDelete?: boolean;
}

export function SettingCard({ setting, onChange, onEdit, canUpdate = false, canDelete = false }: SettingCardProps) {
    const { __ } = useLocalizer();
    const [isSaving, setIsSaving] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);
    const [showDeleteDialog, setShowDeleteDialog] = useState(false);
    const [fileToUpload, setFileToUpload] = useState<File | null>(null);
    const [shouldClearFile, setShouldClearFile] = useState(false);

    const handleCopyKey = () => {
        navigator.clipboard.writeText(setting.key);
        toast.success(`${setting.key} copied to clipboard`);
    };

    const handleValueChange = (value: string | File | null) => {
        if (value instanceof File) {
            setFileToUpload(value);
            setShouldClearFile(false);
        } else if (value === null) {
            setFileToUpload(null);
            setShouldClearFile(true);
        } else {
            setShouldClearFile(false);
        }
        onChange(value);
    };

    const handleSave = () => {
        if (!canUpdate) return;

        setIsSaving(true);

        const data: Record<string, File | string | undefined> = fileToUpload
            ? { file: fileToUpload }
            : shouldClearFile
              ? { value: '' }
              : { value: setting.value };

        router.post(
            updateRoute({ systemSetting: setting.id }).url,
            { ...data, _method: 'PUT' },
            {
                forceFormData: true,
                preserveScroll: true,
                onFinish: () => {
                    setIsSaving(false);
                    setFileToUpload(null);
                    setShouldClearFile(false);
                },
            },
        );
    };

    const handleDelete = () => {
        if (!canDelete) return;

        setIsDeleting(true);
        router.delete(destroyRoute({ systemSetting: setting.id }).url, {
            onFinish: () => {
                setIsDeleting(false);
                setShowDeleteDialog(false);
            },
        });
    };

    const handleDeleteClick = () => {
        setShowDeleteDialog(true);
    };

    const handleMoveUp = () => {
        if (!canUpdate) return;
        router.post(moveUpRoute({ systemSetting: setting.id }).url);
    };

    const handleMoveDown = () => {
        if (!canUpdate) return;
        router.post(moveDownRoute({ systemSetting: setting.id }).url);
    };

    return (
        <Card>
            <CardHeader className="pb-3">
                <div className="flex items-start justify-between">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2">
                            <CardTitle className="text-base font-medium">{setting.name}</CardTitle>
                            <div className="flex gap-1">
                                {setting.is_encrypted && (
                                    <Badge variant="secondary" className="gap-1">
                                        <Lock className="h-3 w-3" />
                                        {__('Encrypted')}
                                    </Badge>
                                )}
                                {setting.is_public && (
                                    <Badge variant="outline" className="gap-1">
                                        <Eye className="h-3 w-3" />
                                        {__('Public')}
                                    </Badge>
                                )}
                            </div>
                        </div>
                        <CardDescription className="flex items-center gap-2 text-xs">
                            <code className="rounded bg-muted px-1.5 py-0.5 font-mono">{setting.key}</code>
                            <Button variant="ghost" size="sm" className="h-auto p-1" onClick={handleCopyKey} title={__('Copy key')}>
                                <Copy className="h-3 w-3" />
                            </Button>
                        </CardDescription>
                        {setting.remarks && <p className="text-xs text-muted-foreground">{setting.remarks}</p>}
                    </div>
                    <div className="flex gap-1">
                        <Can permission="settings.edit">
                            <Button variant="ghost" size="sm" onClick={onEdit} title={__('Edit setting')}>
                                <Pencil className="h-4 w-4" />
                            </Button>
                        </Can>
                        {canUpdate && (
                            <>
                                <Button variant="ghost" size="sm" onClick={handleMoveUp} title={__('Move up')}>
                                    <ChevronUp className="h-4 w-4" />
                                </Button>
                                <Button variant="ghost" size="sm" onClick={handleMoveDown} title={__('Move down')}>
                                    <ChevronDown className="h-4 w-4" />
                                </Button>
                                <Button variant="ghost" size="sm" onClick={handleSave} disabled={isSaving} title={__('Save')}>
                                    <Save className="h-4 w-4" />
                                </Button>
                            </>
                        )}
                        {canDelete && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={handleDeleteClick}
                                disabled={isDeleting}
                                title={__('Delete')}
                                className="text-destructive hover:text-destructive"
                            >
                                <Trash2 className="h-4 w-4" />
                            </Button>
                        )}
                    </div>
                </div>
            </CardHeader>
            <CardContent>
                <SettingField setting={setting} onChange={handleValueChange} />
            </CardContent>

            {/* Delete Confirmation Dialog */}
            <Dialog open={showDeleteDialog} onOpenChange={setShowDeleteDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{__('Delete Setting')}</DialogTitle>
                        <DialogDescription>
                            {__('Are you sure you want to delete')} <strong>"{setting.name}"</strong>?
                            <br />
                            <code className="mt-2 block rounded bg-muted px-2 py-1 text-xs">{setting.key}</code>
                            <br />
                            {__('This action cannot be undone.')}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setShowDeleteDialog(false)} disabled={isDeleting}>
                            {__('Cancel')}
                        </Button>
                        <Button variant="destructive" onClick={handleDelete} disabled={isDeleting}>
                            {isDeleting ? __('Deleting...') : __('Delete')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </Card>
    );
}
