<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    BriefcaseBusiness,
    Building2,
    FileSpreadsheet,
    FolderGit2,
    LayoutGrid,
    School,
    ShieldCheck,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useTranslation } from '@/composables/useTranslation';
import { dashboard } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as importsIndex } from '@/routes/admin/imports';
import { index as schoolsIndex } from '@/routes/admin/schools';
import { index as usersIndex } from '@/routes/admin/users';
import { show as dossier } from '@/routes/dossiers';
import { index as educationCatalog } from '@/routes/education-organizations';
import { index as organizations, teachers } from '@/routes/organization';
import { index as reports } from '@/routes/reports';
import { organizationEmployment } from '@/routes/teacher';
import { index as myEmployment } from '@/routes/teacher/employment';
import type { NavItem } from '@/types';

const { t } = useTranslation();

const page = usePage();
const isAdmin = computed(() => page.props.auth.user?.role === 'admin');
const home = computed(() => (isAdmin.value ? adminDashboard() : dashboard()));

const navGroups = computed<{ label: string; items: NavItem[] }[]>(() => {
    const catalogItem = page.props.educationCatalogEnabled
        ? [
              {
                  title: t('Ұйымдар каталогы'),
                  href: educationCatalog(),
                  icon: Building2,
              },
          ]
        : [];
    const access = {
        title: t('Ұйымдық қолжетімділік'),
        href: organizations(),
        icon: ShieldCheck,
    };
    const schoolReports = {
        title: t('Мектеп есептері'),
        href: reports(),
        icon: BookOpen,
    };
    const teacherDirectory = {
        title: t('Педагогтер каталогы'),
        href: teachers(),
        icon: Users,
    };

    if (isAdmin.value) {
        return [
            {
                label: t('Шолу'),
                items: [
                    {
                        title: t('Басқару панелі'),
                        href: adminDashboard(),
                        icon: LayoutGrid,
                    },
                ],
            },
            {
                label: t('Білім беру ұйымдары'),
                items: [
                    ...catalogItem,
                    teacherDirectory,
                    access,
                    {
                        title: t('Мектептерді басқару'),
                        href: schoolsIndex(),
                        icon: School,
                    },
                ],
            },
            {
                label: t('Әкімші құралдары'),
                items: [
                    {
                        title: t('Пайдаланушылар'),
                        href: usersIndex(),
                        icon: Users,
                    },
                    {
                        title: t('Мектеп және педагог импорты'),
                        href: importsIndex(),
                        icon: FileSpreadsheet,
                    },
                    schoolReports,
                ],
            },
        ];
    }

    const user = page.props.auth.user;

    if (user?.role === 'teacher') {
        return [
            {
                label: t('Шолу'),
                items: [
                    {
                        title: t('Басты бет'),
                        href: dashboard(),
                        icon: LayoutGrid,
                    },
                ],
            },
            {
                label: t('Педагог кабинеті'),
                items: [
                    {
                        title: t('Менің жеке ісім'),
                        href: dossier(user.id),
                        icon: FolderGit2,
                    },
                    {
                        title: t('Мектептегі еңбек деректерім'),
                        href: myEmployment(),
                        icon: BriefcaseBusiness,
                    },
                    ...(page.props.educationCatalogEnabled
                        ? [
                              {
                                  title: t('Ұйымдардағы жұмыс орындарым'),
                                  href: organizationEmployment(),
                                  icon: Building2,
                              },
                          ]
                        : []),
                ],
            },
        ];
    }

    if (user?.role === 'school_admin' || user?.role === 'methodist') {
        return [
            {
                label: t('Білім беру ұйымдары'),
                items: [...catalogItem, teacherDirectory, access],
            },
            { label: t('Талдау'), items: [schoolReports] },
        ];
    }

    return [
        {
            label: t('Шолу'),
            items: [
                { title: t('Басты бет'), href: dashboard(), icon: LayoutGrid },
            ],
        },
    ];
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="home">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="gap-4 py-3">
            <nav :aria-label="t('Негізгі навигация')" class="grid gap-4">
                <NavMain
                    v-for="group in navGroups"
                    :key="group.label"
                    :label="group.label"
                    :items="group.items"
                />
            </nav>
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
