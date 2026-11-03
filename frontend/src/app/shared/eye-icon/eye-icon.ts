import { Component, input } from '@angular/core';

@Component({
  selector: 'app-eye-icon',
  template: `
    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z" />
      <circle cx="12" cy="12" r="3" />
      @if (off()) {
        <path d="M4 4l16 16" />
      }
    </svg>
  `,
  styles: ':host { display: flex; }',
})
export class EyeIcon {
  readonly off = input(false);
}
