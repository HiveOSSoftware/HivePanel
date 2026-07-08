import { computed, ref } from 'vue'

export type CellStatus = 'offline' | 'starting' | 'running' | 'stopping'

const activeCell = ref<any>(null)
const activeCellStats = ref<any>(null)
const activeCellStatus = ref<CellStatus>('offline')

let pollTimer: number | undefined
let pollingCellId: string | null = null
let loading = false

function normaliseStatus(status?: string): CellStatus {
    if (status === 'running' || status === 'starting' || status === 'stopping') return status
    return 'offline'
}

async function refreshActiveCell() {
    if (!activeCell.value?.id || loading) return

    loading = true

    try {
        const response = await fetch(`/cells/${activeCell.value.id}/stats-json`, {
            headers: { Accept: 'application/json' },
        })

        if (!response.ok) return

        const stats = await response.json()

        activeCellStats.value = stats
        activeCellStatus.value = stats.running ? 'running' : normaliseStatus(activeCell.value.status)
    } finally {
        loading = false
    }
}

function setActiveCell(cell: any, status?: CellStatus) {
    activeCell.value = cell
    activeCellStatus.value = status ?? normaliseStatus(cell?.status)

    if (!cell?.id) return

    if (pollingCellId === cell.id && pollTimer) return

    stopActiveCellPolling()

    pollingCellId = cell.id
    refreshActiveCell()

    pollTimer = window.setInterval(refreshActiveCell, 2000)
}

function stopActiveCellPolling() {
    if (pollTimer) {
        clearInterval(pollTimer)
    }

    pollTimer = undefined
    pollingCellId = null
}

function updateActiveCellStatus(status: CellStatus) {
    activeCellStatus.value = status
}

export function useActiveCell() {
    return {
        activeCell,
        activeCellStats,
        activeCellStatus: computed(() => activeCellStatus.value),

        setActiveCell,
        refreshActiveCell,
        stopActiveCellPolling,
        updateActiveCellStatus,
    }
}