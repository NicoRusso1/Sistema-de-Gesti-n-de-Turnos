const configurada = (globalThis as { API_URL?: string }).API_URL;

export const API_URL: string = configurada || 'http://localhost:8000/api';
