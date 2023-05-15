import { setLocaleData } from '@wordpress/i18n';

// We are using "@wordpress/i18n" from our bundle while WordPress initializes
// translation data on the core one — we need to pass the data to our code.
export const registerTranslations = () =>
  setLocaleData(window.wp.i18n.getLocaleData('mailpoet'), 'mailpoet');
