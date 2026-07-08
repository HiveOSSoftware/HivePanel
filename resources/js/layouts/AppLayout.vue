<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue'
import ConfirmHost from '@/components/ui/ConfirmHost.vue'
import type { BreadcrumbItemType } from '@/types'
import GlobalToasts from '@/components/ui/GlobalToasts.vue'

type CellStatus = 'offline' | 'starting' | 'running' | 'stopping'

interface Props {
    breadcrumbs?: BreadcrumbItemType[]

    activeCell?: any
    activeCellStatus?: CellStatus
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
})
</script>

<template>
    <AppLayout
        :breadcrumbs="breadcrumbs"
        :active-cell="activeCell"
        :active-cell-status="activeCellStatus"
    >
        <slot />
        <ConfirmHost />
        <GlobalToasts />
    </AppLayout>
</template>