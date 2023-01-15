import { updateSettings } from './updateSettings';

export const finishWizard = async (redirect_url = null) => {
  try {
    await updateSettings({
      version: window.mailpoet_version,
    });
    if (redirect_url) {
      window.location.href = redirect_url;
    } else {
      window.location.href = window.finish_wizard_url;
    }
  } catch (e) {
    // logging the error or just leaving it ....
  }
};
