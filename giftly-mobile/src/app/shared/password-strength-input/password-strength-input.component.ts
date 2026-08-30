import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { IonInput, IonInputPasswordToggle } from '@ionic/angular';

const SPECIAL_CHARS = /[!@#$%^&*(),.?":{}|<>]/;

interface PasswordChecks {
  length: boolean;
  letter: boolean;
  number: boolean;
  special: boolean;
}

// Password field with a show/hide eye toggle (Ionic's built-in
// ion-input-password-toggle) and a live strength meter, matching
// modal_register.php's validatePassword(): 8+ chars, a letter, a number,
// and a special character. Shared by Register and Reset Password so the
// rule stays in one place.
@Component({
  selector: 'app-password-strength-input',
  templateUrl: 'password-strength-input.component.html',
  styleUrls: ['password-strength-input.component.scss'],
  imports: [CommonModule, IonInput, IonInputPasswordToggle],
})
export class PasswordStrengthInputComponent {
  @Input() label = 'Password';
  @Input() value = '';

  @Output() valueChange = new EventEmitter<string>();
  @Output() validChange = new EventEmitter<boolean>();

  get checks(): PasswordChecks {
    const p = this.value;
    return {
      length: p.length >= 8,
      letter: /[a-zA-Z]/.test(p),
      number: /\d/.test(p),
      special: SPECIAL_CHARS.test(p),
    };
  }

  get valid(): boolean {
    const c = this.checks;
    return c.length && c.letter && c.number && c.special;
  }

  get strengthPct(): number {
    if (!this.value) return 0;
    const c = this.checks;
    if (!c.length || !c.letter) return 30;
    if (!c.number) return 60;
    if (!c.special) return 80;
    return 100;
  }

  get strengthLevel(): 'weak' | 'medium' | 'strong' {
    const pct = this.strengthPct;
    if (pct >= 100) return 'strong';
    if (pct >= 60) return 'medium';
    return 'weak';
  }

  get strengthLabel(): string {
    if (!this.value) return 'Use 8 or more letters, numbers and symbols';
    const c = this.checks;
    if (!c.length) return 'Use at least 8 characters';
    if (!c.letter) return 'Add a letter';
    if (!c.number) return 'Add a number';
    if (!c.special) return 'Add a special character';
    return 'Strong password!';
  }

  onInput(value: string | null | undefined): void {
    this.value = value ?? '';
    this.valueChange.emit(this.value);
    this.validChange.emit(this.valid);
  }
}
