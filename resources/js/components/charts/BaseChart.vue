<script setup lang="ts">
import type ApexCharts from 'apexcharts';
import type { ApexOptions } from 'apexcharts';
import { onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';
import { cssVar, onThemeChange } from '@/lib/theme';

/*
 * Thin ApexCharts wrapper: loads the library lazily and applies the theme
 * tokens, re-rendering when light/dark mode changes.
 */
const props = withDefaults(
    defineProps<{
        type: NonNullable<ApexOptions['chart']>['type'];
        series: ApexOptions['series'];
        options?: ApexOptions;
        height?: number;
        /** Describes the chart for screen readers. */
        label: string;
    }>(),
    { options: () => ({}), height: 260 },
);

const el = ref<HTMLDivElement | null>(null);
const chart = shallowRef<ApexCharts | null>(null);

function themed(): ApexOptions {
    const dark = document.documentElement.classList.contains('dark');
    const muted = cssVar('muted-foreground');

    return {
        ...props.options,
        series: props.series,
        chart: {
            fontFamily: 'inherit',
            foreColor: muted,
            background: 'transparent',
            toolbar: { show: false },
            zoom: { enabled: false },
            animations: { speed: 300 },
            parentHeightOffset: 0,
            ...props.options.chart,
            type: props.type,
            height: props.height,
        },
        grid: {
            borderColor: cssVar('border'),
            strokeDashArray: 4,
            ...props.options.grid,
        },
        legend: {
            labels: { colors: muted },
            ...props.options.legend,
        },
        tooltip: {
            theme: dark ? 'dark' : 'light',
            ...props.options.tooltip,
        },
        theme: { mode: dark ? 'dark' : 'light' },
    };
}

async function render() {
    if (!el.value) {
        return;
    }

    const { default: Apex } = await import('apexcharts');
    chart.value?.destroy();
    chart.value = new Apex(el.value, themed());
    await chart.value.render();
}

let stopWatchingTheme = () => {};

onMounted(() => {
    void render();
    stopWatchingTheme = onThemeChange(() => void render());
});

watch(
    () => [props.series, props.options],
    () => void chart.value?.updateOptions(themed()),
    { deep: true },
);

onBeforeUnmount(() => {
    stopWatchingTheme();
    chart.value?.destroy();
});
</script>

<template>
    <div
        ref="el"
        role="img"
        :aria-label="label"
        :style="{ minHeight: `${height}px` }"
    />
</template>
