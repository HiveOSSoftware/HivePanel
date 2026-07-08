<script setup lang="ts">
import AppContent from '@/components/AppContent.vue'
import AppShell from '@/components/AppShell.vue'
import AppSidebar from '@/components/AppSidebar.vue'
import AppSidebarHeader from '@/components/AppSidebarHeader.vue'
import { useActiveCell, type CellStatus } from '@/composables/useActiveCell'
import type { BreadcrumbItemType } from '@/types'
import { onUnmounted, watch } from 'vue'

type SidebarContext = 'dashboard' | 'server' | 'admin'

const props = withDefaults(defineProps<{
    breadcrumbs?: BreadcrumbItemType[]
    context?: SidebarContext
    activeCell?: any
    activeCellStatus?: CellStatus
}>(), {
    breadcrumbs: () => [],
    context: 'dashboard',
})

const {
    activeCell,
    activeCellStatus,
    setActiveCell,
    stopActiveCellPolling,
} = useActiveCell()

watch(
    () => props.activeCell,
    (cell) => {
        if (cell) {
            setActiveCell(cell, props.activeCellStatus)
        }
    },
    { immediate: true },
)

onUnmounted(() => {
    stopActiveCellPolling()
})
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar
            :context="context"
            :active-cell="activeCell"
            :active-cell-status="activeCellStatus"
        />

        <AppContent variant="sidebar">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <div v-if="activeCell?.lock?.locked" class="px-4 pt-5 sm:px-6 sm:pt-7 lg:px-8">
                <div class="rounded-panel border border-status-warning/40 bg-status-warning/10 p-4 text-status-warning">
                    <div class="text-sm font-black">
                        {{ activeCell.lock.reason || 'Server Locked' }}
                    </div>

                    <div class="mt-1 text-sm text-zinc-300">
                        {{ activeCell.lock.message || 'This server is temporarily locked.' }}
                    </div>
                </div>
            </div>
            <slot />
        </AppContent>
    </AppShell>
</template>