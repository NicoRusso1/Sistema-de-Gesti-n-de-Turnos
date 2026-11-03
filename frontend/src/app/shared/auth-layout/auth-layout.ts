import { Component, DestroyRef, inject, input, signal, computed, ViewEncapsulation } from '@angular/core';

export type AuthIcon =
  | 'calendar'
  | 'card'
  | 'document'
  | 'clock'
  | 'folder'
  | 'shield'
  | 'mail'
  | 'link'
  | 'key'
  | 'refresh'
  | 'check';

export interface AuthBenefit {
  icon: AuthIcon;
  title: string;
  text: string;
}

@Component({
  selector: 'app-auth-layout',
  templateUrl: './auth-layout.html',
  styleUrl: './auth-layout.css',
  encapsulation: ViewEncapsulation.None,
})
export class AuthLayout {
  readonly headline = input.required<string>();
  readonly support = input('');
  readonly benefits = input.required<AuthBenefit[]>();
  readonly wide = input(false);
  readonly portrait = input(false);
  protected readonly intro = signal(true);
  protected readonly viewport = signal(document.documentElement.clientWidth);

  protected readonly cut = computed(() =>
    Math.round(this.viewport() * (this.viewport() >= 1400 ? 0.5 : 0.48)),
  );

  protected readonly pulsePath = computed(() => {
    const width = this.viewport();
    const step = width >= 1400 ? 800 : width <= 1100 ? 600 : 700;
    let d = 'M0 40';
    for (let x = 420; x + 98 < width; x += step) {
      d += ` H${x} l20 0 l18 -32 l24 64 l20 -50 l16 18`;
    }
    return `${d} H${width}`;
  });

  constructor() {
    const timer = setTimeout(() => this.intro.set(false), 2000);
    const observer = new ResizeObserver(() => this.viewport.set(document.documentElement.clientWidth));
    observer.observe(document.documentElement);
    inject(DestroyRef).onDestroy(() => {
      clearTimeout(timer);
      observer.disconnect();
    });
  }
}
