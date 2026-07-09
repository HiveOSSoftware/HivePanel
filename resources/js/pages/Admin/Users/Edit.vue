<script setup lang="ts">
import InputError from '@/components/InputError.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ArrowLeft, Save, Shield, User } from 'lucide-vue-next'

const props = defineProps<{ user: any }>()

const form = useForm({
    name: props.user.name ?? '',
    email: props.user.email ?? '',
    is_admin: props.user.is_admin ?? false,
})

function submit() {
    form.patch(`/admin/users/${props.user.id}`)
}
</script>

<template>
    <AppLayout :context="'admin'">
        <Head :title="`Edit ${user.name}`" />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto max-w-3xl space-y-5">
                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center gap-3">
                                <User class="size-6 text-hive" />
                                <div>
                                    <h1 class="text-2xl font-black sm:text-3xl">Edit User</h1>
                                    <p class="mt-2 text-sm text-zinc-400">Update this user’s profile details.</p>
                                </div>
                            </div>

                            <Link :href="`/admin/users/${user.id}`" class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-2 text-sm font-black text-zinc-300 hover:text-hive">
                                <ArrowLeft class="size-4" />
                                Back
                            </Link>
                        </div>
                    </section>

                    <form class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6" @submit.prevent="submit">
                        <div class="space-y-5">
                            <div>
                                <label class="text-xs font-black uppercase tracking-wide text-zinc-500">Name</label>
                                <input v-model="form.name" type="text" class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition focus:border-hive/50" />
                                <InputError class="mt-2" :message="form.errors.name" />
                            </div>

                            <div>
                                <label class="text-xs font-black uppercase tracking-wide text-zinc-500">Email</label>
                                <input v-model="form.email" type="email" class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition focus:border-hive/50" />
                                <InputError class="mt-2" :message="form.errors.email" />
                            </div>

                            <label class="flex cursor-pointer items-center gap-3 rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                                <input v-model="form.is_admin" type="checkbox" class="size-4 rounded border-zinc-700 bg-[#0d0f11] text-hive focus:ring-hive" />
                                <span class="inline-flex items-center gap-2 text-sm font-bold text-zinc-300">
                                    <Shield class="size-4 text-hive" />
                                    Administrator access
                                </span>
                            </label>

                            <div class="flex justify-end">
                                <button type="submit" :disabled="form.processing" class="inline-flex items-center gap-2 rounded-button border border-hive bg-hive px-5 py-3 text-sm font-black text-black transition hover:opacity-90 disabled:opacity-50">
                                    <Save class="size-4" />
                                    {{ form.processing ? 'Saving...' : 'Save User' }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </AppLayout>
</template>