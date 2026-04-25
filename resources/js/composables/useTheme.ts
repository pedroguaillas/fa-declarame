import { ref, watch, onMounted, onUnmounted } from "vue";

type Theme = "light" | "dark" | "system";

// Estado global compartido entre todos los componentes
const theme = ref<Theme>("system");

function getSystemTheme(): "light" | "dark" {
    return window.matchMedia("(prefers-color-scheme: dark)").matches
        ? "dark"
        : "light";
}

function applyTheme(value: Theme) {
    const root = document.documentElement;
    root.classList.remove("light", "dark");
    root.classList.add(value === "system" ? getSystemTheme() : value);
}

// Listener del sistema — se define fuera para poder removerlo
let systemListener: ((e: MediaQueryListEvent) => void) | null = null;
const mediaQuery = window.matchMedia("(prefers-color-scheme: dark)");

function attachSystemListener() {
    removeSystemListener();
    systemListener = (e: MediaQueryListEvent) => {
        if (theme.value === "system") {
            const root = document.documentElement;
            root.classList.remove("light", "dark");
            root.classList.add(e.matches ? "dark" : "light");
        }
    };
    mediaQuery.addEventListener("change", systemListener);
}

function removeSystemListener() {
    if (systemListener) {
        mediaQuery.removeEventListener("change", systemListener);
        systemListener = null;
    }
}

export function useTheme() {
    onMounted(() => {
        const stored = (localStorage.getItem("theme") as Theme) ?? "system";
        theme.value = stored;
        applyTheme(stored);
        attachSystemListener();
    });

    watch(theme, (val) => {
        localStorage.setItem("theme", val);
        applyTheme(val);
    });

    function setTheme(val: Theme) {
        theme.value = val;
    }

    return { theme, setTheme };
}
