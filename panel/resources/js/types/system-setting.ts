import { SystemSettingType } from '@/enums';

export { SystemSettingType };

export interface SystemSettingRules {
    accept?: string;
    maxsize?: number;
    options?: Record<string, string>;
    [key: string]: string | number | boolean | Record<string, string> | undefined;
}

export interface SystemSetting {
    id: number;
    group: string;
    name: string;
    key: string;
    value: string;
    url: string | null;
    type: string;
    rules: SystemSettingRules | null;
    remarks: string | null;
    is_encrypted: boolean;
    is_public: boolean;
    sort_order: number;
    is_visible: boolean;
    created_at: string | null;
    updated_at: string | null;
}
