<script setup lang="ts">
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
    Calendar,
    Clock3,
    CpuIcon,
    DatabaseIcon,
    DownloadCloud,
    Folder,
    FolderOpen,
    Glasses,
    Home,
    Network,
    Rocket,
    Server,
    Settings,
    SlidersHorizontal,
    Terminal,
    Users,
} from 'lucide-vue-next'
import { computed } from 'vue'
import AppLogo from './AppLogo.vue'

type CellRuntimeStatus =
    | 'offline'
    | 'starting'
    | 'running'
    | 'stopping'
    | 'installing'

type CellInstallStatus =
    | 'pending'
    | 'installing'
    | 'installed'
    | 'failed'

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
    activeCellStatus?: CellRuntimeStatus
}>()

const getCombData = (cell: any) => {
    return (
        cell?.comb_data ??
        cell?.combData ??
        cell?.comb?.data ??
        {}
    )
}

const getCellGame = (cell: any): string => {
    return String(
        getCombData(cell)?.game ??
            cell?.game ??
            cell?.comb?.game ??
            '',
    ).toLowerCase()
}

const getInstallStatus = (
    cell: any,
): CellInstallStatus => {
    const status = String(
        cell?.install_status ??
            cell?.installStatus ??
            'installed',
    ).toLowerCase()

    switch (status) {
        case 'pending':
        case 'installing':
        case 'failed':
        case 'installed':
            return status

        default:
            /*
             * Existing cells created before install_status was introduced
             * should remain usable if no recognised status was supplied.
             */
            return 'installed'
    }
}

const isCellInstalled = computed(() => {
    if (!props.activeCell) {
        return true
    }

    return getInstallStatus(props.activeCell) === 'installed'
})

const sidebarCellStatus = computed<CellRuntimeStatus>(
    () => {
        if (
            props.activeCell &&
            !isCellInstalled.value
        ) {
            return 'installing'
        }

        return props.activeCellStatus ?? 'offline'
    },
)

const gameNavGroup = (
    cell: any,
): SidebarNavEntry | null => {
    switch (getCellGame(cell)) {
        case 'minecraft':
            return {
                type: 'group',
                title: 'Minecraft',
                items: [
                    {
                        title: 'Players',
                        href: `/cells/${cell.id}/players`,
                        icon: Users,
                    },
                    {
                        title: 'Worlds',
                        href: `/cells/${cell.id}/worlds`,
                        icon: FolderOpen,
                    },
                    {
                        title: 'Plugins',
                        href: `/cells/${cell.id}/plugins`,
                        icon: Boxes,
                    },
                    {
                        title: 'Modpacks',
                        href: `/cells/${cell.id}/mods`,
                        icon: Boxes,
                    },
                    {
                        title: 'Instances',
                        href: `/cells/${cell.id}/instances`,
                        icon: Boxes,
                    },
                    {
                        title: 'JARs',
                        href: `/cells/${cell.id}/jars`,
                        icon: Boxes,
                    },
                ],
            }

        case 'discord':
        case 'discord-bot':
        case 'discord_bot':
            return {
                type: 'group',
                title: 'Discord Bot',
                items: [
                    {
                        title: 'Bot Preinstalls',
                        href: `/cells/${cell.id}/bot-preinstalls`,
                        icon: Boxes,
                    },
                    {
                        title: 'Environment',
                        href: `/cells/${cell.id}/environment`,
                        icon: SlidersHorizontal,
                    },
                    {
                        title: 'Packages',
                        href: `/cells/${cell.id}/packages`,
                        icon: Archive,
                    },
                    {
                        title: 'Logs',
                        href: `/cells/${cell.id}/logs`,
                        icon: Glasses,
                    },
                ],
            }

        case 'steam':
            return {
                type: 'group',
                title: 'Steam',
                items: [
                    {
                        title: 'SteamCMD',
                        href: `/cells/${cell.id}/steamcmd`,
                        icon: DownloadCloud,
                    },
                    {
                        title: 'Workshop',
                        href: `/cells/${cell.id}/workshop`,
                        icon: Boxes,
                    },
                    {
                        title: 'Updates',
                        href: `/cells/${cell.id}/updates`,
                        icon: Rocket,
                    },
                ],
            }

        default:
            return null
    }
}

const dashboardNav: SidebarNavEntry[] = [
    {
        title: 'Servers',
        href: '',
        icon: Home,
    },
]

const installingNav = (
    cell: any,
): SidebarNavEntry[] => {
    return [
        {
            title:
                getInstallStatus(cell) === 'failed'
                    ? 'Installation Failed'
                    : 'Installation',
            href: `/cells/${cell.id}`,
            icon: Clock3,
        },
    ]
}

const serverNav = (
    cell: any,
): SidebarNavEntry[] => {
    if (getInstallStatus(cell) !== 'installed') {
        return installingNav(cell)
    }

    const items: SidebarNavEntry[] = [
        {
            title: 'Console',
            href: `/cells/${cell.id}`,
            icon: Terminal,
        },
        {
            title: 'Files',
            href: `/cells/${cell.id}/files`,
            icon: FolderOpen,
        },
        {
            title: 'Databases',
            href: `/cells/${cell.id}/databases`,
            icon: DatabaseIcon,
        },
        {
            title: 'Sub Users',
            href: `/cells/${cell.id}/users`,
            icon: Users,
        },
        {
            title: 'Activity',
            href: `/cells/${cell.id}/activity`,
            icon: Glasses,
        },
        {
            title: 'Settings',
            href: `/cells/${cell.id}/settings`,
            icon: Settings,
        },
    ]

    const gameGroup = gameNavGroup(cell)

    if (gameGroup) {
        items.push(gameGroup)
    }

    items.push({
        type: 'group',
        title: 'Advanced',
        items: [
            {
                title: 'Backups',
                href: `/cells/${cell.id}/backups`,
                icon: Archive,
            },
            {
                title: 'Scheduled Tasks',
                href: `/cells/${cell.id}/schedules`,
                icon: Calendar,
            },
            {
                title: 'Config Editor',
                href: `/cells/${cell.id}/config`,
                icon: SlidersHorizontal,
            },
            {
                title: 'Server Importer',
                href: `/cells/${cell.id}/importer`,
                icon: DownloadCloud,
            },
            {
                title: 'Startup',
                href: `/cells/${cell.id}/startup`,
                icon: Rocket,
            },
            {
                title: 'Network',
                href: `/cells/${cell.id}/network`,
                icon: Network,
            },
        ],
    })

    return items
}

const adminNav: SidebarNavEntry[] = [
    {
        title: 'Overview',
        href: '/admin',
        icon: Home,
    },
    {
        title: 'Users',
        href: '/admin/users',
        icon: Users,
    },
    {
        type: 'group',
        title: 'Infrastructure',
        items: [
            {
                title: 'Nodes',
                href: '/admin/nodes',
                icon: CpuIcon,
            },
            {
                title: 'Cells',
                href: '/admin/cells',
                icon: Server,
            },
            {
                title: 'Combs',
                href: '/admin/combs',
                icon: Boxes,
            },
            {
                title: 'Settings',
                href: '/admin/settings',
                icon: Settings,
            },
        ],
    },
]

const mainNavItems = computed<
    SidebarNavEntry[]
>(() => {
    switch (props.context) {
        case 'server':
            return props.activeCell
                ? serverNav(props.activeCell)
                : dashboardNav

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
    <Sidebar
        collapsible="icon"
        variant="inset"
    >
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        size="lg"
                        as-child
                    >
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <div
                class="mx-3 my-2 border-t border-zinc-800"
            />

            <SidebarActiveCell
                v-if="activeCell"
                :cell="activeCell"
                :status="sidebarCellStatus"
            />

            <div
                v-if="
                    context === 'server' &&
                    activeCell &&
                    !isCellInstalled
                "
                class="mx-3 mb-3 rounded-button border border-hive/20 bg-hive/5 px-3 py-3 group-data-[collapsible=icon]:hidden"
            >
                <div
                    class="flex items-start gap-2"
                >
                    <Clock3
                        class="mt-0.5 size-4 shrink-0 text-hive"
                    />

                    <div class="min-w-0">
                        <p
                            class="text-xs font-black text-zinc-200"
                        >
                            {{
                                getInstallStatus(
                                    activeCell,
                                ) === 'failed'
                                    ? 'Installation failed'
                                    : getInstallStatus(
                                          activeCell,
                                      ) ===
                                        'pending'
                                      ? 'Waiting to install'
                                      : 'Installation in progress'
                            }}
                        </p>

                        <p
                            class="mt-1 text-[11px] leading-4 text-zinc-500"
                        >
                            {{
                                getInstallStatus(
                                    activeCell,
                                ) === 'failed'
                                    ? 'Server controls are unavailable until the installation is retried successfully.'
                                    : 'Server controls will become available when installation has completed.'
                            }}
                        </p>
                    </div>
                </div>
            </div>

            <NavMixed :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <!--
            <NavFooter
                :items="footerNavItems"
            />
            -->

            <NavUser />
        </SidebarFooter>
    </Sidebar>

    <slot />
</template>