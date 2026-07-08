<script setup lang="ts">
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Tooltip,
    Legend,
} from 'chart.js'
import { Line } from 'vue-chartjs'
import { computed } from 'vue'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Tooltip, Legend)

const props = defineProps<{
    title: string
    value: string
    labels: string[]
    used: number[]
    max: number[]
    suffix?: string
}>()

const chartData = computed(() => ({
    labels: props.labels,
    datasets: [
        {
            label: 'In Use',
            data: props.used,
            borderWidth: 2,
            tension: 0.35,
            pointRadius: 0,
        },
        {
            label: 'Maximum',
            data: props.max,
            borderWidth: 2,
            borderDash: [6, 6],
            tension: 0.35,
            pointRadius: 0,
        },
    ],
}))

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            labels: {
                color: '#a1a1aa',
                boxWidth: 10,
                boxHeight: 10,
            },
        },
        tooltip: {
            callbacks: {
                label(context: any) {
                    return `${context.dataset.label}: ${context.parsed.y}${props.suffix ?? ''}`
                },
            },
        },
    },
    scales: {
        x: {
            ticks: {
                color: '#71717a',
                maxTicksLimit: 6,
            },
            grid: {
                color: 'rgba(63, 63, 70, 0.35)',
            },
        },
        y: {
            ticks: {
                color: '#71717a',
                callback(value: number) {
                    return `${value}${props.suffix ?? ''}`
                },
            },
            grid: {
                color: 'rgba(63, 63, 70, 0.35)',
            },
        },
    },
}))
</script>

<template>
    <div class="space-y-3">
        <div class="flex items-center justify-between gap-4">
            <h3 class="font-black text-white">{{ title }}</h3>
            <span class="text-sm font-bold text-zinc-400">{{ value }}</span>
        </div>

        <div class="h-32 rounded-button border border-zinc-800 bg-[#0d0f11] p-3">
            <Line :data="chartData" :options="chartOptions" />
        </div>
    </div>
</template>