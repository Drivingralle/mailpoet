import { __ } from '@wordpress/i18n';

import { PremiumModalForStepEdit } from '../../../../../common/premium_modal';
import { LockedBadge } from '../../../../../common/premium_modal/locked_badge';
import { StepType } from '../../../../editor/store/types';
import { Icon } from './icon';

const keywords = [
  __('notification', 'mailpoet-premium'),
  __('email', 'mailpoet-premium'),
];
export const step: StepType = {
  key: 'mailpoet:notification-email',
  group: 'actions',
  title: () => __('Send notification email', 'mailpoet'),
  description: () =>
    __(
      'Receive a notification when a contact reaches a specific point in automation.',
      'mailpoet',
    ),
  subtitle: () => <LockedBadge text={__('Premium', 'mailpoet')} />,
  keywords,
  foreground: '#996800',
  background: '#FCF9E8',
  icon: () => <Icon />,
  edit: () => (
    <PremiumModalForStepEdit
      tracking={{
        utm_medium: 'upsell_modal',
        utm_campaign: 'create_automation_editor_notification_email',
      }}
    >
      {__('Sending notification emails is a premium feature.', 'mailpoet')}
    </PremiumModalForStepEdit>
  ),
  createStep: (stepData) => stepData,
} as const;
