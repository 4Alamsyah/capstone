import { router } from '@inertiajs/vue3';
import { reactive } from 'vue';

export type PartContextMenuTarget = {
    id: number;
    part_number: string;
};

type PartContextMenuState = {
    visible: boolean;
    x: number;
    y: number;
    part: PartContextMenuTarget | null;
};

// Module-level (not inside the composable function) so every component that calls
// usePartContextMenu() shares one menu instance instead of each getting its own -
// there is only ever one context menu open at a time across the whole app.
const state = reactive<PartContextMenuState>({
    visible: false,
    x: 0,
    y: 0,
    part: null,
});

const MENU_WIDTH = 180;
const MENU_HEIGHT = 44;

function openPartContextMenu(event: MouseEvent, part: PartContextMenuTarget): void {
    event.preventDefault();

    state.part = part;
    state.x = Math.min(event.clientX, window.innerWidth - MENU_WIDTH - 8);
    state.y = Math.min(event.clientY, window.innerHeight - MENU_HEIGHT - 8);
    state.visible = true;
}

function closePartContextMenu(): void {
    state.visible = false;
    state.part = null;
}

function goToEditPart(): void {
    const part = state.part;

    if (!part) {
        return;
    }

    closePartContextMenu();

    router.visit(`/parts?search=${encodeURIComponent(part.part_number)}&edit=${part.id}`);
}

export function usePartContextMenu() {
    return { state, openPartContextMenu, closePartContextMenu, goToEditPart };
}
