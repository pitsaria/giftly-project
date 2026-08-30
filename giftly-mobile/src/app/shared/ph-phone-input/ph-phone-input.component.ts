import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { IonInput } from '@ionic/angular';

// PH mobile number field: fixed +63 prefix + exactly 10 digits, shared by
// Register and Checkout (sender/recipient phone) so the rule — and its
// error copy — stays in one place instead of three.
@Component({
  selector: 'app-ph-phone-input',
  templateUrl: 'ph-phone-input.component.html',
  styleUrls: ['ph-phone-input.component.scss'],
  imports: [CommonModule, IonInput],
})
export class PhPhoneInputComponent {
  @Input() label = '';
  @Input() placeholder = '9171234567';
  @Input() digits = '';
  @Input() touched = false;

  @Output() digitsChange = new EventEmitter<string>();
  @Output() touchedChange = new EventEmitter<boolean>();

  get valid(): boolean {
    return /^\d{10}$/.test(this.digits);
  }

  onInput(value: string | null | undefined): void {
    this.digits = (value ?? '').replace(/\D/g, '').slice(0, 10);
    this.digitsChange.emit(this.digits);
  }

  onBlur(): void {
    if (!this.touched) {
      this.touched = true;
      this.touchedChange.emit(true);
    }
  }
}
