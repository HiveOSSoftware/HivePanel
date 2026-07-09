<script setup lang="ts">
import { useAppSettings } from '@/composables/useAppSettings'

const { appLogo } = useAppSettings()
interface Props {
    class?: string;
}

defineProps<Props>();
</script>

<template>
    <img v-if="appLogo" :src="appLogo" alt="Logo" class="mx-auto" style="padding: 0px 30px;"/>
    <img v-else src="https://cdn.thatbritish.one/HivePanelLogo.png" alt="Logo" class="mx-auto" style="padding: 0px 30px;"/>
</template>
