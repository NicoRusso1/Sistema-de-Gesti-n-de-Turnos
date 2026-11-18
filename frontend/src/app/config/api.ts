const configurada = (globalThis as { API_URL?: string }).API_URL;

export const API_URL: string = configurada || 'http://localhost:8000/api';
export const GOOGLE_CLIENT_ID = '522813062683-p0jlove4k7ntqi4q0gljt3rgnje42hnq.apps.googleusercontent.com';
