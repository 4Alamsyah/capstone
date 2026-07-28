<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <!--
        public/logo.png berisi mark heksagon (atas) + wordmark "Skatra ERP" (bawah).
        Hanya mark-nya yang dipakai sebagai ikon, di-crop lewat offset persentase
        supaya ikut menyesuaikan berapa pun ukuran yang dikirim lewat class.
    -->
    <div
        :class="['relative aspect-square shrink-0 overflow-hidden', className]"
        v-bind="$attrs"
    >
        <img
            src="/logo.png"
            alt="Skatra ERP"
            class="absolute top-[-51.9%] left-[-79.7%] w-[277.8%] max-w-none"
        />
    </div>
</template>
