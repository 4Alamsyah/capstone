<script setup lang="ts">
import { Pencil } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted } from 'vue';
import { usePartContextMenu } from '@/composables/usePartContextMenu';

const { state, closePartContextMenu, goToEditPart } = usePartContextMenu();

const handleDismiss = (): void => {
    if (state.visible) {
        closePartContextMenu();
    }
};

const handleKeydown = (event: KeyboardEvent): void => {
    if (event.key === 'Escape') {
        closePartContextMenu();
    }
};

onMounted(() => {
    window.addEventListener('click', handleDismiss);
    window.addEventListener('keydown', handleKeydown);
    window.addEventListener('scroll', handleDismiss, true);
    window.addEventListener('resize', handleDismiss);
});

onBeforeUnmount(() => {
    window.removeEventListener('click', handleDismiss);
    window.removeEventListener('keydown', handleKeydown);
    window.removeEventListener('scroll', handleDismiss, true);
    window.removeEventListener('resize', handleDismiss);
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="state.visible"
            class="fixed z-50 min-w-[180px] overflow-hidden rounded-md border border-sidebar-border/70 bg-popover py-1 text-popover-foreground shadow-md"
            :style="{ top: `${state.y}px`, left: `${state.x}px` }"
            @click.stop
            @contextmenu.prevent.stop
        >
            <button
                type="button"
                class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-accent hover:text-accent-foreground"
                @click="goToEditPart"
            >
                <Pencil class="h-4 w-4" />
                Edit Part
            </button>
        </div>
    </Teleport>
</template>
