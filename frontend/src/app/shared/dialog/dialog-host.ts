import { Component, ElementRef, HostListener, OnDestroy, effect, inject, signal, viewChild } from '@angular/core';
import { DialogRequest, DialogService } from './dialog.service';
import { ToastItem } from './toast-item';

@Component({
  selector: 'app-dialog-host',
  imports: [ToastItem],
  templateUrl: './dialog-host.html',
})
export class DialogHost implements OnDestroy {
  protected readonly svc = inject(DialogService);
  protected readonly closing = signal(false);
  protected readonly value = signal('');

  private readonly panel = viewChild<ElementRef<HTMLElement>>('panel');
  private readonly field = viewChild<ElementRef<HTMLInputElement | HTMLTextAreaElement>>('field');
  private readonly cancelBtn = viewChild<ElementRef<HTMLButtonElement>>('cancelBtn');
  private readonly confirmBtn = viewChild<ElementRef<HTMLButtonElement>>('confirmBtn');

  private opener: HTMLElement | null = null;
  private wasOpen = false;
  private closeTimer: ReturnType<typeof setTimeout> | null = null;

  constructor() {
    effect(() => {
      const current = this.svc.current();
      if (current) {
        if (!this.wasOpen) {
          this.opener = document.activeElement instanceof HTMLElement ? document.activeElement : null;
          document.body.style.overflow = 'hidden';
        }
        this.wasOpen = true;
        this.value.set('');
        this.closing.set(false);
        setTimeout(() => this.focusInitial(current));
      } else if (this.wasOpen) {
        this.wasOpen = false;
        document.body.style.overflow = '';
        const target = this.opener;
        this.opener = null;
        setTimeout(() => {
          if (target?.isConnected) {
            target.focus();
          }
        });
      }
    });
  }

  ngOnDestroy(): void {
    document.body.style.overflow = '';
    if (this.closeTimer) {
      clearTimeout(this.closeTimer);
    }
  }

  protected onInput(event: Event): void {
    this.value.set((event.target as HTMLInputElement | HTMLTextAreaElement).value);
  }

  protected cancel(d: DialogRequest): void {
    this.close(d, d.kind === 'prompt' ? null : false);
  }

  protected confirm(d: DialogRequest): void {
    if (d.kind === 'prompt') {
      if (d.required && !this.value().trim()) {
        return;
      }
      this.close(d, this.value());
    } else {
      this.close(d, true);
    }
  }

  protected onBackdropClick(event: MouseEvent, d: DialogRequest): void {
    if (event.target === event.currentTarget && d.variant !== 'danger') {
      this.cancel(d);
    }
  }

  @HostListener('document:keydown', ['$event'])
  protected onKeydown(event: KeyboardEvent): void {
    const d = this.svc.current();
    if (!d) {
      return;
    }
    if (this.closing()) {
      event.preventDefault();
      return;
    }

    if (event.key === 'Escape') {
      event.preventDefault();
      event.stopPropagation();
      this.cancel(d);
      return;
    }

    if (event.key === 'Enter') {
      const target = event.target as HTMLElement;
      if (target.tagName === 'TEXTAREA' || target.tagName === 'BUTTON') {
        return;
      }
      event.preventDefault();
      this.confirm(d);
      return;
    }

    if (event.key === 'Tab') {
      this.trapFocus(event);
    }
  }

  private trapFocus(event: KeyboardEvent): void {
    const root = this.panel()?.nativeElement;
    if (!root) {
      return;
    }
    const items = Array.from(
      root.querySelectorAll<HTMLElement>('button:not([disabled]), input, textarea'),
    );
    if (!items.length) {
      event.preventDefault();
      return;
    }
    const first = items[0];
    const last = items[items.length - 1];
    const active = document.activeElement;
    if (event.shiftKey && (active === first || !root.contains(active))) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && (active === last || !root.contains(active))) {
      event.preventDefault();
      first.focus();
    }
  }

  private focusInitial(d: DialogRequest): void {
    if (d.kind === 'prompt') {
      this.field()?.nativeElement.focus();
    } else if (d.variant === 'default') {
      this.confirmBtn()?.nativeElement.focus();
    } else {
      this.cancelBtn()?.nativeElement.focus();
    }
  }

  private close(d: DialogRequest, result: boolean | string | null): void {
    if (this.closing()) {
      return;
    }
    this.closing.set(true);
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    this.closeTimer = setTimeout(() => {
      this.closeTimer = null;
      this.svc.finish(d, result);
    }, reduced ? 100 : 160);
  }
}
