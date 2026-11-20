<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    BriefcaseBusiness,
    FolderGit2,
    LayoutGrid,
    School,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
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
import { dashboard } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as schoolsIndex } from '@/routes/admin/schools';
import { index as usersIndex } from '@/routes/admin/users';
import { show as dossier } from '@/routes/dossiers';
import { index as organizations } from '@/routes/organization';
import { index as reports } from '@/routes/reports';
import { index as myEmployment } from '@/routes/teacher/employment';
import type { NavItem } from '@/types';

const page = usePage();
const isAdmin = computed(() => page.props.auth.user?.role === 'admin');
const home = computed(() => (isAdmin.value ? adminDashboard() : dashboard()));

const adminNavItems: NavItem[] = [
    { title: 'Басқару панелі', href: adminDashboard(), icon: LayoutGrid },
    { title: 'Пайдаланушылар', href: usersIndex(), icon: Users },
    { title: 'Мектептер', href: schoolsIndex(), icon: School },
    { title: 'Ұйымдар', href: organizations(), icon: School },
    { title: 'Есептер', href: reports(), icon: BookOpen },
];

const defaultNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

const teacherNavItems: NavItem[] = [
    ...defaultNavItems,
    {
        title: 'Менің еңбек деректерім',
        href: myEmployment(),
        icon: BriefcaseBusiness,
    },
];

const mainNavItems = computed(() => {
    if (isAdmin.value) {
        return adminNavItems;
    }

    const user = page.props.auth.user;

    if (user?.role === 'teacher') {
        return [
            ...teacherNavItems,
            {
                title: 'Менің жеке ісім',
                href: dossier(user.id),
                icon: FolderGit2,
            },
        ];
    }

    if (user?.role === 'school_admin' || user?.role === 'methodist') {
        return [
            { title: 'Ұйымдар', href: organizations(), icon: School },
            { title: 'Есептер', href: reports(), icon: BookOpen },
        ];
    }

    return defaultNavItems;
});

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
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

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
