<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

// VALO's Time: the liquid-glass "lens" that slides to the active item of its parent element
// (sidebar link, tab, day). It starts where the lens was on the previous page so it glides across navigations.
const props = withDefaults(
    defineProps<{
        selector: string;
        storageKey: string;
        axis?: 'x' | 'y';
    }>(),
    { axis: 'x' }
);

type Box = { x: number; y: number; w: number; h: number };

const el = ref<HTMLElement | null>(null);
const box = ref<Box | null>(null);
const animate = ref(false);
const moving = ref(false);
let last = '';
let movingTimer: number | undefined;
let resizeObserver: ResizeObserver | undefined;
let mutationObserver: MutationObserver | undefined;

function measure(): Box | null {
    const container = el.value?.parentElement;
    const target = container?.querySelector<HTMLElement>(props.selector);
    if (!container || !target || target.offsetParent === null) {
        return null;
    }
    const c = container.getBoundingClientRect();
    const t = target.getBoundingClientRect();
    return {
        x: Math.round(t.left - c.left + container.scrollLeft),
        y: Math.round(t.top - c.top + container.scrollTop),
        w: Math.round(t.width),
        h: Math.round(t.height),
    };
}

function readStored(): Box | null {
    try {
        return JSON.parse(sessionStorage.getItem('valo-lens-' + props.storageKey) ?? 'null');
    } catch {
        return null;
    }
}

function update(withMotion: boolean) {
    const next = measure();
    const key = JSON.stringify(next);
    if (key === last) {
        return;
    }
    if (withMotion && box.value && next) {
        moving.value = true;
        window.clearTimeout(movingTimer);
        movingTimer = window.setTimeout(() => (moving.value = false), 240);
    }
    animate.value = withMotion;
    last = key;
    box.value = next;
    if (next) {
        try {
            sessionStorage.setItem('valo-lens-' + props.storageKey, key);
        } catch {
            // private mode: the lens just does not glide between pages
        }
    }
}

onMounted(async () => {
    await nextTick();
    const previous = readStored();
    const current = measure();
    if (previous && current && JSON.stringify(previous) !== JSON.stringify(current)) {
        box.value = previous;
        last = JSON.stringify(previous);
        requestAnimationFrame(() => requestAnimationFrame(() => update(true)));
    } else {
        update(false);
    }
    const container = el.value?.parentElement;
    if (!container) {
        return;
    }
    resizeObserver = new ResizeObserver(() => update(false));
    resizeObserver.observe(container);
    mutationObserver = new MutationObserver(() => update(true));
    mutationObserver.observe(container, {
        subtree: true,
        childList: true,
        attributes: true,
        attributeFilter: ['class', 'data-state', 'aria-pressed', 'aria-current'],
    });
    container.addEventListener('animationend', onSettled);
    container.addEventListener('transitionend', onSettled);
});

function onSettled(event: Event) {
    if (event.target !== el.value && !el.value?.contains(event.target as Node)) {
        update(false);
    }
}

onBeforeUnmount(() => {
    resizeObserver?.disconnect();
    mutationObserver?.disconnect();
    window.clearTimeout(movingTimer);
    const container = el.value?.parentElement;
    container?.removeEventListener('animationend', onSettled);
    container?.removeEventListener('transitionend', onSettled);
});
</script>

<template>
    <div
        ref="el"
        aria-hidden="true"
        class="valo-lens-track"
        :class="{ 'valo-lens-animate': animate }"
        :style="
            box
                ? {
                      transform: `translate(${box.x}px, ${box.y}px)`,
                      width: `${box.w}px`,
                      height: `${box.h}px`,
                  }
                : { opacity: 0 }
        ">
        <div
            class="valo-lens"
            :class="[
                axis === 'y' ? 'valo-lens-y' : 'valo-lens-x',
                { 'valo-lens-moving': moving },
            ]"></div>
    </div>
</template>
