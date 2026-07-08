<script setup lang="ts">
defineProps<{
    schedule: any
    formatDate: (value?: string) => string
}>()
</script>

<template>
    <section class="rounded-panel border border-zinc-800 bg-surface p-5">
        <h2 class="text-lg font-black">Schedule Info</h2>

        <div class="mt-4 space-y-3 text-sm">
            <div class="flex justify-between gap-4">
                <span class="text-zinc-500">Last run</span>
                <span class="font-bold text-zinc-300">{{ formatDate(schedule.last_run_at) }}</span>
            </div>

            <div class="flex justify-between gap-4">
                <span class="text-zinc-500">Next run</span>
                <span class="font-bold text-zinc-300">{{ formatDate(schedule.next_run_at) }}</span>
            </div>
        </div>
    </section>
</template>