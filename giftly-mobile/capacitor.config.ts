import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'com.giftly.app',
  appName: 'Giftly',
  webDir: 'www',
  plugins: {
    // Resize the webview when the soft keyboard opens so inputs stay visible.
    Keyboard: { resize: 'native' },
    // App is light-themed: dark status-bar content, matching toolbar colour,
    // and don't let the webview draw under the status bar.
    StatusBar: {
      style: 'DARK',
      backgroundColor: '#fcfcfc',
      overlaysWebView: false,
    },
  },
};

export default config;
