<script setup lang="ts">
import { usePartContextMenu } from '@/composables/usePartContextMenu';

type Props = {
    partId: number;
    partNumber: string;
};

const props = defineProps<Props>();

const { openPartContextMenu } = usePartContextMenu();

const onContextMenu = (event: MouseEvent): void => {
    openPartContextMenu(event, { id: props.partId, part_number: props.partNumber });
};
</script>

<template>
    <!-- display:contents keeps this wrapper invisible to layout (block children like
         <p> inside stay valid), while contextmenu still bubbles through it normally. -->
    <div class="contents" @contextmenu.stop="onContextMenu">
        <slot />
    </div>
</template>
