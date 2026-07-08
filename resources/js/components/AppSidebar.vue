<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue'
import NavMixed from '@/components/NavMixed.vue'
import NavUser from '@/components/NavUser.vue'
import SidebarActiveCell from '@/components/sidebar/SidebarActiveCell.vue'
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar'
import { type NavItem } from '@/types'
import { Link } from '@inertiajs/vue3'
import {
    Archive,
    BookOpen,
    Boxes,
    Cpu,
    Calendar,
    Folder,
    FolderOpen,
    Home,
    LockIcon,
    Glasses,
    Network,
    Rocket,
    Settings,
    Terminal,
    Users,
    SlidersHorizontal,
    DownloadCloud,
    DatabaseIcon,
} from 'lucide-vue-next'
import { computed } from 'vue'
import AppLogo from './AppLogo.vue'

type CellStatus = 'offline' | 'starting' | 'running' | 'stopping'

type NavSingle = NavItem & {
    type?: 'item'
}

type NavGroup = {
    type: 'group'
    title: string
    items: NavItem[]
}

type SidebarNavEntry = NavSingle | NavGroup

const props = defineProps<{
    context?: 'dashboard' | 'server' | 'admin'
    activeCell?: any
    activeCellStatus?: CellStatus
}>()

const getCombData = (cell: any) => {
    return cell?.comb_data ?? cell?.combData ?? cell?.comb?.data ?? {}
}

const getCellGame = (cell: any): string => {
    return String(
        getCombData(cell)?.game ??
        cell?.game ??
        cell?.comb?.game ??
        ''
    ).toLowerCase()
}

const gameNavGroup = (cell: any): SidebarNavEntry | null => {
    switch (getCellGame(cell)) {
        case 'minecraft':
            return {
                type: 'group',
                title: 'Minecraft',
                items: [
                    { title: 'Players', href: `/cells/${cell.id}/players`, icon: Users },
                    { title: 'Worlds', href: `/cells/${cell.id}/worlds`, icon: FolderOpen },
                    { title: 'Plugins', href: `/cells/${cell.id}/plugins`, icon: Boxes },
                    { title: 'Modpacks', href: `/cells/${cell.id}/mods`, icon: Boxes },
                    { title: 'Instances', href: `/cells/${cell.id}/instances`, icon: Boxes },
                    { title: 'JARs', href: `/cells/${cell.id}/jars`, icon: Boxes },
                ],
            }

        case 'discord':
        case 'discord-bot':
        case 'discord_bot':
            return {
                type: 'group',
                title: 'Discord Bot',
                items: [
                    { title: 'Bot Preinstalls', href: `/cells/${cell.id}/bot-preinstalls`, icon: Boxes },
                    { title: 'Environment', href: `/cells/${cell.id}/environment`, icon: SlidersHorizontal },
                    { title: 'Packages', href: `/cells/${cell.id}/packages`, icon: Archive },
                    { title: 'Logs', href: `/cells/${cell.id}/logs`, icon: Glasses },
                ],
            }

        case 'steam':
            return {
                type: 'group',
                title: 'Steam',
                items: [
                    { title: 'SteamCMD', href: `/cells/${cell.id}/steamcmd`, icon: DownloadCloud },
                    { title: 'Workshop', href: `/cells/${cell.id}/workshop`, icon: Boxes },
                    { title: 'Updates', href: `/cells/${cell.id}/updates`, icon: Rocket },
                ],
            }

        default:
            return null
    }
}

const dashboardNav: SidebarNavEntry[] = [
    { title: 'Servers', href: '/dashboard', icon: Home },
    { title: 'API Keys', href: '/servers', icon: LockIcon },
]

const serverNav = (cell: any): SidebarNavEntry[] => {
    const items: SidebarNavEntry[] = [
        { title: 'Console', href: `/cells/${cell.id}`, icon: Terminal },
        { title: 'Files', href: `/cells/${cell.id}/files`, icon: FolderOpen },
        { title: 'Databases', href: `/cells/${cell.id}/databases`, icon: DatabaseIcon },
        { title: 'Sub Users', href: `/cells/${cell.id}/users`, icon: Users },
        { title: 'Activity', href: `/cells/${cell.id}/activity`, icon: Glasses },
        { title: 'Settings', href: `/cells/${cell.id}/settings`, icon: Settings },
    ]

    const gameGroup = gameNavGroup(cell)

    if (gameGroup) {
        items.push(gameGroup)
    }

    items.push({
        type: 'group',
        title: 'Advanced',
        items: [
            { title: 'Backups', href: `/cells/${cell.id}/backups`, icon: Archive },
            { title: 'Scheduled Tasks', href: `/cells/${cell.id}/schedules`, icon: Calendar },
            { title: 'Config Editor', href: `/cells/${cell.id}/config`, icon: SlidersHorizontal },
            { title: 'Server Importer', href: `/cells/${cell.id}/importer`, icon: DownloadCloud },
            { title: 'Startup', href: `/cells/${cell.id}/startup`, icon: Rocket },
            { title: 'Network', href: `/cells/${cell.id}/network`, icon: Network },
        ],
    })

    return items
}

const adminNav: SidebarNavEntry[] = [
    { title: 'Overview', href: '/admin', icon: Home },
    { title: 'Users', href: '/admin/users', icon: Users },

    {
        type: 'group',
        title: 'Infrastructure',
        items: [
            { title: 'Nodes', href: '/admin/nodes', icon: Cpu },
            { title: 'Cells', href: '/admin/cells', icon: DatabaseIcon },
            { title: 'Combs', href: '/admin/combs', icon: Boxes },
            { title: 'Settings', href: '/admin/settings', icon: Settings },
        ],
    },
]

const mainNavItems = computed<SidebarNavEntry[]>(() => {
    switch (props.context) {
        case 'server':
            return props.activeCell ? serverNav(props.activeCell) : dashboardNav

        case 'admin':
            return adminNav

        default:
            return dashboardNav
    }
})

const footerNavItems: NavItem[] = [
    {
        title: 'Github Repo',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits',
        icon: BookOpen,
    },
]
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <div class="mx-3 my-2 border-t border-zinc-800" />

            <SidebarActiveCell
                v-if="activeCell"
                :cell="activeCell"
                :status="activeCellStatus"
            />

            <NavMixed :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <!-- <NavFooter :items="footerNavItems" /> -->
            <NavUser />
        </SidebarFooter>
    </Sidebar>

    <slot />
</template>