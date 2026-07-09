<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import {
    Building2,
    Globe2,
    Image,
    KeyRound,
    Lock,
    Mail,
    Save,
    Send,
    Settings,
    ShieldCheck,
} from 'lucide-vue-next'
import { computed, ref } from 'vue'

type SettingsPayload = {
    general: {
        company_name: string
        company_logo?: string | null
        require_2fa: 'not_required' | 'admin_only' | 'all_users'
        default_language: string
    }
    security: {
        allow_registration: boolean
        require_email_verification: boolean
        session_lifetime: number
        password_min_length: number
    }
    mail: {
        host?: string | null
        port?: number | null
        encryption?: 'none' | 'tls' | 'ssl' | null
        username?: string | null
        password?: string | null
        from_address?: string | null
        from_name?: string | null
    }
    captcha: {
        enabled: boolean
        provider: 'turnstile' | 'recaptcha' | 'hcaptcha'
        site_key?: string | null
        secret_key?: string | null
    }
}

type OAuthProvider = {
    provider: 'discord' | 'google' | 'github'
    enabled: boolean
    client_id?: string | null
    client_secret?: string | null
    redirect_url?: string | null
}

const props = defineProps<{
    settings: SettingsPayload
    oauthProviders: Record<string, OAuthProvider>
}>()

const tabs = [
    { key: 'general', label: 'General', icon: Building2 },
    { key: 'security', label: 'Security', icon: Lock },
    { key: 'mail', label: 'Mail', icon: Mail },
    { key: 'captcha', label: 'Captcha', icon: ShieldCheck },
    { key: 'oauth', label: 'Social Providers', icon: KeyRound },
] as const

const activeTab = ref<(typeof tabs)[number]['key']>('general')

const generalForm = useForm({
    company_name: props.settings.general.company_name ?? 'HivePanel',
    company_logo: props.settings.general.company_logo ?? '',
    require_2fa: props.settings.general.require_2fa ?? 'not_required',
    default_language: props.settings.general.default_language ?? 'en',
})

const securityForm = useForm({
    allow_registration: props.settings.security?.allow_registration ?? false,
    require_email_verification: props.settings.security?.require_email_verification ?? false,
    session_lifetime: props.settings.security?.session_lifetime ?? 120,
    password_min_length: props.settings.security?.password_min_length ?? 8,
})

const mailForm = useForm({
    host: props.settings.mail.host ?? '',
    port: props.settings.mail.port ?? 587,
    encryption: props.settings.mail.encryption ?? 'tls',
    username: props.settings.mail.username ?? '',
    password: '',
    from_address: props.settings.mail.from_address ?? '',
    from_name: props.settings.mail.from_name ?? '',
})

const testMailForm = useForm({
    email: '',
})

const captchaForm = useForm({
    enabled: props.settings.captcha.enabled ?? false,
    provider: props.settings.captcha.provider ?? 'turnstile',
    site_key: props.settings.captcha.site_key ?? '',
    secret_key: '',
})

const oauthForm = useForm({
    providers: {
        discord: {
            enabled: props.oauthProviders.discord?.enabled ?? false,
            client_id: props.oauthProviders.discord?.client_id ?? '',
            client_secret: '',
            redirect_url: props.oauthProviders.discord?.redirect_url ?? '',
        },
        google: {
            enabled: props.oauthProviders.google?.enabled ?? false,
            client_id: props.oauthProviders.google?.client_id ?? '',
            client_secret: '',
            redirect_url: props.oauthProviders.google?.redirect_url ?? '',
        },
        github: {
            enabled: props.oauthProviders.github?.enabled ?? false,
            client_id: props.oauthProviders.github?.client_id ?? '',
            client_secret: '',
            redirect_url: props.oauthProviders.github?.redirect_url ?? '',
        },
    },
})

const providerLabels: Record<string, string> = {
    discord: 'Discord',
    google: 'Google',
    github: 'GitHub',
}

const providerHelp: Record<string, string> = {
    discord: 'Allow users to sign in using their Discord account.',
    google: 'Allow users to sign in using their Google account.',
    github: 'Allow users to sign in using their GitHub account.',
}

const activeTabLabel = computed(() => {
    return tabs.find(tab => tab.key === activeTab.value)?.label ?? 'Settings'
})

function submitGeneral() {
    generalForm.patch('/admin/settings/general', {
        preserveScroll: true,
    })
}

function submitSecurity() {
    securityForm.patch('/admin/settings/security', {
        preserveScroll: true,
    })
}

function submitMail() {
    mailForm.patch('/admin/settings/mail', {
        preserveScroll: true,
        onSuccess: () => {
            mailForm.password = ''
        },
    })
}

function sendTestMail() {
    testMailForm.post('/admin/settings/mail/test', {
        preserveScroll: true,
        onSuccess: () => {
            testMailForm.email = ''
        },
    })
}

function submitCaptcha() {
    captchaForm.patch('/admin/settings/captcha', {
        preserveScroll: true,
        onSuccess: () => {
            captchaForm.secret_key = ''
        },
    })
}

function submitOAuth() {
    oauthForm.patch('/admin/settings/oauth', {
        preserveScroll: true,
        onSuccess: () => {
            oauthForm.providers.discord.client_secret = ''
            oauthForm.providers.google.client_secret = ''
            oauthForm.providers.github.client_secret = ''
        },
    })
}
</script>

<template>
    <AppLayout context="admin">
        <Head title="Admin Settings" />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex items-center gap-3">
                                <Settings class="size-6 text-hive" />

                                <div>
                                    <h1 class="text-2xl font-black sm:text-3xl">
                                        Settings
                                    </h1>

                                    <p class="mt-2 text-sm text-zinc-400">
                                        Configure branding, security, mail, captcha, and social authentication.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="grid gap-5 xl:grid-cols-[260px_1fr]">
                        <aside class="rounded-panel border border-zinc-800 bg-surface p-3">
                            <button
                                v-for="tab in tabs"
                                :key="tab.key"
                                type="button"
                                class="flex w-full items-center gap-3 rounded-button px-3 py-3 text-left text-sm font-black transition"
                                :class="activeTab === tab.key
                                    ? 'bg-hive text-black'
                                    : 'text-zinc-400 hover:bg-surface-light hover:text-white'"
                                @click="activeTab = tab.key"
                            >
                                <component :is="tab.icon" class="size-4" />
                                {{ tab.label }}
                            </button>
                        </aside>

                        <div class="space-y-5">
                            <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                                <div>
                                    <h2 class="text-lg font-black text-white">
                                        {{ activeTabLabel }}
                                    </h2>
                                    <p class="mt-1 text-sm text-zinc-500">
                                        Update {{ activeTabLabel.toLowerCase() }} settings.
                                    </p>
                                </div>
                            </section>

                            <form
                                v-if="activeTab === 'general'"
                                class="space-y-5"
                                @submit.prevent="submitGeneral"
                            >
                                <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                                    <div class="grid gap-5 lg:grid-cols-2">
                                        <div>
                                            <label class="flex items-center gap-2 text-xs font-black uppercase tracking-wide text-zinc-500">
                                                <Building2 class="size-4" />
                                                Company Name
                                            </label>

                                            <input
                                                v-model="generalForm.company_name"
                                                type="text"
                                                class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-3 py-2 text-sm font-bold text-white outline-none transition placeholder:text-zinc-600 focus:border-hive/50"
                                                placeholder="HivePanel"
                                            />

                                            <p v-if="generalForm.errors.company_name" class="mt-2 text-xs font-bold text-status-danger">
                                                {{ generalForm.errors.company_name }}
                                            </p>
                                        </div>

                                        <div>
                                            <label class="flex items-center gap-2 text-xs font-black uppercase tracking-wide text-zinc-500">
                                                <Globe2 class="size-4" />
                                                Default Language
                                            </label>

                                            <select
                                                v-model="generalForm.default_language"
                                                class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-3 py-2 text-sm font-bold text-white outline-none transition focus:border-hive/50"
                                            >
                                                <option value="en">English</option>
                                                <option value="en-GB">English (UK)</option>
                                            </select>

                                            <p v-if="generalForm.errors.default_language" class="mt-2 text-xs font-bold text-status-danger">
                                                {{ generalForm.errors.default_language }}
                                            </p>
                                        </div>

                                        <div class="lg:col-span-2">
                                            <label class="flex items-center gap-2 text-xs font-black uppercase tracking-wide text-zinc-500">
                                                <Image class="size-4" />
                                                Company Logo URL
                                            </label>

                                            <input
                                                v-model="generalForm.company_logo"
                                                type="text"
                                                class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-3 py-2 text-sm font-bold text-white outline-none transition placeholder:text-zinc-600 focus:border-hive/50"
                                                placeholder="https://example.com/logo.png"
                                            />

                                            <p class="mt-2 text-xs text-zinc-600">
                                                Used later for the sidebar, login page, and emails.
                                            </p>

                                            <p v-if="generalForm.errors.company_logo" class="mt-2 text-xs font-bold text-status-danger">
                                                {{ generalForm.errors.company_logo }}
                                            </p>
                                        </div>

                                        <div class="lg:col-span-2">
                                            <label class="flex items-center gap-2 text-xs font-black uppercase tracking-wide text-zinc-500">
                                                <Lock class="size-4" />
                                                Require Two-Factor Authentication
                                            </label>

                                            <select
                                                v-model="generalForm.require_2fa"
                                                class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-3 py-2 text-sm font-bold text-white outline-none transition focus:border-hive/50"
                                            >
                                                <option value="not_required">Not Required</option>
                                                <option value="admin_only">Admin Only</option>
                                                <option value="all_users">All Users</option>
                                            </select>

                                            <p class="mt-2 text-xs text-zinc-600">
                                                This controls who must enable 2FA before using the panel.
                                            </p>

                                            <p v-if="generalForm.errors.require_2fa" class="mt-2 text-xs font-bold text-status-danger">
                                                {{ generalForm.errors.require_2fa }}
                                            </p>
                                        </div>
                                    </div>
                                </section>

                                <div class="flex justify-end">
                                    <button
                                        type="submit"
                                        class="inline-flex items-center gap-2 rounded-button bg-hive px-4 py-2 text-sm font-black text-black transition hover:bg-hive/90 disabled:opacity-50"
                                        :disabled="generalForm.processing"
                                    >
                                        <Save class="size-4" />
                                        Save General Settings
                                    </button>
                                </div>
                            </form>

                            <form
                                v-if="activeTab === 'security'"
                                class="space-y-5"
                                @submit.prevent="submitSecurity"
                            >
                                <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                                    <div class="space-y-4">
                                        <label class="flex cursor-pointer items-center justify-between rounded-button border border-zinc-900 bg-[#0d0f11] p-4">
                                            <div>
                                                <div class="font-black text-white">
                                                    Allow Registration
                                                </div>
                                                <div class="text-sm text-zinc-500">
                                                    Allow new users to create accounts without being created by an admin.
                                                </div>
                                            </div>

                                            <input
                                                v-model="securityForm.allow_registration"
                                                type="checkbox"
                                                class="size-5 rounded border-zinc-700 bg-black text-hive focus:ring-hive"
                                            />
                                        </label>

                                        <label class="flex cursor-pointer items-center justify-between rounded-button border border-zinc-900 bg-[#0d0f11] p-4">
                                            <div>
                                                <div class="font-black text-white">
                                                    Require Email Verification
                                                </div>
                                                <div class="text-sm text-zinc-500">
                                                    Users must verify their email address before accessing the panel.
                                                </div>
                                            </div>

                                            <input
                                                v-model="securityForm.require_email_verification"
                                                type="checkbox"
                                                class="size-5 rounded border-zinc-700 bg-black text-hive focus:ring-hive"
                                            />
                                        </label>
                                    </div>

                                    <div class="mt-5 grid gap-5 lg:grid-cols-2">
                                        <div>
                                            <label class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                                Session Lifetime
                                            </label>

                                            <input
                                                v-model="securityForm.session_lifetime"
                                                type="number"
                                                min="5"
                                                max="10080"
                                                class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-3 py-2 text-sm font-bold text-white outline-none transition focus:border-hive/50"
                                            />

                                            <p class="mt-2 text-xs text-zinc-600">
                                                Session lifetime in minutes. Max: 10080 minutes.
                                            </p>

                                            <p v-if="securityForm.errors.session_lifetime" class="mt-2 text-xs font-bold text-status-danger">
                                                {{ securityForm.errors.session_lifetime }}
                                            </p>
                                        </div>

                                        <div>
                                            <label class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                                Minimum Password Length
                                            </label>

                                            <input
                                                v-model="securityForm.password_min_length"
                                                type="number"
                                                min="8"
                                                max="128"
                                                class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-3 py-2 text-sm font-bold text-white outline-none transition focus:border-hive/50"
                                            />

                                            <p class="mt-2 text-xs text-zinc-600">
                                                Used for local account password validation.
                                            </p>

                                            <p v-if="securityForm.errors.password_min_length" class="mt-2 text-xs font-bold text-status-danger">
                                                {{ securityForm.errors.password_min_length }}
                                            </p>
                                        </div>
                                    </div>
                                </section>

                                <div class="flex justify-end">
                                    <button
                                        type="submit"
                                        class="inline-flex items-center gap-2 rounded-button bg-hive px-4 py-2 text-sm font-black text-black transition hover:bg-hive/90 disabled:opacity-50"
                                        :disabled="securityForm.processing"
                                    >
                                        <Save class="size-4" />
                                        Save Security Settings
                                    </button>
                                </div>
                            </form>

                            <div
                                v-if="activeTab === 'mail'"
                                class="space-y-5"
                            >
                                <form
                                    class="space-y-5"
                                    @submit.prevent="submitMail"
                                >
                                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                                        <div class="grid gap-5 lg:grid-cols-2">
                                            <div>
                                                <label class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                                    SMTP Host
                                                </label>

                                                <input
                                                    v-model="mailForm.host"
                                                    type="text"
                                                    class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-3 py-2 text-sm font-bold text-white outline-none transition focus:border-hive/50"
                                                    placeholder="smtp.mailgun.org"
                                                />
                                            </div>

                                            <div>
                                                <label class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                                    SMTP Port
                                                </label>

                                                <input
                                                    v-model="mailForm.port"
                                                    type="number"
                                                    class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-3 py-2 text-sm font-bold text-white outline-none transition focus:border-hive/50"
                                                    placeholder="587"
                                                />
                                            </div>

                                            <div>
                                                <label class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                                    Encryption
                                                </label>

                                                <select
                                                    v-model="mailForm.encryption"
                                                    class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-3 py-2 text-sm font-bold text-white outline-none transition focus:border-hive/50"
                                                >
                                                    <option value="none">None</option>
                                                    <option value="tls">TLS</option>
                                                    <option value="ssl">SSL</option>
                                                </select>
                                            </div>

                                            <div>
                                                <label class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                                    Username
                                                </label>

                                                <input
                                                    v-model="mailForm.username"
                                                    type="text"
                                                    class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-3 py-2 text-sm font-bold text-white outline-none transition focus:border-hive/50"
                                                    placeholder="Optional"
                                                />
                                            </div>

                                            <div>
                                                <label class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                                    Password
                                                </label>

                                                <input
                                                    v-model="mailForm.password"
                                                    type="password"
                                                    class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-3 py-2 text-sm font-bold text-white outline-none transition focus:border-hive/50"
                                                    placeholder="Leave blank to keep existing password"
                                                />
                                            </div>

                                            <div>
                                                <label class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                                    Mail From Address
                                                </label>

                                                <input
                                                    v-model="mailForm.from_address"
                                                    type="email"
                                                    class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-3 py-2 text-sm font-bold text-white outline-none transition focus:border-hive/50"
                                                    placeholder="noreply@example.com"
                                                />
                                            </div>

                                            <div class="lg:col-span-2">
                                                <label class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                                    Mail From Name
                                                </label>

                                                <input
                                                    v-model="mailForm.from_name"
                                                    type="text"
                                                    class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-3 py-2 text-sm font-bold text-white outline-none transition focus:border-hive/50"
                                                    placeholder="HivePanel"
                                                />
                                            </div>
                                        </div>
                                    </section>

                                    <div class="flex justify-end">
                                        <button
                                            type="submit"
                                            class="inline-flex items-center gap-2 rounded-button bg-hive px-4 py-2 text-sm font-black text-black transition hover:bg-hive/90 disabled:opacity-50"
                                            :disabled="mailForm.processing"
                                        >
                                            <Save class="size-4" />
                                            Save Mail Settings
                                        </button>
                                    </div>
                                </form>

                                <form
                                    class="space-y-5"
                                    @submit.prevent="sendTestMail"
                                >
                                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                                        <div>
                                            <h3 class="text-lg font-black text-white">
                                                Send Test Email
                                            </h3>
                                            <p class="mt-1 text-sm text-zinc-500">
                                                Send a test email using the currently saved SMTP settings.
                                            </p>
                                        </div>

                                        <div class="mt-5">
                                            <label class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                                Recipient Email
                                            </label>

                                            <input
                                                v-model="testMailForm.email"
                                                type="email"
                                                class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-3 py-2 text-sm font-bold text-white outline-none transition focus:border-hive/50"
                                                placeholder="you@example.com"
                                            />

                                            <p v-if="testMailForm.errors.email" class="mt-2 text-xs font-bold text-status-danger">
                                                {{ testMailForm.errors.email }}
                                            </p>
                                        </div>
                                    </section>

                                    <div class="flex justify-end">
                                        <button
                                            type="submit"
                                            class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-surface-light px-4 py-2 text-sm font-black text-white transition hover:border-hive/50 disabled:opacity-50"
                                            :disabled="testMailForm.processing"
                                        >
                                            <Send class="size-4" />
                                            Send Test Email
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <form
                                v-if="activeTab === 'captcha'"
                                class="space-y-5"
                                @submit.prevent="submitCaptcha"
                            >
                                <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                                    <label class="flex cursor-pointer items-center justify-between rounded-button border border-zinc-900 bg-[#0d0f11] p-4">
                                        <div>
                                            <div class="font-black text-white">
                                                Enable Captcha
                                            </div>
                                            <div class="text-sm text-zinc-500">
                                                Require captcha checks during authentication.
                                            </div>
                                        </div>

                                        <input
                                            v-model="captchaForm.enabled"
                                            type="checkbox"
                                            class="size-5 rounded border-zinc-700 bg-black text-hive focus:ring-hive"
                                        />
                                    </label>

                                    <div class="mt-5 grid gap-5 lg:grid-cols-2">
                                        <div>
                                            <label class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                                Provider
                                            </label>

                                            <select
                                                v-model="captchaForm.provider"
                                                class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-3 py-2 text-sm font-bold text-white outline-none transition focus:border-hive/50"
                                            >
                                                <option value="turnstile">Cloudflare Turnstile</option>
                                                <option value="recaptcha">Google reCAPTCHA</option>
                                                <option value="hcaptcha">hCaptcha</option>
                                            </select>
                                        </div>

                                        <div>
                                            <label class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                                Site Key
                                            </label>

                                            <input
                                                v-model="captchaForm.site_key"
                                                type="text"
                                                class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-3 py-2 text-sm font-bold text-white outline-none transition focus:border-hive/50"
                                            />
                                        </div>

                                        <div class="lg:col-span-2">
                                            <label class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                                Secret Key
                                            </label>

                                            <input
                                                v-model="captchaForm.secret_key"
                                                type="password"
                                                class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-3 py-2 text-sm font-bold text-white outline-none transition focus:border-hive/50"
                                                placeholder="Leave blank to keep existing secret"
                                            />
                                        </div>
                                    </div>
                                </section>

                                <div class="flex justify-end">
                                    <button
                                        type="submit"
                                        class="inline-flex items-center gap-2 rounded-button bg-hive px-4 py-2 text-sm font-black text-black transition hover:bg-hive/90 disabled:opacity-50"
                                        :disabled="captchaForm.processing"
                                    >
                                        <Save class="size-4" />
                                        Save Captcha Settings
                                    </button>
                                </div>
                            </form>

                            <form
                                v-if="activeTab === 'oauth'"
                                class="space-y-5"
                                @submit.prevent="submitOAuth"
                            >
                                <section class="space-y-4">
                                    <div
                                        v-for="provider in ['discord', 'google', 'github']"
                                        :key="provider"
                                        class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6"
                                    >
                                        <div class="mb-5 flex items-center justify-between gap-4">
                                            <div>
                                                <h3 class="text-lg font-black text-white">
                                                    {{ providerLabels[provider] }}
                                                </h3>
                                                <p class="mt-1 text-sm text-zinc-500">
                                                    {{ providerHelp[provider] }}
                                                </p>
                                            </div>

                                            <label class="flex cursor-pointer items-center gap-3">
                                                <span class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                                    Enabled
                                                </span>
                                                <input
                                                    v-model="oauthForm.providers[provider].enabled"
                                                    type="checkbox"
                                                    class="size-5 rounded border-zinc-700 bg-black text-hive focus:ring-hive"
                                                />
                                            </label>
                                        </div>

                                        <div class="grid gap-5 lg:grid-cols-2">
                                            <div>
                                                <label class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                                    Client ID
                                                </label>

                                                <input
                                                    v-model="oauthForm.providers[provider].client_id"
                                                    type="text"
                                                    class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-3 py-2 text-sm font-bold text-white outline-none transition focus:border-hive/50"
                                                />
                                            </div>

                                            <div>
                                                <label class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                                    Client Secret
                                                </label>

                                                <input
                                                    v-model="oauthForm.providers[provider].client_secret"
                                                    type="password"
                                                    class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-3 py-2 text-sm font-bold text-white outline-none transition focus:border-hive/50"
                                                    placeholder="Leave blank to keep existing secret"
                                                />
                                            </div>

                                            <div class="lg:col-span-2">
                                                <label class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                                    Redirect URL
                                                </label>

                                                <input
                                                    v-model="oauthForm.providers[provider].redirect_url"
                                                    type="url"
                                                    class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-3 py-2 text-sm font-bold text-white outline-none transition focus:border-hive/50"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </section>

                                <div class="flex justify-end">
                                    <button
                                        type="submit"
                                        class="inline-flex items-center gap-2 rounded-button bg-hive px-4 py-2 text-sm font-black text-black transition hover:bg-hive/90 disabled:opacity-50"
                                        :disabled="oauthForm.processing"
                                    >
                                        <Save class="size-4" />
                                        Save Social Providers
                                    </button>
                                </div>
                            </form>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </AppLayout>
</template>