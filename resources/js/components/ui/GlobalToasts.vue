<script setup lang="ts">
import { usePage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import { AlertTriangle, CheckCircle, X } from 'lucide-vue-next'

const page = usePage()
const visible = ref(false)

const message = computed(() => {
    const flash = page.props.flash as any
    const errors = page.props.errors as Record<string, string> | undefined

    if (flash?.success) return flash.success
    if (flash?.error) return flash.error

    const firstError = errors ? Object.values(errors)[0] : null
    return firstError || ''
})

const type = computed(() => {
    const flash = page.props.flash as any

    if (flash?.success) return 'success'
    return 'error'
})

watch(message, (value) => {
    if (!value) return

    visible.value = true

    setTimeout(() => {
        visible.value = false
    }, 4500)
}, { immediate: true })
</script>

<template>
    <div
        v-if="visible && message"
        class="fixed bottom-5 right-5 z-[100] w-[calc(100%-2rem)] max-w-md rounded-panel border px-5 py-4 shadow-[0_25px_80px_rgba(0,0,0,0.55)]"
        :class="type === 'success'
            ? 'border-status-success/40 bg-status-success/15 text-status-success'
            : 'border-status-danger/40 bg-status-danger/15 text-status-danger'"
    >
        <div class="flex items-start gap-3">
            <CheckCircle v-if="type === 'success'" class="mt-0.5 size-5 shrink-0" />
            <AlertTriangle v-else class="mt-0.5 size-5 shrink-0" />

            <div class="min-w-0 flex-1 text-sm font-bold leading-6">
                {{ message }}
            </div>

            <button class="shrink-0 opacity-70 transition hover:opacity-100" @click="visible = false">
                <X class="size-4" />
            </button>
        </div>
    </div>
</template>