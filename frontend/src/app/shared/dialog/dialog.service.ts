import { Injectable, signal } from '@angular/core';

export type DialogVariant = 'default' | 'danger' | 'warning';
export type ToastType = 'info' | 'success' | 'error';

export interface ConfirmOptions {
  title: string;
  message?: string;
  confirmText?: string;
  cancelText?: string;
  variant?: DialogVariant;
}

export interface PromptOptions {
  title: string;
  message?: string;
  placeholder?: string;
  confirmText?: string;
  cancelText?: string;
  multiline?: boolean;
  required?: boolean;
}

export interface DialogRequest {
  kind: 'confirm' | 'prompt';
  title: string;
  message: string;
  confirmText: string;
  cancelText: string;
  variant: DialogVariant;
  placeholder: string;
  multiline: boolean;
  required: boolean;
  resolve: (value: boolean | string | null) => void;
}

export interface ToastItemData {
  id: number;
  message: string;
  type: ToastType;
  duration: number | null;
  closing: boolean;
}

const MAX_TOASTS = 3;
const TOAST_EXIT_MS = 180;

@Injectable({ providedIn: 'root' })
export class DialogService {
  readonly current = signal<DialogRequest | null>(null);
  readonly toasts = signal<ToastItemData[]>([]);

  private queue: DialogRequest[] = [];
  private nextId = 1;

  confirm(options: ConfirmOptions): Promise<boolean> {
    return new Promise<boolean>((resolve) => {
      this.enqueue({
        kind: 'confirm',
        title: options.title,
        message: options.message ?? '',
        confirmText: options.confirmText ?? 'Confirmar',
        cancelText: options.cancelText ?? 'Cancelar',
        variant: options.variant ?? 'default',
        placeholder: '',
        multiline: false,
        required: false,
        resolve: (value) => resolve(value === true),
      });
    });
  }

  prompt(options: PromptOptions): Promise<string | null> {
    return new Promise<string | null>((resolve) => {
      this.enqueue({
        kind: 'prompt',
        title: options.title,
        message: options.message ?? '',
        confirmText: options.confirmText ?? 'Aceptar',
        cancelText: options.cancelText ?? 'Cancelar',
        variant: 'default',
        placeholder: options.placeholder ?? '',
        multiline: options.multiline ?? false,
        required: options.required ?? false,
        resolve: (value) => resolve(typeof value === 'string' ? value : null),
      });
    });
  }

  toast(message: string, type: ToastType = 'info'): void {
    const duration = type === 'error' ? (message.length > 120 ? null : 6000) : 5000;
    const item: ToastItemData = { id: this.nextId++, message, type, duration, closing: false };
    this.toasts.update((list) => [...list, item]);

    const active = this.toasts().filter((t) => !t.closing);
    if (active.length > MAX_TOASTS) {
      this.dismissToast(active[0].id);
    }
  }

  dismissToast(id: number): void {
    const target = this.toasts().find((t) => t.id === id);
    if (!target || target.closing) {
      return;
    }
    this.toasts.update((list) => list.map((t) => (t.id === id ? { ...t, closing: true } : t)));
    setTimeout(() => this.toasts.update((list) => list.filter((t) => t.id !== id)), TOAST_EXIT_MS);
  }

  finish(request: DialogRequest, value: boolean | string | null): void {
    if (this.current() !== request) {
      return;
    }
    this.current.set(this.queue.shift() ?? null);
    request.resolve(value);
  }

  private enqueue(request: DialogRequest): void {
    if (this.current()) {
      this.queue.push(request);
    } else {
      this.current.set(request);
    }
  }
}
