<script setup lang="ts">
import ConfirmationModal from './ConfirmationModal.vue'
import { useConfirm } from '@/composables/useConfirm'

const { state, accept, cancel } = useConfirm()
</script>

<template>
    <ConfirmationModal
        :open="state.open"
        :title="state.title"
        :description="state.description"
        :confirm-text="state.confirmText"
        :cancel-text="state.cancelText"
        :danger="state.danger"
        :loading="state.loading"
        @confirm="accept"
        @cancel="cancel"
    />
</template>