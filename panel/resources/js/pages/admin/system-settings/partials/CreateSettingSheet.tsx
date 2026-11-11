import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { store as storeRoute } from '@/routes/admin/system-settings';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { router } from '@inertiajs/react';
import Editor from '@monaco-editor/react';
import { useState } from 'react';
import { RulesHelpDialog } from './RulesHelpDialog';

interface CreateSettingSheetProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    currentGroup?: string;
    groups: string[];
    fieldTypes: Record<string, string>;
}

export function CreateSettingSheet({ open, onOpenChange, currentGroup, groups, fieldTypes }: CreateSettingSheetProps) {
    const { __ } = useLocalizer();
    const [formData, setFormData] = useState({
        group: currentGroup || 'general',
        name: '',
        key: '',
        value: '',
        type: 'text',
        rules: '{}',
        remarks: '',
        is_encrypted: false,
        is_public: false,
        is_visible: true,
    });
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [isCreatingNewGroup, setIsCreatingNewGroup] = useState(false);
    const [isKeyManuallyEdited, setIsKeyManuallyEdited] = useState(false);

    // Slugify group name: lowercase, replace spaces and underscores with dots
    const slugifyGroup = (value: string): string => {
        return value
            .toLowerCase()
            .trim()
            .replace(/[\s_]+/g, '.') // Replace spaces and underscores with dots
            .replace(/\.+/g, '.') // Replace multiple dots with single dot
            .replace(/^\.+|\.+$/g, ''); // Remove leading/trailing dots
    };

    // Generate auto-suggested key from group and name
    const generateKey = (group: string, name: string): string => {
        const slugifiedGroup = slugifyGroup(group);
        const slugifiedName = name
            .toLowerCase()
            .trim()
            .replace(/[\s_]+/g, '.') // Replace spaces and underscores with dots
            .replace(/\.+/g, '.') // Replace multiple dots with single dot
            .replace(/^\.+|\.+$/g, ''); // Remove leading/trailing dots

        if (!slugifiedGroup && !slugifiedName) return '';
        if (!slugifiedGroup) return slugifiedName;
        if (!slugifiedName) return slugifiedGroup;

        return `${slugifiedGroup}.${slugifiedName}`;
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setIsSubmitting(true);
        setErrors({});

        // Slugify group before submitting
        const submissionData = {
            ...formData,
            group: slugifyGroup(formData.group),
        };

        router.post(storeRoute().url, submissionData, {
            onSuccess: () => {
                onOpenChange(false);
                setFormData({
                    group: currentGroup || 'general',
                    name: '',
                    key: '',
                    value: '',
                    type: 'text',
                    rules: '{}',
                    remarks: '',
                    is_encrypted: false,
                    is_public: false,
                    is_visible: true,
                });
                setIsCreatingNewGroup(false);
            },
            onError: (errors) => {
                setErrors(errors as Record<string, string>);
            },
            onFinish: () => {
                setIsSubmitting(false);
            },
        });
    };

    const handleChange = (field: string, value: string | boolean) => {
        setFormData((prev) => {
            const updated = {
                ...prev,
                [field]: value,
            };

            // Auto-generate key when group or name changes (unless manually edited)
            if ((field === 'group' || field === 'name') && !isKeyManuallyEdited) {
                updated.key = generateKey(field === 'group' ? (value as string) : prev.group, field === 'name' ? (value as string) : prev.name);
            }

            return updated;
        });

        // Clear error for this field
        if (errors[field]) {
            setErrors((prev) => {
                const newErrors = { ...prev };
                delete newErrors[field];
                return newErrors;
            });
        }
    };

    const handleKeyChange = (value: string) => {
        setIsKeyManuallyEdited(true);
        handleChange('key', value);
    };

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="w-full overflow-y-auto sm:max-w-lg [&>button]:hidden">
                <SheetHeader className="border-b">
                    <SheetTitle>{__('Add New Setting')}</SheetTitle>
                    <SheetDescription>{__('Create a new system setting for your application.')}</SheetDescription>
                </SheetHeader>
                <div className="flex-1 space-y-6 px-4 py-2">
                    <form onSubmit={handleSubmit} className="mt-6 space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="group">{__('Group')}</Label>
                            {isCreatingNewGroup ? (
                                <div className="space-y-2">
                                    <Input
                                        id="group"
                                        placeholder={__('Enter new group name (e.g., Email Settings, Payment Config)')}
                                        value={formData.group}
                                        onChange={(e) => handleChange('group', e.target.value)}
                                        required
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        {__('Will be saved as:')}{' '}
                                        <code className="rounded bg-muted px-1 py-0.5">{slugifyGroup(formData.group) || 'group.name'}</code>
                                    </p>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => {
                                            setIsCreatingNewGroup(false);
                                            handleChange('group', currentGroup || 'general');
                                        }}
                                        className="text-xs"
                                    >
                                        {__('← Select from existing groups')}
                                    </Button>
                                </div>
                            ) : (
                                <Select
                                    value={formData.group}
                                    onValueChange={(value) => {
                                        if (value === '__new__') {
                                            setIsCreatingNewGroup(true);
                                            handleChange('group', '');
                                        } else {
                                            handleChange('group', value);
                                        }
                                    }}
                                >
                                    <SelectTrigger id="group">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {groups.map((group) => (
                                            <SelectItem key={group} value={group}>
                                                {group}
                                            </SelectItem>
                                        ))}
                                        <SelectItem value="__new__">{__('+ Create New Group')}</SelectItem>
                                    </SelectContent>
                                </Select>
                            )}
                            {errors.group && <p className="text-sm text-destructive">{errors.group}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="name">{__('Name')} *</Label>
                            <Input
                                id="name"
                                placeholder="e.g., Site Name"
                                value={formData.name}
                                onChange={(e) => handleChange('name', e.target.value)}
                                required
                            />
                            {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="key">{__('Key')} *</Label>
                            <Input
                                id="key"
                                placeholder="e.g., app.site_name"
                                value={formData.key}
                                onChange={(e) => handleKeyChange(e.target.value)}
                                required
                            />
                            {!isKeyManuallyEdited && (
                                <p className="text-xs text-muted-foreground">
                                    {__('Auto-generated from group and name. You can edit manually if needed.')}
                                </p>
                            )}
                            {errors.key && <p className="text-sm text-destructive">{errors.key}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="type">{__('Field Type')}</Label>
                            <Select value={formData.type} onValueChange={(value) => handleChange('type', value)}>
                                <SelectTrigger id="type">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(fieldTypes).map(([value, label]) => (
                                        <SelectItem key={value} value={value}>
                                            {label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.type && <p className="text-sm text-destructive">{errors.type}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="value">{__('Default Value')}</Label>
                            {formData.type === 'text_area' ? (
                                <Textarea
                                    id="value"
                                    placeholder={__('Enter default value')}
                                    value={formData.value}
                                    onChange={(e) => handleChange('value', e.target.value)}
                                    rows={4}
                                />
                            ) : (
                                <Input
                                    id="value"
                                    type={formData.type === 'number' ? 'number' : 'text'}
                                    placeholder={__('Enter default value')}
                                    value={formData.value}
                                    onChange={(e) => handleChange('value', e.target.value)}
                                />
                            )}
                            {errors.value && <p className="text-sm text-destructive">{errors.value}</p>}
                        </div>

                        <div className="space-y-2">
                            <div className="flex items-center justify-between">
                                <Label htmlFor="rules">{__('Rules (JSON)')}</Label>
                                <RulesHelpDialog />
                            </div>
                            <div className="overflow-hidden rounded-md border">
                                <Editor
                                    height="200px"
                                    language="json"
                                    value={formData.rules}
                                    onChange={(value) => handleChange('rules', value || '')}
                                    theme="vs-dark"
                                    options={{
                                        minimap: { enabled: false },
                                        fontSize: 13,
                                        lineNumbers: 'on',
                                        scrollBeyondLastLine: false,
                                        wordWrap: 'on',
                                        automaticLayout: true,
                                        formatOnPaste: true,
                                        formatOnType: true,
                                        tabSize: 2,
                                    }}
                                />
                            </div>
                            <div className="rounded-md bg-muted p-3 text-xs text-muted-foreground">
                                <p className="mb-1 font-medium">{__('Quick Examples (click ? for detailed documentation):')}</p>
                                <ul className="ml-4 list-disc space-y-1">
                                    <li>
                                        <strong>{__('For select/radio:')}</strong> {'{'}&#34;yes&#34;: &#34;Yes&#34;, &#34;no&#34;: &#34;No&#34;{'}'}
                                    </li>
                                    <li>
                                        <strong>{__('For validation:')}</strong> {'{'}&#34;required&#34;: true, &#34;min&#34;: 5, &#34;max&#34;: 100
                                        {'}'}
                                    </li>
                                    <li>
                                        <strong>{__('Empty rules:')}</strong> {'{}'}
                                    </li>
                                </ul>
                            </div>
                            {errors.rules && <p className="text-sm text-destructive">{errors.rules}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="remarks">{__('Description')}</Label>
                            <Textarea
                                id="remarks"
                                placeholder={__('Brief description of this setting')}
                                value={formData.remarks}
                                onChange={(e) => handleChange('remarks', e.target.value)}
                                rows={3}
                            />
                            {errors.remarks && <p className="text-sm text-destructive">{errors.remarks}</p>}
                        </div>

                        <div className="space-y-3">
                            <div className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    id="is_encrypted"
                                    checked={formData.is_encrypted}
                                    onChange={(e) => {
                                        handleChange('is_encrypted', e.target.checked);
                                        if (e.target.checked) {
                                            handleChange('is_public', false);
                                        }
                                    }}
                                    className="h-4 w-4"
                                />
                                <Label htmlFor="is_encrypted" className="cursor-pointer font-normal">
                                    Encrypt value (for sensitive data like API keys)
                                </Label>
                            </div>

                            <div className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    id="is_public"
                                    checked={formData.is_public}
                                    onChange={(e) => handleChange('is_public', e.target.checked)}
                                    disabled={formData.is_encrypted}
                                    className="h-4 w-4 disabled:cursor-not-allowed disabled:opacity-50"
                                />
                                <Label
                                    htmlFor="is_public"
                                    className={`font-normal ${formData.is_encrypted ? 'cursor-not-allowed opacity-50' : 'cursor-pointer'}`}
                                >
                                    {__('Expose to frontend globally')}
                                </Label>
                            </div>

                            <div className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    id="is_visible"
                                    checked={formData.is_visible}
                                    onChange={(e) => handleChange('is_visible', e.target.checked)}
                                    className="h-4 w-4"
                                />
                                <Label htmlFor="is_visible" className="cursor-pointer font-normal">
                                    {__('Visible in settings list')}
                                </Label>
                            </div>
                            {errors.is_public && <p className="text-sm text-destructive">{errors.is_public}</p>}
                        </div>

                        <div className="flex gap-2 pt-4">
                            <Button type="submit" disabled={isSubmitting} className="flex-1">
                                {isSubmitting ? __('Creating...') : __('Create Setting')}
                            </Button>
                            <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={isSubmitting}>
                                {__('Cancel')}
                            </Button>
                        </div>
                    </form>
                </div>
            </SheetContent>
        </Sheet>
    );
}
