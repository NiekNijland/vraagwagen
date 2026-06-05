import { Form, Head, setLayoutProps, usePage } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import { edit } from '@/routes/profile';

export default function Profile() {
    const { auth } = usePage().props;
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            {
                title: t('pages.settings.profile.breadcrumb'),
                href: edit(),
            },
        ],
    });

    return (
        <>
            <Head title={t('pages.settings.profile.title')} />

            <h1 className="sr-only">{t('pages.settings.profile.title')}</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('pages.settings.profile.heading')}
                    description={t('pages.settings.profile.description')}
                />

                <Form
                    {...ProfileController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">
                                    {t('pages.settings.profile.name')}
                                </Label>

                                <Input
                                    id="name"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.name}
                                    name="name"
                                    required
                                    autoComplete="name"
                                    placeholder={t(
                                        'pages.settings.profile.namePlaceholder',
                                    )}
                                    aria-invalid={
                                        errors.name ? true : undefined
                                    }
                                    aria-describedby={
                                        errors.name
                                            ? 'profile-name-error'
                                            : undefined
                                    }
                                />

                                <InputError
                                    id="profile-name-error"
                                    className="mt-2"
                                    message={errors.name}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">
                                    {t('pages.settings.profile.email')}
                                </Label>

                                <Input
                                    id="email"
                                    type="email"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.email}
                                    name="email"
                                    required
                                    autoComplete="username"
                                    placeholder={t(
                                        'pages.settings.profile.emailPlaceholder',
                                    )}
                                    aria-invalid={
                                        errors.email ? true : undefined
                                    }
                                    aria-describedby={
                                        errors.email
                                            ? 'profile-email-error'
                                            : undefined
                                    }
                                />

                                <InputError
                                    id="profile-email-error"
                                    className="mt-2"
                                    message={errors.email}
                                />
                            </div>

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="update-profile-button"
                                >
                                    {t('pages.settings.profile.save')}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>

            <DeleteUser />
        </>
    );
}
