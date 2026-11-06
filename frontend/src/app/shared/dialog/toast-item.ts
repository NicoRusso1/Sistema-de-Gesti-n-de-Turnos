import { Component, ElementRef, OnDestroy, OnInit, inject, input, signal } from '@angular/core';
import { DialogService, ToastItemData } from './dialog.service';

@Component({
  selector: 'app-toast',
  template: `
    <div
      class="toast toast-{{ toast().type }}"
      [class.is-closing]="toast().closing"
      [class.is-paused]="paused()"
      [attr.role]="toast().type === 'error' ? 'alert' : 'status'"
      [attr.aria-live]="toast().type === 'error' ? 'assertive' : 'polite'"
      (mouseenter)="pause()"
      (mouseleave)="resume()"
      (focusin)="pause()"
      (focusout)="onFocusOut($event)"
    >
      <span class="toast-icon" aria-hidden="true">
        @switch (toast().type) {
          @case ('success') {
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path class="toast-check" pathLength="1" d="M5 12.5l4.5 4.5L19 7.5"/></svg>
          }
          @case ('error') {
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 6v7M12 18h.01"/></svg>
          }
          @default {
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 11v6M12 7h.01"/></svg>
          }
        }
      </span>
      <p class="toast-message">{{ toast().message }}</p>
      <button type="button" class="toast-close" aria-label="Cerrar aviso" (click)="close()">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
      </button>
      @if (toast().duration !== null) {
        <span class="toast-bar" [style.animation-duration.ms]="toast().duration" aria-hidden="true"></span>
      }
    </div>
  `,
})
export class ToastItem implements OnInit, OnDestroy {
  readonly toast = input.required<ToastItemData>();
  protected readonly paused = signal(false);

  private readonly svc = inject(DialogService);
  private readonly host = inject<ElementRef<HTMLElement>>(ElementRef);
  private remaining = 0;
  private startedAt = 0;
  private timer: ReturnType<typeof setTimeout> | null = null;

  ngOnInit(): void {
    const duration = this.toast().duration;
    if (duration !== null) {
      this.remaining = duration;
      this.start();
    }
  }

  ngOnDestroy(): void {
    this.stop();
  }

  protected pause(): void {
    if (this.toast().duration === null || this.paused()) {
      return;
    }
    this.paused.set(true);
    this.remaining -= Date.now() - this.startedAt;
    this.stop();
  }

  protected resume(): void {
    if (this.toast().duration === null || !this.paused()) {
      return;
    }
    const el = this.host.nativeElement;
    if (el.matches(':hover') || el.contains(document.activeElement)) {
      return;
    }
    this.paused.set(false);
    this.start();
  }

  protected onFocusOut(event: FocusEvent): void {
    if (!this.host.nativeElement.contains(event.relatedTarget as Node | null)) {
      this.resume();
    }
  }

  protected close(): void {
    this.svc.dismissToast(this.toast().id);
  }

  private start(): void {
    this.startedAt = Date.now();
    this.timer = setTimeout(() => this.close(), Math.max(this.remaining, 0));
  }

  private stop(): void {
    if (this.timer) {
      clearTimeout(this.timer);
      this.timer = null;
    }
  }
}
