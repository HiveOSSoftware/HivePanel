<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <img src="https://cdn.thatbritish.one/hivepanel-icon.png" alt="P.S We Care Forms Logo" :class="className" />
</template>
