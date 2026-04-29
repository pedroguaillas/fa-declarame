import { ref } from 'vue';

export function useClipboard() {
  const copied = ref(false);

  const copy = async (text: string | null | undefined) => {
    if (!text) return;

    try {
      await navigator.clipboard.writeText(text);
      copied.value = true;

      setTimeout(() => {
        copied.value = false;
      }, 2000);
    } catch (error) {
      console.error('Error al copiar:', error);
    }
  };

  return {
    copied,
    copy,
  };
}