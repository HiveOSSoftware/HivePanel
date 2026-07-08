<script setup lang="ts">
import InputError from '@/components/InputError.vue'
import TextLink from '@/components/TextLink.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { Hexagon, LoaderCircle, Lock, Mail } from 'lucide-vue-next'

defineProps<{
    status?: string
    canResetPassword: boolean
    oauthProviders: { provider: string }[]
}>()

const form = useForm({
    email: '',
    password: '',
    remember: false,
})

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    })
}
</script>

<template>
    <Head title="Log in" />

    <div class="relative min-h-screen overflow-hidden bg-surface-dark text-white">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top,#ffc4001f,transparent_35%),radial-gradient(circle_at_bottom_right,#ff8a0018,transparent_35%)]" />
        <div class="pointer-events-none absolute inset-0 opacity-[0.035] [background-image:linear-gradient(30deg,#fff_12%,transparent_12.5%,transparent_87%,#fff_87.5%,#fff),linear-gradient(150deg,#fff_12%,transparent_12.5%,transparent_87%,#fff_87.5%,#fff),linear-gradient(30deg,#fff_12%,transparent_12.5%,transparent_87%,#fff_87.5%,#fff),linear-gradient(150deg,#fff_12%,transparent_12.5%,transparent_87%,#fff_87.5%,#fff)] [background-position:0_0,0_0,24px_42px,24px_42px] [background-size:48px_84px]" />

        <main class="relative z-10 flex min-h-screen items-center justify-center px-4 py-10">
            <div class="w-full max-w-md">
                <div class="mb-8 text-center">
                    <img src="https://hivepanel.dev/assets/imgs/HivePanelLogo.png" alt="HivePanel" class="mx-auto h-16 w-auto" />

                    <h1 class="mt-5 text-3xl font-black tracking-tight">
                        Welcome back
                    </h1>

                    <p class="mt-2 text-sm text-zinc-400">
                        Log in to your account to continue.
                    </p>
                </div>

                <div class="rounded-panel border border-zinc-800 bg-surface/95 p-6 shadow-[0_25px_90px_rgba(0,0,0,0.45)] backdrop-blur sm:p-7">
                    <div v-if="status" class="mb-5 rounded-button border border-status-success/30 bg-status-success/10 px-4 py-3 text-sm font-bold text-status-success">
                        {{ status }}
                    </div>

                    <div v-if="oauthProviders.length" class="mb-5 space-y-2">
                        <a
                            v-for="provider in oauthProviders"
                            :key="provider.provider"
                            :href="route('social.redirect', provider.provider)"
                            class="flex w-full items-center justify-center rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-black text-zinc-300 transition hover:border-hive/40 hover:text-hive"
                        >
                            Continue with {{ provider.provider.charAt(0).toUpperCase() + provider.provider.slice(1) }}
                        </a>

                        <div class="flex items-center gap-3 py-2">
                            <div class="h-px flex-1 bg-zinc-800" />
                            <span class="text-xs font-black uppercase tracking-wide text-zinc-600">
                                Or
                            </span>
                            <div class="h-px flex-1 bg-zinc-800" />
                        </div>
                    </div>

                    <form class="space-y-5" @submit.prevent="submit">
                        <div>
                            <label for="email" class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                Email address
                            </label>

                            <div class="mt-2 flex items-center gap-3 rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 transition focus-within:border-hive/60">
                                <Mail class="size-4 text-zinc-500" />

                                <input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    required
                                    autofocus
                                    tabindex="1"
                                    autocomplete="email"
                                    placeholder="email@example.com"
                                    class="w-full bg-transparent text-sm font-bold text-white outline-none placeholder:text-zinc-600"
                                />
                            </div>

                            <InputError class="mt-2" :message="form.errors.email" />
                        </div>

                        <div>
                            <div class="flex items-center justify-between">
                                <label for="password" class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                    Password
                                </label>

                                <TextLink
                                    v-if="canResetPassword"
                                    :href="route('password.request')"
                                    class="text-xs font-black text-hive hover:text-hive-light"
                                    tabindex="5"
                                >
                                    Forgot password?
                                </TextLink>
                            </div>

                            <div class="mt-2 flex items-center gap-3 rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 transition focus-within:border-hive/60">
                                <Lock class="size-4 text-zinc-500" />

                                <input
                                    id="password"
                                    v-model="form.password"
                                    type="password"
                                    required
                                    tabindex="2"
                                    autocomplete="current-password"
                                    placeholder="Password"
                                    class="w-full bg-transparent text-sm font-bold text-white outline-none placeholder:text-zinc-600"
                                />
                            </div>

                            <InputError class="mt-2" :message="form.errors.password" />
                        </div>

                        <div class="flex items-center justify-between">
                            <label for="remember" class="flex cursor-pointer items-center gap-3 text-sm font-bold text-zinc-400">
                                <input
                                    id="remember"
                                    v-model="form.remember"
                                    type="checkbox"
                                    tabindex="3"
                                    class="size-4 rounded border-zinc-700 bg-[#0d0f11] text-hive focus:ring-hive"
                                />

                                <span>Remember me</span>
                            </label>
                        </div>

                        <button
                            type="submit"
                            tabindex="4"
                            :disabled="form.processing"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-button border border-hive bg-hive px-5 py-3 text-sm font-black text-black transition hover:bg-hive-light disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                            {{ form.processing ? 'Logging in...' : 'Log in' }}
                        </button>
                    </form>
                </div>

                <p class="mt-6 text-center text-xs font-bold text-zinc-600">
                    HivePanel · Game server management, rebuilt.
                </p>
            </div>
        </main>
    </div>
</template>