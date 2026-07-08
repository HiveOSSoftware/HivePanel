<script setup lang="ts">
import {
    SidebarGroup,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar'
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible'
import { Link, usePage } from '@inertiajs/vue3'
import { ChevronDown } from 'lucide-vue-next'
import { computed } from 'vue'
import type { NavItem } from '@/types'

type NavSingle = NavItem & {
    type?: 'item'
}

type NavGroup = {
    type: 'group'
    title: string
    items: NavItem[]
}

type SidebarNavEntry = NavSingle | NavGroup

defineProps<{
    items: SidebarNavEntry[]
}>()

const page = usePage()

const currentPath = computed(() => {
    return new URL(page.url, window.location.origin).pathname.replace(/\/$/, '')
})

function isGroup(item: SidebarNavEntry): item is NavGroup {
    return item.type === 'group'
}

function normalisePath(href: string) {
    return new URL(href, window.location.origin).pathname.replace(/\/$/, '')
}

function isActive(href: string) {
    const hrefPath = normalisePath(href)
    const path = currentPath.value

    if (/^\/cells\/[^/]+$/.test(hrefPath)) {
        return path === hrefPath
    }

    return path === hrefPath || path.startsWith(`${hrefPath}/`)
}
</script>

<template>
    <SidebarGroup>
        <SidebarMenu>
            <template
                v-for="item in items"
                :key="isGroup(item) ? item.title : item.href"
            >
                <Collapsible
                    v-if="isGroup(item)"
                    default-open
                    class="group/collapsible"
                >
                    <CollapsibleTrigger
                        class="flex w-full items-center justify-between rounded-button px-3 py-2 text-xs font-black uppercase tracking-wide text-zinc-500 transition hover:bg-surface-hover hover:text-zinc-300"
                    >
                        <span>{{ item.title }}</span>
                        <ChevronDown class="size-4 transition group-data-[state=open]/collapsible:rotate-180" />
                    </CollapsibleTrigger>

                    <CollapsibleContent>
                        <SidebarMenu>
                            <SidebarMenuItem
                                v-for="child in item.items"
                                :key="child.href"
                            >
                                <SidebarMenuButton
                                    as-child
                                    :is-active="isActive(child.href)"
                                >
                                    <Link :href="child.href">
                                        <component :is="child.icon" />
                                        <span>{{ child.title }}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </CollapsibleContent>
                </Collapsible>

                <SidebarMenuItem v-else>
                    <SidebarMenuButton
                        as-child
                        :is-active="isActive(item.href)"
                    >
                        <Link :href="item.href">
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </template>
        </SidebarMenu>
    </SidebarGroup>
</template>