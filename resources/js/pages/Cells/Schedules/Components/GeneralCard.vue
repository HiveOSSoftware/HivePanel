<script setup lang="ts">
defineProps<{
    form: any
    cron: any
}>()
</script>

<template>
    <section class="rounded-panel border border-zinc-800 bg-surface p-5">
        <h2 class="text-lg font-black">General</h2>

        <div class="mt-5 grid gap-4 md:grid-cols-2">
            <label class="space-y-2">
                <span class="text-xs font-black uppercase tracking-wide text-zinc-400">Name</span>
                <input
                    v-model="form.name"
                    class="w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 text-sm text-zinc-200 outline-none focus:border-hive"
                />
            </label>

            <label class="space-y-2">
                <span class="text-xs font-black uppercase tracking-wide text-zinc-400">Timezone</span>
                <input
                    v-model="form.timezone"
                    class="w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 text-sm text-zinc-200 outline-none focus:border-hive"
                />
            </label>
        </div>

        <div class="mt-5 grid grid-cols-2 gap-3 md:grid-cols-5">
            <label class="space-y-2">
                <span class="text-xs font-black uppercase tracking-wide text-zinc-400">Minute</span>
                <input v-model="cron.minute" class="w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 font-mono text-sm font-bold text-zinc-200 outline-none focus:border-hive" />
            </label>

            <label class="space-y-2">
                <span class="text-xs font-black uppercase tracking-wide text-zinc-400">Hour</span>
                <input v-model="cron.hour" class="w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 font-mono text-sm font-bold text-zinc-200 outline-none focus:border-hive" />
            </label>

            <label class="space-y-2">
                <span class="text-xs font-black uppercase tracking-wide text-zinc-400">Day</span>
                <input v-model="cron.day" class="w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 font-mono text-sm font-bold text-zinc-200 outline-none focus:border-hive" />
            </label>

            <label class="space-y-2">
                <span class="text-xs font-black uppercase tracking-wide text-zinc-400">Month</span>
                <input v-model="cron.month" class="w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 font-mono text-sm font-bold text-zinc-200 outline-none focus:border-hive" />
            </label>

            <label class="space-y-2">
                <span class="text-xs font-black uppercase tracking-wide text-zinc-400">Day of Week</span>
                <input v-model="cron.dayOfWeek" class="w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 font-mono text-sm font-bold text-zinc-200 outline-none focus:border-hive" />
            </label>
        </div>

        <div class="mt-5 grid gap-3 md:grid-cols-3">
            <label class="flex items-center justify-between gap-4 rounded-button border border-zinc-800 bg-surface-light px-4 py-3">
                <span>
                    <span class="block text-sm font-bold text-zinc-300">Enabled</span>
                    <span class="text-xs text-zinc-500">Allow this schedule to run automatically.</span>
                </span>
                <input v-model="form.enabled" type="checkbox" class="accent-hive" />
            </label>

            <label class="flex items-center justify-between gap-4 rounded-button border border-zinc-800 bg-surface-light px-4 py-3">
                <span>
                    <span class="block text-sm font-bold text-zinc-300">Only online</span>
                    <span class="text-xs text-zinc-500">Only run when the server is online.</span>
                </span>
                <input v-model="form.only_when_online" type="checkbox" class="accent-hive" />
            </label>

            <label class="flex items-center justify-between gap-4 rounded-button border border-zinc-800 bg-surface-light px-4 py-3">
                <span>
                    <span class="block text-sm font-bold text-zinc-300">Continue on fail</span>
                    <span class="text-xs text-zinc-500">Continue remaining actions if one fails.</span>
                </span>
                <input v-model="form.continue_on_failure" type="checkbox" class="accent-hive" />
            </label>
        </div>
    </section>
</template>