import { reactive } from 'vue'

type ConfirmOptions = {
    title: string
    description?: string
    confirmText?: string
    cancelText?: string
    danger?: boolean
}

const state = reactive({
    open: false,
    loading: false,
    title: '',
    description: '',
    confirmText: 'Confirm',
    cancelText: 'Cancel',
    danger: false,
    resolve: null as null | ((value: boolean) => void),
})

export function useConfirm() {
    function confirm(options: ConfirmOptions): Promise<boolean> {
        state.open = true
        state.loading = false
        state.title = options.title
        state.description = options.description ?? ''
        state.confirmText = options.confirmText ?? 'Confirm'
        state.cancelText = options.cancelText ?? 'Cancel'
        state.danger = options.danger ?? false

        return new Promise(resolve => {
            state.resolve = resolve
        })
    }

    function accept() {
        state.open = false
        state.resolve?.(true)
        state.resolve = null
    }

    function cancel() {
        state.open = false
        state.resolve?.(false)
        state.resolve = null
    }

    return {
        state,
        confirm,
        accept,
        cancel,
    }
}