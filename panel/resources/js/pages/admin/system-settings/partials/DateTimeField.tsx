import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { SystemSetting } from '@/types/system-setting';

interface DateTimeFieldProps {
    setting: SystemSetting;
    onChange: (value: string) => void;
}

export function DateTimeField({ setting, onChange }: DateTimeFieldProps) {
    return (
        <div className="space-y-2">
            <Label htmlFor={setting.key}>{setting.name}</Label>
            <Input id={setting.key} type={setting.type} value={setting.value} onChange={(e) => onChange(e.target.value)} />
        </div>
    );
}
