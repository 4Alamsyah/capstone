export interface AppNotification {
    id: string;
    type: string | null;
    title: string;
    description: string;
    url: string | null;
    read_at: string | null;
    created_at: string;
}

export interface NotificationsShared {
    unreadCount: number;
    recent: AppNotification[];
}
