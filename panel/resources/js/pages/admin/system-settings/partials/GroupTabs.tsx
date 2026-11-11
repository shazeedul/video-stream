import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';

interface GroupTabsProps {
    groups: string[];
    activeGroup: string;
    onGroupChange: (group: string) => void;
}

export function GroupTabs({ groups, activeGroup, onGroupChange }: GroupTabsProps) {
    if (groups.length === 0) return null;

    return (
        <Tabs value={activeGroup} onValueChange={onGroupChange}>
            <TabsList>
                {groups.map((group) => (
                    <TabsTrigger key={group} value={group} className="cursor-pointer capitalize">
                        {group.replace(/_/g, ' ')}
                    </TabsTrigger>
                ))}
            </TabsList>
        </Tabs>
    );
}
