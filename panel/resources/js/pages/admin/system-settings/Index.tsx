import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import AdminLayout from '@/layouts/admin-layout';
import { dashboard } from '@/routes/admin';
import { bulkUpdate as bulkUpdateRoute, index as systemSettingsRoute } from '@/routes/admin/system-settings';
import type { BreadcrumbItem, SharedData } from '@/types';
import type { SystemSetting } from '@/types/system-setting';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { Head, router } from '@inertiajs/react';
import { Plus, Save } from 'lucide-react';
import { useState } from 'react';
import { CreateSettingSheet } from './partials/CreateSettingSheet';
import { EditSettingSheet } from './partials/EditSettingSheet';
import { GroupTabs } from './partials/GroupTabs';
import { SettingCard } from './partials/SettingCard';

interface Props extends SharedData {
    settings: SystemSetting[];
    groups: string[];
    currentGroup: string | null;
    fieldTypes: Record<string, string>;
}

export default function Index({ settings, groups, currentGroup, fieldTypes, auth }: Props) {
    const { __ } = useLocalizer();
    const [editedSettings, setEditedSettings] = useState<Record<number, SystemSetting>>({});

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: __('System'),
            href: dashboard().url,
        },
        {
            title: __('Settings'),
            href: systemSettingsRoute().url,
        },
    ];
    const [isSaving, setIsSaving] = useState(false);
    const [isCreateSheetOpen, setIsCreateSheetOpen] = useState(false);
    const [isEditSheetOpen, setIsEditSheetOpen] = useState(false);
    const [settingToEdit, setSettingToEdit] = useState<SystemSetting | null>(null);

    const activeGroup = currentGroup || groups[0] || 'general';
    const canCreate = auth.user?.permissions?.includes('system-settings.create');
    const canUpdate = auth.user?.permissions?.includes('system-settings.update');

    const handleGroupChange = (group: string) => {
        router.get(systemSettingsRoute().url, { group }, { preserveState: true });
    };

    const handleSettingChange = (setting: SystemSetting, value: string | File | null) => {
        // For file types, the SettingCard handles upload internally
        // We don't store file objects in editedSettings
        if (value instanceof File || value === null) {
            return;
        }

        setEditedSettings((prev) => ({
            ...prev,
            [setting.id]: {
                ...setting,
                value,
            },
        }));
    };

    const handleSave = () => {
        const settingsToUpdate = Object.values(editedSettings).map((setting) => ({
            id: setting.id,
            value: setting.value,
        }));

        if (settingsToUpdate.length === 0) return;

        setIsSaving(true);
        router.patch(
            bulkUpdateRoute().url,
            { settings: settingsToUpdate },
            {
                onFinish: () => {
                    setIsSaving(false);
                    setEditedSettings({});
                },
            },
        );
    };

    const handleEditClick = (setting: SystemSetting) => {
        setSettingToEdit(setting);
        setIsEditSheetOpen(true);
    };

    const hasChanges = Object.keys(editedSettings).length > 0;

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={__('System Settings')} />
            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <Heading title={__('System Settings')} description={__('Manage application-wide settings and configurations')} />
                    <div className="flex gap-2">
                        {canCreate && (
                            <Button variant="outline" onClick={() => setIsCreateSheetOpen(true)}>
                                <Plus className="mr-2 h-4 w-4" />
                                {__('Add Setting')}
                            </Button>
                        )}
                        {hasChanges && canUpdate && (
                            <Button onClick={handleSave} disabled={isSaving}>
                                <Save className="mr-2 h-4 w-4" />
                                {isSaving ? __('Saving...') : __('Save All Changes')}
                            </Button>
                        )}
                    </div>
                </div>

                <GroupTabs groups={groups} activeGroup={activeGroup} onGroupChange={handleGroupChange} />

                <div className="mt-6 space-y-4">
                    {settings.length === 0 ? (
                        <div className="py-12 text-center text-muted-foreground">{__('No settings found for this group.')}</div>
                    ) : (
                        settings.map((setting) => (
                            <SettingCard
                                key={setting.id}
                                setting={editedSettings[setting.id] || setting}
                                onChange={(value) => handleSettingChange(setting, value)}
                                onEdit={() => handleEditClick(setting)}
                                canUpdate={canUpdate}
                                canDelete={auth.user?.permissions?.includes('system-settings.delete')}
                            />
                        ))
                    )}
                </div>
            </div>

            <CreateSettingSheet
                open={isCreateSheetOpen}
                onOpenChange={setIsCreateSheetOpen}
                currentGroup={activeGroup}
                groups={groups}
                fieldTypes={fieldTypes}
            />

            <EditSettingSheet
                open={isEditSheetOpen}
                onOpenChange={setIsEditSheetOpen}
                setting={settingToEdit}
                groups={groups}
                fieldTypes={fieldTypes}
            />
        </AdminLayout>
    );
}
