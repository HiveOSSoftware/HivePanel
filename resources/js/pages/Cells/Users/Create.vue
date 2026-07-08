<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ArrowLeft, UserPlus } from 'lucide-vue-next'
import { computed } from 'vue'

type PermissionGroups = Record<string, Record<string, string>>

const props = defineProps<{
    cell: any
    permissionGroups: PermissionGroups
}>()

const allPermissions = computed(() => {
    return Object.values(props.permissionGroups).flatMap(group => Object.keys(group))
})

const presets = {
    readonly: [
        'cell.view',
        'console.view',
        'files.view',
        'files.read',
        'backups.view',
        'schedules.view',
        'databases.view',
        'settings.view',
    ],
    developer: [
        'cell.view',
        'console.view',
        'console.send',
        'files.view',
        'files.read',
        'files.write',
        'files.upload',
        'schedules.view',
        'schedules.create',
        'schedules.update',
        'databases.view',
        'settings.view',
        'startup.update',
    ],
    admin: [] as string[],
}

const form = useForm({
    email: '',
    permissions: [] as string[],
})

function applyPreset(preset: keyof typeof presets) {
    form.permissions = preset === 'admin'
        ? [...allPermissions.value]
        : [...presets[preset]]
}

function togglePermission(permission: string) {
    form.permissions = form.permissions.includes(permission)
        ? form.permissions.filter(item => item !== permission)
        : [...form.permissions, permission]
}

function groupChecked(permissions: string[]) {
    return permissions.every(permission => form.permissions.includes(permission))
}

function toggleGroup(permissions: string[]) {
    form.permissions = groupChecked(permissions)
        ? form.permissions.filter(permission => !permissions.includes(permission))
        : Array.from(new Set([...form.permissions, ...permissions]))
}

function submit() {
    form.post(`/cells/${props.cell.id}/users`)
}
</script>

<template>
    <AppLayout
        context="server"
        :active-cell="cell"
        :active-cell-status="cell.status ?? 'offline'"
    >
        <Head :title="`Invite User - ${cell.name}`" />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <form class="mx-auto space-y-5" @submit.prevent="submit">
                    <section class="overflow-hidden rounded-panel border border-hive/10 bg-surface">
                        <div class="relative p-5 sm:p-6">
                            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(255,196,0,0.12),transparent_35%)]" />

                            <div class="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="rounded-xl border border-hive/20 bg-hive/10 p-3">
                                        <UserPlus class="size-6 text-hive" />
                                    </div>

                                    <div>
                                        <h1 class="text-2xl font-black sm:text-3xl">
                                            Invite User
                                        </h1>
                                        <p class="mt-1 text-sm text-zinc-400">
                                            Give another user granular access to {{ cell.name }}.
                                        </p>
                                    </div>
                                </div>

                                <Link
                                    :href="`/cells/${cell.id}/users`"
                                    class="inline-flex items-center justify-center gap-2 rounded-button border border-zinc-800 px-4 py-2 text-sm font-bold text-zinc-300 transition hover:border-hive/30 hover:text-hive"
                                >
                                    <ArrowLeft class="size-4" />
                                    Back
                                </Link>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <label class="block text-xs font-black uppercase tracking-wide text-zinc-500">
                            Email Address
                        </label>

                        <input
                            v-model="form.email"
                            type="email"
                            class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-3 py-2 text-sm text-white outline-none transition placeholder:text-zinc-600 focus:border-hive/50"
                            placeholder="user@example.com"
                        />

                        <p v-if="form.errors.email" class="mt-2 text-xs font-bold text-status-danger">
                            {{ form.errors.email }}
                        </p>

                        <div class="mt-5">
                            <label class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                Presets
                            </label>

                            <div class="mt-3 grid grid-cols-3 gap-2">
                                <button type="button" class="rounded-button border border-zinc-800 px-3 py-2 text-xs font-bold text-zinc-300 transition hover:border-hive/30 hover:text-hive" @click="applyPreset('readonly')">
                                    Read Only
                                </button>

                                <button type="button" class="rounded-button border border-zinc-800 px-3 py-2 text-xs font-bold text-zinc-300 transition hover:border-hive/30 hover:text-hive" @click="applyPreset('developer')">
                                    Developer
                                </button>

                                <button type="button" class="rounded-button border border-hive/20 bg-hive/10 px-3 py-2 text-xs font-bold text-hive transition hover:bg-hive/15" @click="applyPreset('admin')">
                                    Admin
                                </button>
                            </div>
                        </div>
                    </section>

                    <section class="grid gap-4 xl:grid-cols-2">
                        <div
                            v-for="(permissions, group) in permissionGroups"
                            :key="group"
                            class="rounded-panel border border-zinc-800 bg-surface p-5"
                        >
                            <div class="mb-3 flex items-center justify-between">
                                <h3 class="text-sm font-black text-zinc-200">
                                    {{ group }}
                                </h3>

                                <button
                                    type="button"
                                    class="text-xs font-bold text-hive hover:underline"
                                    @click="toggleGroup(Object.keys(permissions))"
                                >
                                    {{ groupChecked(Object.keys(permissions)) ? 'Clear' : 'Select all' }}
                                </button>
                            </div>

                            <div class="space-y-2">
                                <label
                                    v-for="(label, permission) in permissions"
                                    :key="permission"
                                    class="flex cursor-pointer items-start gap-3 rounded-md p-2 transition hover:bg-white/5"
                                >
                                    <input
                                        type="checkbox"
                                        class="mt-1 rounded border-zinc-700 bg-black text-hive focus:ring-hive"
                                        :checked="form.permissions.includes(permission)"
                                        @change="togglePermission(permission)"
                                    />

                                    <span>
                                        <span class="block text-sm font-bold text-zinc-300">
                                            {{ label }}
                                        </span>
                                        <span class="block text-xs text-zinc-600">
                                            {{ permission }}
                                        </span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </section>

                    <p v-if="form.errors.permissions" class="text-xs font-bold text-status-danger">
                        {{ form.errors.permissions }}
                    </p>

                    <div class="flex justify-end gap-2">
                        <Link
                            :href="`/cells/${cell.id}/users`"
                            class="rounded-button border border-zinc-800 px-4 py-2 text-sm font-bold text-zinc-300 transition hover:text-white"
                        >
                            Cancel
                        </Link>

                        <button
                            type="submit"
                            class="inline-flex items-center gap-2 rounded-button bg-hive px-4 py-2 text-sm font-black text-black transition hover:bg-hive/90 disabled:opacity-50"
                            :disabled="form.processing"
                        >
                            <UserPlus class="size-4" />
                            Invite User
                        </button>
                    </div>
                </form>
            </main>
        </div>
    </AppLayout>
</template>