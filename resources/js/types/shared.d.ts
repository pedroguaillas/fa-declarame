export interface BadgeDef<T> {
    value: (item: T) => string;
    variant?: (item: T) => "default" | "secondary" | "destructive" | "outline";
    class?: (item: T) => string;
}

export interface AvatarDef<T> {
    src: (item: T) => string | null | undefined;
    fallback?: (item: T) => string;
}

export interface ColumnDef<T> {
    key: keyof T;
    label: string;
    width?: string;
    align?: "left" | "center" | "right";
    badge?: BadgeDef<T>;
    avatar?: AvatarDef<T>;
    format?: (value: any, item: T) => string;
}

export interface ActionDef<T> {
    icon?: any;
    label: string;
    event: string;
    class?: string;
    separator?: boolean;
    show?: (item: T) => boolean;
    type?: "check";
    checked?: (item: T) => boolean;
    tooltip?: string;
}

export interface ActionPayload<T> {
    event: string;
    item: T;
}
