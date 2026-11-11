import { UserSettingsHeader } from '@/components/shared/settings-header';
import TabNavigation from '@/components/tab-navigation';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useSettingsNavigation } from '@/config/usePortalNavigation';
import PortalLayout from '@/layouts/portal-layout';
import { edit, overview } from '@/routes/portal/settings/profile';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { useLocalizer } from '@devwizard/laravel-localizer-react';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    Briefcase,
    Calendar,
    CheckCircle2,
    Edit3,
    Facebook,
    Globe,
    Instagram,
    Linkedin,
    Mail,
    MapPin,
    Phone,
    Twitter,
    User,
    XCircle,
} from 'lucide-react';

interface User {
    id: number;
    name: string;
    email: string;
    username: string;
    phone: string | null;
    bio: string | null;
    company: string | null;
    designation: string | null;
    address: string | null;
    city: string | null;
    state: string | null;
    zip_code: string | null;
    social_links: { [key: string]: string };
    profile_photo_url: string;
    is_active: boolean;
    email_verified_at: string | null;
    created_at: string;
    is_social_only: boolean;
    social_accounts_count: number;
}

interface ProfileOverviewProps {
    user: User;
}

const socialIcons: Record<string, React.ComponentType<{ className?: string }>> = {
    facebook: Facebook,
    twitter: Twitter,
    linkedin: Linkedin,
    instagram: Instagram,
    website: Globe,
};

export default function ProfileOverview({ user }: Readonly<ProfileOverviewProps>) {
    const { auth } = usePage<SharedData>().props;
    const { __ } = useLocalizer();
    const settingsNavigation = useSettingsNavigation();

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: __('Profile Overview'),
            href: overview().url,
        },
    ];

    const getInitials = (name: string) => {
        return name
            .split(' ')
            .map((n) => n[0])
            .join('')
            .toUpperCase()
            .slice(0, 2);
    };

    const hasSocialLinks = user.social_links && Object.keys(user.social_links).length > 0;

    return (
        <PortalLayout breadcrumbs={breadcrumbs}>
            <Head title={__('Profile Overview')} />

            <TabNavigation header={<UserSettingsHeader user={auth.user} />} navigation={settingsNavigation}>
                <div className="space-y-6">
                    {/* Profile Header */}
                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex flex-col items-center space-y-4 md:flex-row md:space-y-0 md:space-x-6">
                                <Avatar className="h-32 w-32">
                                    <AvatarImage src={user.profile_photo_url} alt={user.name} />
                                    <AvatarFallback className="text-2xl">{getInitials(user.name)}</AvatarFallback>
                                </Avatar>
                                <div className="flex-1 space-y-2 text-center md:text-left">
                                    <div className="flex flex-col items-center gap-2 md:flex-row">
                                        <h2 className="text-2xl font-bold">{user.name}</h2>
                                        {user.is_active ? (
                                            <Badge variant="default" className="gap-1">
                                                <CheckCircle2 className="h-3 w-3" />
                                                {__('Active')}
                                            </Badge>
                                        ) : (
                                            <Badge variant="destructive" className="gap-1">
                                                <XCircle className="h-3 w-3" />
                                                {__('Inactive')}
                                            </Badge>
                                        )}
                                        {user.email_verified_at && (
                                            <Badge variant="secondary" className="gap-1">
                                                <CheckCircle2 className="h-3 w-3" />
                                                {__('Verified')}
                                            </Badge>
                                        )}
                                    </div>
                                    {user.designation && <p className="text-lg text-muted-foreground">{user.designation}</p>}
                                    {user.bio && <p className="text-sm text-muted-foreground">{user.bio}</p>}
                                </div>
                                <Link href={edit().url}>
                                    <Button variant="outline" size="sm">
                                        <Edit3 className="mr-2 h-4 w-4" />
                                        {__('Edit Profile')}
                                    </Button>
                                </Link>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Contact Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle>{__('Contact Information')}</CardTitle>
                            <CardDescription>{__('Your primary contact details')}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center gap-3">
                                <Mail className="h-5 w-5 text-muted-foreground" />
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">{__('Email')}</p>
                                    <p className="text-base">{user.email}</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <User className="h-5 w-5 text-muted-foreground" />
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">{__('Username')}</p>
                                    <p className="text-base">@{user.username}</p>
                                </div>
                            </div>
                            {user.phone && (
                                <div className="flex items-center gap-3">
                                    <Phone className="h-5 w-5 text-muted-foreground" />
                                    <div>
                                        <p className="text-sm font-medium text-muted-foreground">{__('Phone')}</p>
                                        <p className="text-base">{user.phone}</p>
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Work Information */}
                    {(user.company || user.designation) && (
                        <Card>
                            <CardHeader>
                                <CardTitle>{__('Work Information')}</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {user.company && (
                                    <div className="flex items-center gap-3">
                                        <Briefcase className="h-5 w-5 text-muted-foreground" />
                                        <div>
                                            <p className="text-sm font-medium text-muted-foreground">{__('Company')}</p>
                                            <p className="text-base">{user.company}</p>
                                        </div>
                                    </div>
                                )}
                                {user.designation && (
                                    <div className="flex items-center gap-3">
                                        <Briefcase className="h-5 w-5 text-muted-foreground" />
                                        <div>
                                            <p className="text-sm font-medium text-muted-foreground">{__('Designation')}</p>
                                            <p className="text-base">{user.designation}</p>
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    )}

                    {/* Location */}
                    {(user.address || user.city || user.state || user.zip_code) && (
                        <Card>
                            <CardHeader>
                                <CardTitle>{__('Location')}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="flex items-start gap-3">
                                    <MapPin className="mt-0.5 h-5 w-5 text-muted-foreground" />
                                    <div className="space-y-1">
                                        {user.address && <p className="text-base">{user.address}</p>}
                                        <p className="text-base">{[user.city, user.state, user.zip_code].filter(Boolean).join(', ')}</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Social Links */}
                    {hasSocialLinks && (
                        <Card>
                            <CardHeader>
                                <CardTitle>{__('Social Media')}</CardTitle>
                                <CardDescription>{__('Connect with me on social media')}</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="flex flex-wrap gap-3">
                                    {Object.entries(user.social_links).map(([platform, url]) => {
                                        const Icon = socialIcons[platform] || Globe;
                                        return (
                                            <a
                                                key={platform}
                                                href={url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="flex items-center gap-2 rounded-lg border px-4 py-2 transition-colors hover:bg-accent"
                                            >
                                                <Icon className="h-4 w-4" />
                                                <span className="text-sm capitalize">{platform}</span>
                                            </a>
                                        );
                                    })}
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Account Statistics */}
                    <div className="grid gap-4 md:grid-cols-2">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">{__('Member Since')}</CardTitle>
                                <Calendar className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{user.created_at}</div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">{__('Social Connections')}</CardTitle>
                                <User className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{user.social_accounts_count}</div>
                                <p className="text-xs text-muted-foreground">{__('Connected accounts')}</p>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </TabNavigation>
        </PortalLayout>
    );
}
