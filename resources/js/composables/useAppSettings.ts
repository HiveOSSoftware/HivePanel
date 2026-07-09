import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

type AppSettings = {
    name: string
    logo?: string | null
    general?: {
        company_name?: string
        company_logo?: string | null
        require_2fa?: string
        default_language?: string
    }
}

export function useAppSettings() {
    const page = usePage()

    const appSettings = computed(() => {
        return page.props.appSettings as AppSettings
    })

    const appName = computed(() => {
        return appSettings.value?.name || 'HivePanel'
    })

    const appLogo = computed(() => {
        return appSettings.value?.logo || null
    })

    return {
        appSettings,
        appName,
        appLogo,
    }
}