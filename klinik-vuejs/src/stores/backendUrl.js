import { defineStore } from 'pinia';

export const useBackendUrl = defineStore({
    id: 'BackendUrl',
    state: () => ({
        backendUrl: import.meta.env.VITE_API_ORIGIN || ''
    }),
});

