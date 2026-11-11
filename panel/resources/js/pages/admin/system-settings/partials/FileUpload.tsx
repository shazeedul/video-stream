import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatFileSize, validateFileSize, validateFileType } from '@/lib/file-validation';
import type { SystemSetting } from '@/types/system-setting';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { ExternalLink, File, X } from 'lucide-react';
import { useRef, useState } from 'react';

interface FileUploadProps {
    setting: SystemSetting;
    onChange: (file: File | null) => void;
}

export function FileUpload({ setting, onChange }: FileUploadProps) {
    const { __ } = useLocalizer();
    const [error, setError] = useState<string | null>(null);
    const [selectedFile, setSelectedFile] = useState<File | null>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);

    // Get validation rules from setting
    const rules = typeof setting.rules === 'object' && setting.rules !== null ? setting.rules : {};
    const accept = rules.accept as string | undefined;
    const maxSize = rules.maxsize as number | undefined; // Size in KB, undefined means unlimited

    const handleFileSelect = async (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;

        setError(null);

        // Validate file size only if maxSize is specified (maxSize is in KB)
        if (!validateFileSize(file, maxSize)) {
            setError(`File size must be less than ${formatFileSize(maxSize!)}`);
            if (fileInputRef.current) {
                fileInputRef.current.value = '';
            }
            return;
        }

        // Validate file type if accept is specified
        const isValidType = await validateFileType(file, accept);
        if (!isValidType) {
            setError(`Invalid file type. Accepted: ${accept}`);
            if (fileInputRef.current) {
                fileInputRef.current.value = '';
            }
            return;
        }

        setSelectedFile(file);
        onChange(file);
    };

    const handleRemove = () => {
        onChange(null);
        setSelectedFile(null);
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    };

    return (
        <div className="space-y-2">
            <Label htmlFor={setting.key}>{setting.name}</Label>
            <div className="flex gap-2">
                <Input id={setting.key} ref={fileInputRef} type="file" accept={accept} onChange={handleFileSelect} className="flex-1" />
            </div>
            {error && <p className="text-sm text-destructive">{error}</p>}
            <div className="w-32">
                {(selectedFile || setting.value) && (
                    <div className="flex items-center gap-2 rounded-md border p-2 text-sm">
                        <File className="h-4 w-4 text-muted-foreground" />
                        <span className="flex-1 truncate">{selectedFile?.name || setting.value}</span>
                        {setting.url && !selectedFile && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => window.open(setting.url!, '_blank')}
                                title={__('Open file')}
                            >
                                <ExternalLink className="h-4 w-4" />
                            </Button>
                        )}
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={handleRemove}
                            title={__('Remove file')}
                            className="text-destructive hover:text-destructive"
                        >
                            <X className="h-4 w-4" />
                        </Button>
                    </div>
                )}
            </div>
            {maxSize && (
                <p className="text-xs text-muted-foreground">
                    {__('Max size:')} {formatFileSize(maxSize)}
                </p>
            )}
        </div>
    );
}
